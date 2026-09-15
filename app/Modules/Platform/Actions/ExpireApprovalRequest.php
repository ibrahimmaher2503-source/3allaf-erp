<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ExpireApprovalRequest
{
    /**
     * System/scheduler calls may run without an authenticated actor. An
     * authenticated invocation must provide its source-specific authorization.
     *
     * @param  callable(ApprovalRecord, User): void|null  $authorize
     */
    public function execute(ApprovalRecord $record, ?Closure $authorize = null): ApprovalRecord
    {
        if ($record->expires_at === null || $record->expires_at->isFuture()) {
            throw ValidationException::withMessages(['expires_at' => __('This approval request has not expired.')]);
        }

        /** @var User|null $actor */
        $actor = Auth::user();
        if ($actor !== null && $authorize === null) {
            throw new AuthorizationException(__('An authenticated expiry action requires explicit authorization.'));
        }

        return app(ApprovalRecordTransition::class)->execute(
            record: $record,
            state: ApprovalState::Expired,
            event: 'approval_expired',
            attributes: ['decision_note' => __('Approval request expired.'), 'decided_at' => now()],
            expectedSourceVersion: $record->source_version,
            expectedSourceHash: $record->source_hash,
            authorize: $actor === null ? null : function (ApprovalRecord $locked) use ($authorize, $actor): void {
                $authorize($locked, $actor);
            },
        );
    }
}
