<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Enums\AttachmentState;
use App\Modules\Platform\Models\Attachment;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class QuarantineAttachment
{
    /** @param Closure(User, Attachment): bool $sourceAuthorizer */
    public function execute(Attachment $attachment, string $reason, Closure $sourceAuthorizer): Attachment
    {
        $user = Auth::user() ?? throw new \LogicException('An authenticated actor is required.');
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('A quarantine reason is required.')]);
        }

        return DB::transaction(function () use ($attachment, $reason, $sourceAuthorizer, $user): Attachment {
            $attachment = Attachment::query()->lockForUpdate()->findOrFail($attachment->id);
            if ($attachment->status->isTerminal() || $attachment->status === AttachmentState::Quarantined) {
                throw ValidationException::withMessages(['attachment' => __('This attachment cannot be quarantined from its current state.')]);
            }
            if (! $sourceAuthorizer($user, $attachment)) {
                abort(403);
            }

            $before = ['status' => $attachment->status->value];
            $attachment->mutate(['status' => AttachmentState::Quarantined]);
            app(RecordAuditEvent::class)->execute(
                'attachments',
                'attachment_quarantined',
                $attachment,
                $before,
                ['status' => $attachment->status->value],
                $attachment->branch_id,
                $attachment->store_id,
                reasonText: trim($reason),
                metadata: ['purpose' => $attachment->purpose, 'outcome' => 'quarantined'],
                requestId: $attachment->request_id,
            );

            return $attachment;
        });
    }
}
