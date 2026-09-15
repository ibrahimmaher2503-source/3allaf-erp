<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalRecordTransition
{
    /**
     * Apply a terminal approval-request decision and audit it in the same transaction.
     *
     * @param  array<string, mixed>  $attributes
     * @param  callable(ApprovalRecord): void  $authorize
     */
    public function execute(
        ApprovalRecord $record,
        ApprovalState $state,
        string $event,
        array $attributes = [],
        ?string $expectedSourceVersion = null,
        ?string $expectedSourceHash = null,
        ?\Closure $authorize = null,
    ): ApprovalRecord {
        return DB::transaction(function () use ($record, $state, $event, $attributes, $expectedSourceVersion, $expectedSourceHash, $authorize): ApprovalRecord {
            $record = ApprovalRecord::query()->lockForUpdate()->findOrFail($record->id);
            if ($record->approval_state->isTerminal()) {
                throw ValidationException::withMessages(['approval_state' => __('This approval request is already terminal.')]);
            }
            if ($authorize !== null) {
                $authorize($record);
            }
            $this->assertCurrentSource($record, $expectedSourceVersion, $expectedSourceHash);

            $before = $this->auditValues($record);
            $record->transitionTo($state, $attributes);
            $record->refresh();

            app(RecordAuditEvent::class)->execute(
                category: 'workflow',
                event: $event,
                source: $record,
                before: $before,
                after: $this->auditValues($record),
                branchId: $record->branch_id,
                storeId: $record->store_id,
                reasonCode: $record->reason_code,
                reasonText: $record->decision_note ?? $record->reason_text,
                metadata: [
                    'approval_record_id' => $record->id,
                    'source_type' => $record->source_type,
                    'source_id' => $record->source_id,
                    'requested_action' => $record->requested_action,
                    ...((Auth::user()?->canBypassApproval() ?? false) ? ['super_admin_bypass' => true] : []),
                ],
                requestId: $record->request_id,
            );

            return $record;
        });
    }

    private function assertCurrentSource(ApprovalRecord $record, ?string $sourceVersion, ?string $sourceHash): void
    {
        if ($record->source_version !== null && $sourceVersion !== $record->source_version) {
            throw ValidationException::withMessages(['source_version' => __('The approval request is stale. Reload the source record and try again.')]);
        }

        if ($record->source_hash !== null && $sourceHash !== $record->source_hash) {
            throw ValidationException::withMessages(['source_hash' => __('The approval request is stale. Reload the source record and try again.')]);
        }
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
            'approval_state' => $record->approval_state->value,
            'requester_id' => $record->requester_id,
            'approver_id' => $record->approver_id,
            'branch_id' => $record->branch_id,
            'store_id' => $record->store_id,
            'reason_code' => $record->reason_code,
            'reason_text' => $record->reason_text,
            'decision_note' => $record->decision_note,
            'limit_context' => $record->limit_context,
            'request_id' => $record->request_id,
        ];
    }
}
