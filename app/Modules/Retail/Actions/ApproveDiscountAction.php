<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\ApprovalRecordTransition;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ApproveDiscountAction
{
    public function execute(User $approver, ApprovalRecord $record): void
    {
        Gate::forUser($approver)->authorize('pos_sales.discount_approve');
        $this->assertRecord($approver, $record);

        app(ApprovalRecordTransition::class)->execute(
            record: $record,
            state: ApprovalState::Approved,
            event: 'pos_discount_approved',
            attributes: ['approver_id' => $approver->id, 'decided_at' => now(), 'decision_note' => __('Discount approved by an independent manager.')],
            expectedSourceVersion: $record->source_version,
            expectedSourceHash: $record->source_hash,
            authorize: fn (ApprovalRecord $current): mixed => Gate::forUser($approver)->authorize('pos_sales.discount_approve'),
        );
    }

    private function assertRecord(User $approver, ApprovalRecord $record): void
    {
        if ($record->source_type !== 'pos_discount' || $record->requested_action !== 'approve_discount') {
            throw ValidationException::withMessages(['approval' => __('This is not a POS discount approval request.')]);
        }
        if ((int) $record->requester_id === (int) $approver->id && ! $approver->canBypassApproval()) {
            throw ValidationException::withMessages(['approval' => __('The cashier who requested the discount cannot approve it.')]);
        }
        if ($record->expires_at !== null && $record->expires_at->isPast()) {
            throw ValidationException::withMessages(['approval' => __('This discount approval has expired. Request a fresh decision.')]);
        }
    }
}
