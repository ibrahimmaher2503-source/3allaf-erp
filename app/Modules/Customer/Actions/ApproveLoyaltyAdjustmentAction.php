<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\ApprovalRecordTransition;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ApproveLoyaltyAdjustmentAction
{
    public function __construct(private readonly PostLoyaltyAdjustmentAction $poster) {}

    public function execute(User $approver, ApprovalRecord $approval, Store $store): void
    {
        Gate::forUser($approver)->authorize('decide', $approval);
        abort_unless($approval->decisionPermission() === 'loyalty.approve', 403);

        DB::transaction(function () use ($approver, $approval, $store): void {
            $approval = ApprovalRecord::query()->lockForUpdate()->findOrFail($approval->id);
            Gate::forUser($approver)->authorize('decide', $approval);
            $adjustment = \App\Modules\Customer\Models\LoyaltyAdjustment::query()->lockForUpdate()->findOrFail((int) $approval->source_id);
            if ((int) $adjustment->requested_by === (int) $approver->id && ! $approver->canBypassApproval()) {
                throw ValidationException::withMessages(['approval' => __('The requester cannot approve the same loyalty adjustment.')]);
            }

            $hash = hash('sha256', json_encode([
                'customer_id' => $adjustment->customer_id,
                'points' => $adjustment->points,
                'reason' => $adjustment->reason,
                'source_reference' => $adjustment->source_reference,
                'lock_version' => (int) $adjustment->lock_version,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            app(ApprovalRecordTransition::class)->execute(
                $approval,
                ApprovalState::Approved,
                'approval_approved',
                ['approver_id' => $approver->id, 'decided_at' => now(), 'decision_note' => __('Loyalty adjustment approved.')],
                expectedSourceVersion: (string) $adjustment->lock_version,
                expectedSourceHash: $hash,
                authorize: static function (ApprovalRecord $record) use ($approver): void {
                    Gate::forUser($approver)->authorize('decide', $record);
                },
            );
            $this->poster->execute($approver, $adjustment, $store);
        }, 5);
    }
}
