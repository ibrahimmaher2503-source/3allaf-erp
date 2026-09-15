<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Policies\SupplierReturnPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class SubmitPurchaseReturnAction
{
    public function execute(int $id, ?int $expectedVersion = null): PurchaseReturn
    {
        Gate::authorize('purchase_returns.edit');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $actorId = (int) $actor->getAuthIdentifier();

        return DB::transaction(function () use ($actor, $actorId, $id, $expectedVersion): PurchaseReturn {
            $return = PurchaseReturn::query()
                ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
                ->with(['lines', 'reason', 'store'])
                ->lockForUpdate()
                ->findOrFail($id);
            if ($expectedVersion !== null && $return->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This supplier return was modified in another session.'));
            }
            if ($return->status !== 'draft') {
                throw new InvalidArgumentException(__('Only draft supplier returns can be submitted.'));
            }
            if ($return->lines->isEmpty()) {
                throw new InvalidArgumentException(__('A supplier return must contain at least one line.'));
            }
            if ($return->reason === null || ! $return->reason->is_active) {
                throw new InvalidArgumentException(__('An active supplier return reason is required.'));
            }

            $before = $return->only(['status', 'lock_version']);
            $nextVersion = $return->lock_version + 1;
            $approvalData = new ApprovalRequestData(
                sourceType: 'purchase_returns',
                sourceId: (string) $return->id,
                sourceVersion: (string) $nextVersion,
                requestedAction: 'approve',
                requestPermission: 'purchase_returns.edit',
                branchId: $return->store?->branch_id,
                storeId: $return->store_id,
                reasonCode: $return->reason->code,
                limitContext: [
                    'amount' => (string) $return->total_amount,
                    'configured_limit' => app(SupplierReturnPolicy::class)->approvalLimit(),
                    'source' => 'financial_setting_versions',
                ],
                idempotencyKey: 'purchase-return-approval:'.$return->id.':'.$nextVersion,
            );
            $existingApproval = ApprovalRecord::query()
                ->where('idempotency_key', $approvalData->idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existingApproval !== null) {
                if ($existingApproval->requester_id !== $actorId
                    || $existingApproval->source_type !== $approvalData->sourceType
                    || $existingApproval->source_id !== $approvalData->sourceId
                    || $existingApproval->requested_action !== $approvalData->requestedAction
                    || $existingApproval->source_version !== $approvalData->sourceVersion
                    || $existingApproval->source_hash !== $approvalData->sourceHash) {
                    throw ValidationException::withMessages(['idempotency_key' => __('This idempotency key belongs to another approval request.')]);
                }
            } else {
                $pendingApproval = ApprovalRecord::query()
                    ->where('pending_key', $approvalData->pendingKey())
                    ->lockForUpdate()
                    ->first();
                if ($pendingApproval !== null
                    && ($pendingApproval->requester_id !== $actorId
                        || $pendingApproval->source_type !== $approvalData->sourceType
                        || $pendingApproval->source_id !== $approvalData->sourceId
                        || $pendingApproval->requested_action !== $approvalData->requestedAction
                        || $pendingApproval->source_version !== $approvalData->sourceVersion
                        || $pendingApproval->source_hash !== $approvalData->sourceHash)) {
                    throw ValidationException::withMessages(['source' => __('A pending approval already exists for this action.')]);
                }
            }
            $return->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'submitted_by' => $actorId,
                'updated_by' => $actorId,
                'lock_version' => $nextVersion,
            ]);
            app(RequestApproval::class)->execute($approvalData);
            app(RecordAuditEvent::class)->execute(
                category: 'procurement',
                event: 'submit_supplier_return',
                source: $return,
                before: $before,
                after: $return->only(['status', 'submitted_at', 'lock_version']),
                storeId: $return->store_id,
                reasonCode: $return->reason->code,
            );

            return $return->fresh(['supplier', 'store', 'reason', 'purchaseInvoice', 'lines.product']);
        });
    }
}
