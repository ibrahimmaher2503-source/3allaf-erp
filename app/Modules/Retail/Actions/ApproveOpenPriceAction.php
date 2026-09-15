<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\ApprovalRecordTransition;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ApproveOpenPriceAction
{
    public function execute(User $approver, ApprovalRecord $record): void
    {
        Gate::forUser($approver)->authorize('pos_sales.open_price_approve');

        if ($record->source_type !== 'pos_open_price' || $record->requested_action !== 'approve_open_price') {
            throw ValidationException::withMessages(['approval' => __('This is not a POS open-price approval request.')]);
        }
        if ($record->requester_id === $approver->id && ! $approver->canBypassApproval()) {
            throw ValidationException::withMessages(['approval' => __('The cashier who requested the open price cannot approve it.')]);
        }
        if ($record->expires_at !== null && $record->expires_at->isPast()) {
            throw ValidationException::withMessages(['approval' => __('This open-price approval has expired. Request a fresh decision.')]);
        }

        app(ApprovalRecordTransition::class)->execute(
            record: $record,
            state: ApprovalState::Approved,
            event: 'pos_open_price_approved',
            attributes: ['approver_id' => $approver->id, 'decided_at' => now(), 'decision_note' => __('Open price approved by an independent manager.')],
            expectedSourceVersion: $record->source_version,
            expectedSourceHash: $record->source_hash,
            authorize: fn (ApprovalRecord $current): mixed => Gate::forUser($approver)->authorize('pos_sales.open_price_approve'),
        );
    }
}
