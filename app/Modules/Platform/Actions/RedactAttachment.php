<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Enums\AttachmentState;
use App\Modules\Platform\Models\Attachment;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class RedactAttachment
{
    /** @param Closure(User, Attachment): bool $sourceAuthorizer */
    public function execute(Attachment $attachment, string $reason, Closure $sourceAuthorizer): Attachment
    {
        $user = Auth::user() ?? throw new \LogicException('An authenticated actor is required.');
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('A redaction reason is required.')]);
        }

        $quarantinePath = '.redaction-quarantine/'.Str::uuid();
        $originalPath = null;
        $diskName = null;

        try {
            return DB::transaction(function () use ($attachment, $reason, $sourceAuthorizer, $user, $quarantinePath, &$originalPath, &$diskName): Attachment {
                $attachment = Attachment::query()->lockForUpdate()->findOrFail($attachment->id);
                if ($attachment->status->isTerminal()) {
                    throw ValidationException::withMessages(['attachment' => __('A terminal attachment cannot be redacted again.')]);
                }
                if (! $sourceAuthorizer($user, $attachment)) {
                    abort(403);
                }

                $originalPath = $attachment->storage_path;
                $diskName = $attachment->storage_disk;
                $disk = Storage::disk($diskName);
                if (! $disk->move($originalPath, $quarantinePath)) {
                    throw ValidationException::withMessages(['attachment' => __('The protected file could not be quarantined for redaction.')]);
                }

                $before = ['status' => $attachment->status->value, 'sha256_prefix' => substr($attachment->sha256, 0, 12)];
                $attachment->mutate(['status' => AttachmentState::Redacted, 'deleted_at' => now()]);
                app(RecordAuditEvent::class)->execute(
                    'attachments',
                    'attachment_redacted',
                    $attachment,
                    $before,
                    ['status' => $attachment->status->value],
                    $attachment->branch_id,
                    $attachment->store_id,
                    reasonText: trim($reason),
                    metadata: ['purpose' => $attachment->purpose, 'outcome' => 'content_quarantined_for_deletion'],
                    requestId: $attachment->request_id,
                );

                DB::afterCommit(static function () use ($diskName, $quarantinePath): void {
                    if ($diskName !== null && ! Storage::disk($diskName)->delete($quarantinePath)) {
                        report(new \RuntimeException('A redacted attachment quarantine blob could not be deleted.'));
                    }
                });

                return $attachment;
            });
        } catch (Throwable $exception) {
            if ($diskName !== null && $originalPath !== null) {
                $disk = Storage::disk($diskName);
                if ($disk->exists($quarantinePath) && ! $disk->exists($originalPath)) {
                    $disk->move($quarantinePath, $originalPath);
                }
            }

            throw $exception;
        }
    }
}
