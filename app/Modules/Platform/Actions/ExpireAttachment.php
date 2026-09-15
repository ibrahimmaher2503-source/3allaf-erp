<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Enums\AttachmentState;
use App\Modules\Platform\Models\Attachment;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpireAttachment
{
    /**
     * Internal retention action; callers must invoke it from an approved scheduler/maintenance path.
     */
    /**
     * System/scheduler calls may run without an authenticated actor. An
     * authenticated invocation must provide its source-specific authorization.
     *
     * @param  callable(Attachment, User): void|null  $authorize
     */
    public function execute(Attachment $attachment, ?Closure $authorize = null): Attachment
    {
        /** @var User|null $actor */
        $actor = Auth::user();
        if ($actor !== null && $authorize === null) {
            throw new AuthorizationException(__('An authenticated expiry action requires explicit authorization.'));
        }

        return DB::transaction(function () use ($attachment, $actor, $authorize): Attachment {
            $attachment = Attachment::query()->lockForUpdate()->findOrFail($attachment->id);
            if ($attachment->expires_at === null || $attachment->expires_at->isFuture()) {
                throw ValidationException::withMessages(['expires_at' => __('This attachment has not expired.')]);
            }

            if ($attachment->status->isTerminal()) {
                throw ValidationException::withMessages(['attachment' => __('A terminal attachment cannot expire again.')]);
            }

            if ($actor !== null) {
                $authorize($attachment, $actor);
            }

            $before = $this->auditValues($attachment);
            $attachment->mutate(['status' => AttachmentState::Expired]);

            app(RecordAuditEvent::class)->execute(
                category: 'attachments',
                event: 'attachment_expired',
                source: $attachment,
                before: $before,
                after: $this->auditValues($attachment),
                branchId: $attachment->branch_id,
                storeId: $attachment->store_id,
                metadata: ['outcome' => 'expired'],
                requestId: $attachment->request_id,
            );

            return $attachment;
        });
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
