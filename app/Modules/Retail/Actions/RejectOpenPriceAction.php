<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\ApprovalRecordTransition;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RejectOpenPriceAction
{
    public function execute(User $approver, ApprovalRecord $record, string $reason): void
    {
        Gate::forUser($approver)->authorize('pos_sales.open_price_approve');
        if ($record->source_type !== 'pos_open_price' || $record->requested_action !== 'approve_open_price') {
            throw ValidationException::withMessages(['approval' => __('This is not a POS open-price approval request.')]);
        }
        if ($record->requester_id === $approver->id && ! $approver->canBypassApproval()) {
            throw ValidationException::withMessages(['approval' => __('The cashier who requested the open price cannot reject it.')]);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('A rejection reason is required.')]);
        }

        app(ApprovalRecordTransition::class)->execute(
            record: $record,
            state: ApprovalState::Rejected,
            event: 'pos_open_price_rejected',
            attributes: ['approver_id' => $approver->id, 'decided_at' => now(), 'decision_note' => $reason],
            expectedSourceVersion: $record->source_version,
            expectedSourceHash: $record->source_hash,
            authorize: fn (ApprovalRecord $current): mixed => Gate::forUser($approver)->authorize('pos_sales.open_price_approve'),
        );
    }
}
