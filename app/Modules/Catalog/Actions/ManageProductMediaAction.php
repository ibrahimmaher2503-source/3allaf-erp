<?php

namespace App\Modules\Catalog\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RevokeAttachment;
use App\Modules\Platform\Actions\StoreAttachment;
use App\Modules\Platform\Data\AttachmentSourceReference;
use App\Modules\Platform\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageProductMediaAction
{
    public function upload(Product $product, UploadedFile $file, string $role): ProductImage
    {
        Gate::authorize('products_categories_brands.edit');

        if (! in_array($role, ['main', 'additional'], true)) {
            throw ValidationException::withMessages(['media' => __('The selected image role is not supported.')]);
        }

        $hash = hash_file('sha256', (string) $file->getRealPath());
        if ($hash === false) {
            throw ValidationException::withMessages(['media' => __('The image hash could not be calculated safely.')]);
        }

        $existing = ProductImage::query()
            ->where('product_id', $product->id)
            ->where('status', 'active')
            ->whereHas('attachment', fn ($query) => $query->where('sha256', $hash)->where('purpose', 'product_image'))
            ->with('attachment')
            ->first();

        if ($existing !== null) {
            if ($role === 'main') {
                return $this->setMain($product, $existing->id);
            }

            return $existing;
        }

        $this->assertCapacity($product, $role);

        $source = new AttachmentSourceReference(
            sourceType: Product::class,
            sourceId: (string) $product->id,
            visibility: 'private',
        );

        $attachment = app(StoreAttachment::class)->execute(
            file: $file,
            purpose: 'product_image',
            source: $source,
            sourceAuthorizer: fn (User $user, AttachmentSourceReference $reference): bool => Gate::forUser($user)->allows('products_categories_brands.edit')
                && $reference->sourceType === Product::class
                && $reference->sourceId === (string) $product->id,
        );

        try {
            return DB::transaction(function () use ($product, $attachment, $role): ProductImage {
                $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
                $activeImages = ProductImage::query()->where('product_id', $lockedProduct->id)->where('status', 'active')->lockForUpdate()->get();

                if ($role === 'additional' && $activeImages->where('role', 'additional')->count() >= 4) {
                    throw ValidationException::withMessages(['media' => __('A product can have at most four additional images.')]);
                }

                if ($role === 'main') {
                    foreach ($activeImages->where('role', 'main') as $oldMain) {
                        $oldMain->update(['status' => 'revoked']);
                        if ($oldMain->attachment !== null) {
                            app(RevokeAttachment::class)->execute(
                                $oldMain->attachment,
                                __('Replaced by a new main product image.'),
                                fn (User $user, Attachment $oldAttachment): bool => Gate::forUser($user)->allows('products_categories_brands.edit')
                                    && $oldAttachment->source_type === Product::class
                                    && $oldAttachment->source_id === (string) $lockedProduct->id,
                            );
                        }
                    }
                }

                $image = ProductImage::query()->create([
                    'product_id' => $lockedProduct->id,
                    'attachment_id' => $attachment->id,
                    'role' => $role,
                    'sort_order' => $role === 'main' ? 0 : ((int) $activeImages->where('role', 'additional')->max('sort_order') + 1),
                    'status' => 'active',
                ]);

                app(RecordAuditEvent::class)->execute(
                    category: 'master_data',
                    event: 'link_product_image',
                    source: $image,
                    after: [
                        'product_id' => $lockedProduct->id,
                        'attachment_id' => $attachment->id,
                        'purpose' => $attachment->purpose,
                        'role' => $role,
                        'sort_order' => $image->sort_order,
                    ],
                );

                return $image->load('attachment');
            });
        } catch (\Throwable $exception) {
            // The attachment foundation preserves the failed upload record as deleted
            // history rather than exposing an unlinked active file.
            app(RevokeAttachment::class)->execute(
                $attachment,
                __('The product image could not be linked to its source.'),
                fn (User $user, Attachment $failedAttachment): bool => Gate::forUser($user)->allows('products_categories_brands.edit')
                    && $failedAttachment->source_type === Product::class
                    && $failedAttachment->source_id === (string) $product->id,
            );

            throw $exception;
        }
    }

    public function setMain(Product $product, int $imageId): ProductImage
    {
        Gate::authorize('products_categories_brands.edit');

        return DB::transaction(function () use ($product, $imageId): ProductImage {
            $target = ProductImage::query()->where('product_id', $product->id)->where('status', 'active')->lockForUpdate()->findOrFail($imageId);
            $oldMain = ProductImage::query()->where('product_id', $product->id)->where('status', 'active')->where('role', 'main')->lockForUpdate()->first();

            if ($oldMain !== null && $oldMain->id !== $target->id) {
                $oldMain->update(['role' => 'additional', 'sort_order' => (int) ProductImage::query()->where('product_id', $product->id)->where('status', 'active')->where('role', 'additional')->max('sort_order') + 1]);
            }

            $target->update(['role' => 'main', 'sort_order' => 0]);

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'set_product_main_image',
                source: $target,
                after: ['product_id' => $product->id, 'attachment_id' => $target->attachment_id, 'role' => 'main'],
            );

            return $target->fresh('attachment');
        });
    }

    public function revoke(Product $product, int $imageId): void
    {
        Gate::authorize('products_categories_brands.edit');

        DB::transaction(function () use ($product, $imageId): void {
            $image = ProductImage::query()->where('product_id', $product->id)->where('status', 'active')->lockForUpdate()->findOrFail($imageId);
            $attachment = $image->attachment;
            $image->update(['status' => 'revoked']);

            if ($attachment !== null) {
                app(RevokeAttachment::class)->execute(
                    $attachment,
                    __('Removed from the product media gallery.'),
                    fn (User $user, Attachment $linkedAttachment): bool => Gate::forUser($user)->allows('products_categories_brands.edit')
                        && $linkedAttachment->source_type === Product::class
                        && $linkedAttachment->source_id === (string) $product->id,
                );
            }

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'revoke_product_image',
                source: $image,
                before: ['attachment_id' => $image->attachment_id, 'status' => 'active'],
                after: ['attachment_id' => $image->attachment_id, 'status' => 'revoked'],
            );
        });
    }

    /** @param array<int, int> $orderedImageIds */
    public function reorder(Product $product, array $orderedImageIds): void
    {
        Gate::authorize('products_categories_brands.edit');

        DB::transaction(function () use ($product, $orderedImageIds): void {
            $images = ProductImage::query()->where('product_id', $product->id)->where('status', 'active')->where('role', 'additional')->lockForUpdate()->get()->keyBy('id');
            $position = 1;

            foreach ($orderedImageIds as $imageId) {
                if (isset($images[(int) $imageId])) {
                    $images[(int) $imageId]->update(['sort_order' => $position++]);
                }
            }

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'reorder_product_images',
                source: $product,
                after: ['product_id' => $product->id, 'ordered_image_ids' => array_values(array_map('intval', $orderedImageIds))],
            );
        });
    }

    private function assertCapacity(Product $product, string $role): void
    {
        $query = ProductImage::query()->where('product_id', $product->id)->where('status', 'active');

        if ($role === 'main' && $query->where('role', 'main')->exists()) {
            return;
        }

        if ($role === 'additional' && $query->where('role', 'additional')->count() >= 4) {
            throw ValidationException::withMessages(['media' => __('A product can have at most four additional images.')]);
        }
    }
}
