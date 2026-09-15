<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RejectRequest
{
    public function execute(ApprovalRecord $record, ?string $sourceVersion, string $decisionNote, ?string $sourceHash = null): ApprovalRecord
    {
        if (trim($decisionNote) === '') {
            throw ValidationException::withMessages(['decision_note' => __('A rejection reason is required.')]);
        }

        /** @var User $approver */
        $approver = Auth::user() ?? throw new \LogicException('An authenticated approver is required.');

        return app(ApprovalRecordTransition::class)->execute(
            record: $record,
            state: ApprovalState::Rejected,
            event: 'approval_rejected',
            attributes: ['approver_id' => $approver->id, 'decision_note' => trim($decisionNote), 'decided_at' => now()],
            expectedSourceVersion: $sourceVersion,
            expectedSourceHash: $sourceHash,
            authorize: function (ApprovalRecord $locked) use ($approver): mixed {
                if ($locked->requester_id === $approver->id && ! $approver->canBypassApproval()) {
                    throw ValidationException::withMessages(['approver' => __('A requester cannot reject their own request.')]);
                }

                return Gate::forUser($approver)->authorize('decide', $locked);
            },
        );
    }
}
