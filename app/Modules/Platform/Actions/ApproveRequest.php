<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApproveRequest
{
    public function execute(ApprovalRecord $record, ?string $sourceVersion, ?string $sourceHash = null, ?string $decisionNote = null): ApprovalRecord
    {
        /** @var User $approver */
        $approver = Auth::user() ?? throw new \LogicException('An authenticated approver is required.');

        return app(ApprovalRecordTransition::class)->execute(
            record: $record,
            state: ApprovalState::Approved,
            event: 'approval_approved',
            attributes: ['approver_id' => $approver->id, 'decision_note' => $decisionNote, 'decided_at' => now()],
            expectedSourceVersion: $sourceVersion,
            expectedSourceHash: $sourceHash,
            authorize: function (ApprovalRecord $locked) use ($approver): mixed {
                $this->assertSeparation($locked, $approver);

                return Gate::forUser($approver)->authorize('decide', $locked);
            },
        );
    }

    private function assertSeparation(ApprovalRecord $record, User $approver): void
    {
        if ($record->requester_id === $approver->id && ! $approver->canBypassApproval()) {
            throw ValidationException::withMessages(['approver' => __('A requester cannot approve their own request.')]);
        }
    }
}
