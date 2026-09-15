<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Enums\AttachmentState;
use App\Modules\Platform\Models\Attachment;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevokeAttachment
{
    /** @param Closure(User, Attachment): bool $sourceAuthorizer */
    public function execute(Attachment $attachment, string $reason, Closure $sourceAuthorizer): Attachment
    {
        /** @var User $user */
        $user = Auth::user() ?? throw new \LogicException('An authenticated revoker is required.');
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('A reason is required to revoke an attachment.')]);
        }

        return DB::transaction(function () use ($attachment, $user, $reason, $sourceAuthorizer): Attachment {
            $attachment = Attachment::query()->lockForUpdate()->findOrFail($attachment->id);
            if ($attachment->status->isTerminal()) {
                throw ValidationException::withMessages(['attachment' => __('A terminal attachment cannot be revoked again.')]);
            }
            if (! $this->hasScope($user, $attachment) || ! $sourceAuthorizer($user, $attachment)) {
                abort(403);
            }

            $before = $this->auditValues($attachment);
            $attachment->mutate(['status' => AttachmentState::Deleted, 'deleted_at' => now()]);

            app(RecordAuditEvent::class)->execute(
                category: 'attachments',
                event: 'attachment_revoked',
                source: $attachment,
                before: $before,
                after: $this->auditValues($attachment),
                branchId: $attachment->branch_id,
                storeId: $attachment->store_id,
                reasonText: trim($reason),
                metadata: ['outcome' => 'revoked'],
                requestId: $attachment->request_id,
            );

            return $attachment;
        });
    }

    private function hasScope(User $user, Attachment $attachment): bool
    {
        return ($attachment->branch_id === null || $user->canAccessBranch((int) $attachment->branch_id))
            && ($attachment->store_id === null || $user->canAccessStore((int) $attachment->store_id));
    }

    /** @return array<string, mixed> */
    private function auditValues(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'source_type' => $attachment->source_type,
            'source_id' => $attachment->source_id,
            'purpose' => $attachment->purpose,
            'status' => $attachment->status->value,
            'branch_id' => $attachment->branch_id,
            'store_id' => $attachment->store_id,
            'sha256_prefix' => substr($attachment->sha256, 0, 12),
        ];
    }
}
