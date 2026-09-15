<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class WithdrawApprovalRequest
{
    public function execute(ApprovalRecord $record, ?string $sourceVersion, ?string $sourceHash = null, ?string $decisionNote = null): ApprovalRecord
    {
        /** @var User $requester */
        $requester = Auth::user() ?? throw new \LogicException('An authenticated requester is required.');

        return app(ApprovalRecordTransition::class)->execute(
            record: $record,
            state: ApprovalState::Withdrawn,
            event: 'approval_withdrawn',
            attributes: ['decision_note' => $decisionNote, 'withdrawn_at' => now()],
            expectedSourceVersion: $sourceVersion,
            expectedSourceHash: $sourceHash,
            authorize: fn (ApprovalRecord $locked): mixed => Gate::forUser($requester)->authorize('withdraw', $locked),
        );
    }
}
