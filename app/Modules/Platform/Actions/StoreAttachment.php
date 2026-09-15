<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Data\AttachmentSourceReference;
use App\Modules\Platform\Data\ValidatedAttachment;
use App\Modules\Platform\Enums\AttachmentState;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Models\Store;
use Closure;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreAttachment
{
    /** @param UploadedFile|File $file @param Closure(User, AttachmentSourceReference): bool|null $sourceAuthorizer */
    public function execute(UploadedFile|File $file, string $purpose, ?AttachmentSourceReference $source = null, ?Closure $sourceAuthorizer = null): Attachment
    {
        /** @var User $user */
        $user = Auth::user() ?? throw new \LogicException('An authenticated uploader is required.');

        try {
            $validated = app(ValidateAttachment::class)->execute($file, $purpose);
        } catch (ValidationException $exception) {
            app(RecordAuditEvent::class)->execute(
                category: 'attachments',
                event: 'attachment_validation_rejected',
                metadata: ['purpose' => $purpose, 'outcome' => 'rejected', 'validation_fields' => array_keys($exception->errors())],
            );

            throw $exception;
        }

        $source ??= new AttachmentSourceReference;
        $this->validateSource($source, $sourceAuthorizer, $user);
        $this->validateScope($user, $source);

        $diskName = (string) config('attachments.disk', 'local');
        $diskConfig = (array) config("filesystems.disks.{$diskName}", []);
        if (($diskConfig['visibility'] ?? null) === 'public') {
            throw new \LogicException('Protected attachments cannot use a public disk.');
        }

        $storageFilename = (string) Str::uuid().'.'.$validated->extension;
        $relativePath = 'attachments/'.$purpose.'/'.now()->format('Y/m').'/'.$storageFilename;
        $disk = Storage::disk($diskName);
        $stored = false;

        try {
            $stored = $disk->putFileAs(dirname($relativePath), $file, $storageFilename);
            if ($stored === false) {
                throw new \RuntimeException('The private attachment could not be stored.');
            }

            return DB::transaction(function () use ($validated, $source, $user, $diskName, $relativePath, $storageFilename, $purpose): Attachment {
                if ($source->isLinked()) {
                    $countLimit = config("attachments.count_limits.{$purpose}");
                    if (! is_int($countLimit) || $countLimit < 1) {
                        throw ValidationException::withMessages(['purpose' => __('The attachment count policy is not configured.')]);
                    }

                    $currentCount = Attachment::query()
                        ->where('source_type', $source->sourceType)
                        ->where('source_id', $source->sourceId)
                        ->where('purpose', $purpose)
                        ->whereIn('status', [AttachmentState::Temporary->value, AttachmentState::Active->value, AttachmentState::Quarantined->value])
                        ->lockForUpdate()
                        ->count();
                    if ($currentCount >= $countLimit) {
                        throw ValidationException::withMessages(['file' => __('The configured attachment count limit has been reached for this source.')]);
                    }
                }

                $attachment = Attachment::create([
                    'id' => (string) Str::uuid(),
                    'source_type' => $source->sourceType,
                    'source_id' => $source->sourceId,
                    'purpose' => $purpose,
                    'original_filename' => $validated->originalFilename,
                    'storage_filename' => $storageFilename,
                    'storage_disk' => $diskName,
                    'storage_path' => $relativePath,
                    'mime_type' => $validated->declaredMimeType,
                    'detected_mime_type' => $validated->detectedMimeType,
                    'extension' => $validated->extension,
                    'size_bytes' => $validated->sizeBytes,
                    'sha256' => $validated->sha256,
                    'uploaded_by' => $user->id,
                    'branch_id' => $source->branchId,
                    'store_id' => $source->storeId,
                    'visibility' => $source->visibility,
                    'status' => $source->isLinked() ? AttachmentState::Active : AttachmentState::Temporary,
                    'request_id' => Context::get('request_id') ?? (string) Str::uuid(),
                    'metadata' => $this->metadata($validated),
                    'retention_until' => $source->retentionUntil,
                    'expires_at' => $source->expiresAt,
                ]);

                app(RecordAuditEvent::class)->execute(
                    category: 'attachments',
                    event: 'attachment_stored',
                    source: $attachment,
                    after: $this->auditValues($attachment),
                    branchId: $attachment->branch_id,
                    storeId: $attachment->store_id,
                    metadata: ['outcome' => 'stored'],
                    requestId: $attachment->request_id,
                );

                return $attachment;
            });
        } catch (Throwable $exception) {
            if ($stored !== false) {
                $disk->delete($relativePath);
            }

            throw $exception;
        }
    }

    private function validateSource(AttachmentSourceReference $source, ?Closure $sourceAuthorizer, User $user): void
    {
        if (($source->sourceType === null) !== ($source->sourceId === null)) {
            throw ValidationException::withMessages(['source' => __('Both source type and source identifier are required when linking an attachment.')]);
        }

        if ($source->isLinked() && ($sourceAuthorizer === null || ! $sourceAuthorizer($user, $source))) {
            abort(403);
        }

        if ($source->visibility === '') {
            throw ValidationException::withMessages(['visibility' => __('Attachment visibility is required.')]);
        }
    }

    private function validateScope(User $user, AttachmentSourceReference $source): void
    {
        $hasScope = ($source->branchId !== null && $user->canAccessBranch($source->branchId))
            || ($source->storeId !== null && $user->canAccessStore($source->storeId));
        if (($source->branchId !== null || $source->storeId !== null) && ! $hasScope) {
            abort(403);
        }

        if ($source->storeId !== null && $source->branchId !== null
            && Store::query()->whereKey($source->storeId)->value('branch_id') !== $source->branchId) {
            throw ValidationException::withMessages(['scope' => __('The store does not belong to the selected branch.')]);
        }
    }

    /** @return array<string, mixed>|null */
    private function metadata(ValidatedAttachment $validated): ?array
    {
        return $validated->dimensions === [] ? null : ['dimensions' => $validated->dimensions];
    }

    /** @return array<string, mixed> */
    private function auditValues(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'source_type' => $attachment->source_type,
            'source_id' => $attachment->source_id,
            'purpose' => $attachment->purpose,
            'mime_type' => $attachment->detected_mime_type,
            'size_bytes' => $attachment->size_bytes,
            'sha256_prefix' => substr($attachment->sha256, 0, 12),
            'status' => $attachment->status->value,
            'branch_id' => $attachment->branch_id,
            'store_id' => $attachment->store_id,
        ];
    }
}
