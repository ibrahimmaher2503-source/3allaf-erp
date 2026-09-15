<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Models\Attachment;
use Closure;
use Illuminate\Support\Facades\Auth;

class AuthorizeAttachmentAccess
{
    /** @param Closure(User, Attachment): bool $sourceAuthorizer */
    public function execute(Attachment $attachment, Closure $sourceAuthorizer): User
    {
        /** @var User $user */
        $user = Auth::user() ?? abort(403);

        if (! $attachment->status->isDeliverable()
            || ($attachment->expires_at !== null && $attachment->expires_at->isPast())
            || $attachment->source_type === null
            || $attachment->source_id === null
            || ! $this->hasScope($user, $attachment)
            || ! $sourceAuthorizer($user, $attachment)) {
            abort(403);
        }

        return $user;
    }

    private function hasScope(User $user, Attachment $attachment): bool
    {
        if ($attachment->branch_id !== null && ! $user->canAccessBranch((int) $attachment->branch_id)) {
            return false;
        }

        if ($attachment->store_id !== null && ! $user->canAccessStore((int) $attachment->store_id)) {
            return false;
        }

        return true;
    }
}
