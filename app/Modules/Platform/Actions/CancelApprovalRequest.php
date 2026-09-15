<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CancelApprovalRequest
{
    public function execute(ApprovalRecord $record, ?string $sourceVersion, string $decisionNote, ?string $sourceHash = null): ApprovalRecord
    {
        if (trim($decisionNote) === '') {
            throw ValidationException::withMessages(['decision_note' => __('A cancellation reason is required.')]);
        }

        /** @var User $actor */
        $actor = Auth::user() ?? throw new \LogicException('An authenticated controller is required.');

        return app(ApprovalRecordTransition::class)->execute(
            record: $record,
            state: ApprovalState::Cancelled,
            event: 'approval_cancelled',
            attributes: ['approver_id' => $actor->id, 'decision_note' => trim($decisionNote), 'cancelled_at' => now()],
            expectedSourceVersion: $sourceVersion,
            expectedSourceHash: $sourceHash,
            authorize: fn (ApprovalRecord $locked): mixed => Gate::forUser($actor)->authorize('cancel', $locked),
        );
    }
}
