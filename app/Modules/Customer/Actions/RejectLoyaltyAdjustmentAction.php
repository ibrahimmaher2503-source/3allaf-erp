<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\LoyaltyAdjustment;
use App\Modules\Platform\Actions\ApprovalRecordTransition;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RejectLoyaltyAdjustmentAction
{
    public function execute(User $reviewer, ApprovalRecord $approval, string $reason): void
    {
        Gate::forUser($reviewer)->authorize('decide', $approval);
        abort_unless($approval->decisionPermission() === 'loyalty.approve', 403);

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['decision_note' => __('A rejection reason is required.')]);
        }

        DB::transaction(function () use ($reviewer, $approval, $reason): void {
            $approval = ApprovalRecord::query()->lockForUpdate()->findOrFail($approval->id);
            Gate::forUser($reviewer)->authorize('decide', $approval);
            $adjustment = LoyaltyAdjustment::query()->lockForUpdate()->findOrFail((int) $approval->source_id);
            if ((int) $adjustment->requested_by === (int) $reviewer->id && ! $reviewer->canBypassApproval()) {
                throw ValidationException::withMessages(['approver' => __('The requester cannot reject the same loyalty adjustment.')]);
            }

            $sourceHash = hash('sha256', json_encode([
                'customer_id' => $adjustment->customer_id,
                'points' => $adjustment->points,
                'reason' => $adjustment->reason,
                'source_reference' => $adjustment->source_reference,
                'lock_version' => (int) $adjustment->lock_version,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $before = $adjustment->only(['status', 'lock_version', 'approval_record_id']);

            app(ApprovalRecordTransition::class)->execute(
                $approval,
                ApprovalState::Rejected,
                'approval_rejected',
                ['approver_id' => $reviewer->id, 'decided_at' => now(), 'decision_note' => $reason],
                expectedSourceVersion: (string) $adjustment->lock_version,
                expectedSourceHash: $sourceHash,
                authorize: static function (ApprovalRecord $record) use ($reviewer): void {
                    Gate::forUser($reviewer)->authorize('decide', $record);
                },
            );

            $adjustment->transition([
                'status' => 'rejected',
                'lock_version' => (int) $adjustment->lock_version + 1,
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'customer_value',
                event: 'loyalty_adjustment_rejected',
                source: $adjustment,
                before: $before,
                after: $adjustment->only(['status', 'lock_version', 'approval_record_id']),
                branchId: $adjustment->branch_id,
                storeId: $adjustment->store_id,
                reasonText: $reason,
                metadata: ['reviewer_id' => $reviewer->id, 'approval_record_id' => $approval->id],
            );
        }, 5);
    }
}
