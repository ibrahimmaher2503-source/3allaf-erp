<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Models\Attachment;
use Closure;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliverAttachment
{
    /** @param Closure(User, Attachment): bool $sourceAuthorizer */
    public function execute(Attachment $attachment, Closure $sourceAuthorizer): StreamedResponse
    {
        app(AuthorizeAttachmentAccess::class)->execute($attachment, $sourceAuthorizer);

        $diskConfig = (array) config("filesystems.disks.{$attachment->storage_disk}", []);
        if (($diskConfig['visibility'] ?? null) === 'public') {
            abort(403);
        }

        $disk = Storage::disk($attachment->storage_disk);
        if (! $disk->exists($attachment->storage_path)) {
            abort(404);
        }

        $stream = $disk->readStream($attachment->storage_path);
        if (! is_resource($stream)) {
            abort(404);
        }

        app(RecordAuditEvent::class)->execute(
            category: 'attachments',
            event: 'attachment_accessed',
            source: $attachment,
            branchId: $attachment->branch_id,
            storeId: $attachment->store_id,
            metadata: [
                'purpose' => $attachment->purpose,
                'mime_type' => $attachment->detected_mime_type,
                'size_bytes' => $attachment->size_bytes,
                'sha256_prefix' => substr($attachment->sha256, 0, 12),
                'outcome' => 'delivered',
            ],
            requestId: $attachment->request_id,
        );

        $safeFilename = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename($attachment->original_filename)) ?: 'attachment';
        $inline = in_array($attachment->detected_mime_type, config('attachments.inline_mimes', []), true);
        $disposition = HeaderUtils::makeDisposition(
            $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $safeFilename,
        );

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $attachment->detected_mime_type,
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
