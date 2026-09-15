<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RequestApproval
{
    public function execute(ApprovalRequestData $data): ApprovalRecord
    {
        /** @var User $requester */
        $requester = Auth::user() ?? throw new \LogicException('An authenticated requester is required.');
        Gate::forUser($requester)->authorize($data->requestPermission);
        $this->validateSourceReference($data);
        $this->authorizeScope($requester, $data);

        try {
            return DB::transaction(function () use ($data, $requester): ApprovalRecord {
                if ($data->idempotencyKey !== null) {
                    $existing = ApprovalRecord::query()
                        ->where('idempotency_key', $data->idempotencyKey)
                        ->lockForUpdate()
                        ->first();

                    if ($existing !== null) {
                        if ($this->isSameRequest($existing, $data, $requester)) {
                            return $existing;
                        }

                        throw ValidationException::withMessages(['idempotency_key' => __('This idempotency key belongs to another approval request.')]);
                    }
                }

                $pending = ApprovalRecord::query()->where('pending_key', $data->pendingKey())->lockForUpdate()->first();
                if ($pending !== null && $data->idempotencyKey !== null && $this->isSameRequest($pending, $data, $requester)) {
                    return $pending;
                }
                if ($pending !== null) {
                    throw ValidationException::withMessages(['source' => __('A pending approval already exists for this action.')]);
                }

                $record = ApprovalRecord::create([
                    'uuid' => (string) Str::uuid(),
                    'source_type' => $data->sourceType,
                    'source_id' => $data->sourceId,
                    'source_version' => $data->sourceVersion,
                    'source_hash' => $data->sourceHash,
                    'requested_action' => $data->requestedAction,
                    'request_permission' => $data->requestPermission,
                    'decision_permission' => $data->approvalPermission(),
                    'approval_state' => ApprovalState::Pending,
                    'requester_id' => $requester->id,
                    'branch_id' => $data->branchId,
                    'store_id' => $data->storeId,
                    'reason_code' => $data->reasonCode,
                    'reason_text' => $data->reasonText,
                    'limit_context' => $data->limitContext,
                    'request_id' => Context::get('request_id') ?? (string) Str::uuid(),
                    'idempotency_key' => $data->idempotencyKey,
                    'pending_key' => $data->pendingKey(),
                    'requested_at' => now(),
                    'expires_at' => $data->expiresAt,
                ]);

                app(RecordAuditEvent::class)->execute(
                    category: 'workflow',
                    event: 'approval_requested',
                    source: $record,
                    after: $this->auditValues($record),
                    branchId: $record->branch_id,
                    storeId: $record->store_id,
                    reasonCode: $record->reason_code,
                    reasonText: $record->reason_text,
                    metadata: [
                        'approval_record_id' => $record->id,
                        'source_type' => $record->source_type,
                        'source_id' => $record->source_id,
                        'requested_action' => $record->requested_action,
                        'request_permission' => $data->requestPermission,
                        'decision_permission' => $data->approvalPermission(),
                    ],
                    requestId: $record->request_id,
                );

                return $record;
            }, 5);
        } catch (QueryException $exception) {
            // A concurrent request may pass the pre-insert checks before either
            // transaction owns the unique idempotency or pending key. Resolve
            // the winning row into a deterministic replay/conflict response.
            $existing = ApprovalRecord::query()
                ->where(function ($query) use ($data): void {
                    if ($data->idempotencyKey !== null) {
                        $query->where('idempotency_key', $data->idempotencyKey)
                            ->orWhere('pending_key', $data->pendingKey());

                        return;
                    }

                    $query->where('pending_key', $data->pendingKey());
                })
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            if ($data->idempotencyKey !== null
                && $existing->idempotency_key === $data->idempotencyKey
                && $this->isSameRequest($existing, $data, $requester)) {
                return $existing;
            }

            throw ValidationException::withMessages([
                'source' => __('A pending approval already exists for this action.'),
            ]);
        }
    }

    private function validateSourceReference(ApprovalRequestData $data): void
    {
        if ($data->sourceType === '' || $data->sourceId === '' || $data->requestedAction === '') {
            throw ValidationException::withMessages(['source' => __('A source type, source identifier, and requested action are required.')]);
        }

        if ($data->sourceVersion === null && $data->sourceHash === null) {
            throw ValidationException::withMessages(['source_version' => __('A source version or hash is required.')]);
        }

        if ($data->expiresAt !== null && $data->expiresAt->isPast()) {
            throw ValidationException::withMessages(['expires_at' => __('Approval expiry must be in the future.')]);
        }
    }

    private function authorizeScope(User $requester, ApprovalRequestData $data): void
    {
        if ($data->branchId === null && $data->storeId === null && ! $requester->is_super_admin && ! $requester->hasPermission($data->approvalPermission())) {
            throw ValidationException::withMessages(['scope' => __('A branch or store scope is required for non-global approval requests.')]);
        }

        $store = $data->storeId !== null
            ? Store::query()->findOrFail($data->storeId)
            : null;

        // A store-scoped operator is allowed to request a decision for the
        // branch that owns their assigned store. The branch is still recorded
        // and must match the store, but a separate branch scope is not needed.
        if ($data->branchId !== null
            && ! $requester->canAccessBranch($data->branchId)
            && ($store === null || ! $requester->canAccessStore($store->id))) {
            abort(403);
        }

        if ($store !== null) {
            if (! $requester->canAccessStore($store->id)
                || ($data->branchId !== null && $store->branch_id !== $data->branchId)) {
                abort(403);
            }
        }
    }

    private function isSameRequest(ApprovalRecord $record, ApprovalRequestData $data, User $requester): bool
    {
        return $record->requester_id === $requester->id
            && $record->source_type === $data->sourceType
            && $record->source_id === $data->sourceId
            && $record->requested_action === $data->requestedAction
            && $record->source_version === $data->sourceVersion
            && $record->source_hash === $data->sourceHash;
    }

    /** @return array<string, mixed> */
    private function auditValues(ApprovalRecord $record): array
    {
        return [
            'id' => $record->id,
            'source_type' => $record->source_type,
            'source_id' => $record->source_id,
            'source_version' => $record->source_version,
            'source_hash' => $record->source_hash,
            'requested_action' => $record->requested_action,
            'request_permission' => $record->request_permission,
            'decision_permission' => $record->decision_permission,
            'approval_state' => $record->approval_state->value,
            'requester_id' => $record->requester_id,
            'branch_id' => $record->branch_id,
            'store_id' => $record->store_id,
            'reason_code' => $record->reason_code,
            'reason_text' => $record->reason_text,
            'limit_context' => $record->limit_context,
            'request_id' => $record->request_id,
        ];
    }
}
