<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Data\AttachmentSourceReference;
use App\Modules\Platform\Enums\AttachmentState;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Models\Store;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LinkAttachmentToSource
{
    /** @param Closure(User, Attachment, AttachmentSourceReference): bool $sourceAuthorizer */
    public function execute(Attachment $attachment, AttachmentSourceReference $source, Closure $sourceAuthorizer): Attachment
    {
        /** @var User $user */
        $user = Auth::user() ?? throw new \LogicException('An authenticated linker is required.');

        if (! $source->isLinked() || ! $sourceAuthorizer($user, $attachment, $source)) {
            abort(403);
        }
        $this->validateScope($user, $source);

        return DB::transaction(function () use ($attachment, $source): Attachment {
            $attachment = Attachment::query()->lockForUpdate()->findOrFail($attachment->id);
            if ($attachment->status !== AttachmentState::Temporary || $attachment->source_type !== null) {
                throw ValidationException::withMessages(['attachment' => __('Only an unlinked temporary attachment can be linked.')]);
            }

            $countLimit = config("attachments.count_limits.{$attachment->purpose}");
            if (! is_int($countLimit) || $countLimit < 1) {
                throw ValidationException::withMessages(['purpose' => __('The attachment count policy is not configured.')]);
            }
            $currentCount = Attachment::query()
                ->where('source_type', $source->sourceType)
                ->where('source_id', $source->sourceId)
                ->where('purpose', $attachment->purpose)
                ->whereIn('status', [AttachmentState::Active->value, AttachmentState::Quarantined->value])
                ->lockForUpdate()
                ->count();
            if ($currentCount >= $countLimit) {
                throw ValidationException::withMessages(['attachment' => __('The configured attachment count limit has been reached for this source.')]);
            }

            $before = $this->auditValues($attachment);
            $attachment->mutate([
                'source_type' => $source->sourceType,
                'source_id' => $source->sourceId,
                'branch_id' => $source->branchId,
                'store_id' => $source->storeId,
                'visibility' => $source->visibility,
                'status' => AttachmentState::Active,
                'retention_until' => $source->retentionUntil,
                'expires_at' => $source->expiresAt,
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'attachments',
                event: 'attachment_linked',
                source: $attachment,
                before: $before,
                after: $this->auditValues($attachment),
                branchId: $attachment->branch_id,
                storeId: $attachment->store_id,
                metadata: ['outcome' => 'linked'],
                requestId: $attachment->request_id,
            );

            return $attachment;
        });
    }

    private function validateScope(User $user, AttachmentSourceReference $source): void
    {
        if ($source->branchId !== null && ! $user->canAccessBranch($source->branchId)) {
            abort(403);
        }
        if ($source->storeId !== null && ! $user->canAccessStore($source->storeId)) {
            abort(403);
        }
        if ($source->storeId !== null && $source->branchId !== null
            && Store::query()->whereKey($source->storeId)->value('branch_id') !== $source->branchId) {
            throw ValidationException::withMessages(['scope' => __('The store does not belong to the selected branch.')]);
        }
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
