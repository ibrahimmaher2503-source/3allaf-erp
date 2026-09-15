<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Category;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SaveCategoryAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data, ?int $id = null): Category
    {
        Gate::authorize($id ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        return DB::transaction(function () use ($data, $id): Category {
            $userId = Auth::id();
            $status = (string) ($data['status'] ?? 'active');
            $parentId = $data['parent_id'] !== null && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null;

            $category = $id === null
                ? null
                : Category::query()->lockForUpdate()->findOrFail($id);

            $this->validateParent($parentId, $category?->id, $status);

            if ($category !== null && $category->status === 'active' && $status === 'inactive') {
                if ($category->children()->where('status', 'active')->exists() || $category->products()->where('status', 'active')->exists()) {
                    throw new InvalidArgumentException(__('Cannot deactivate a category with active child categories or products.'));
                }
            }

            $attributes = [
                'code' => strtoupper(trim((string) $data['code'])),
                'name_ar' => trim((string) $data['name_ar']),
                'name_en' => trim((string) ($data['name_en'] ?? '')),
                'parent_id' => $parentId,
                'status' => $status,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'updated_by' => $userId,
            ];

            if ($category === null) {
                $attributes['created_by'] = $userId;
                $category = Category::query()->create($attributes);
                $event = 'create_category';
                $before = null;
            } else {
                $before = $category->only(['code', 'name_ar', 'name_en', 'parent_id', 'status', 'sort_order']);
                $category->update($attributes);
                $event = 'update_category';
            }

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: $event,
                source: $category,
                before: $before,
                after: $category->fresh()->only(['code', 'name_ar', 'name_en', 'parent_id', 'status', 'sort_order']),
            );

            return $category->fresh();
        });
    }

    private function validateParent(?int $parentId, ?int $categoryId, string $status): void
    {
        if ($parentId === null) {
            return;
        }

        if ($categoryId !== null && $parentId === $categoryId) {
            throw new InvalidArgumentException(__('A category cannot be its own parent.'));
        }

        $visited = [];
        $cursor = $parentId;

        while ($cursor !== null) {
            if (isset($visited[$cursor])) {
                throw new InvalidArgumentException(__('The category hierarchy already contains a cycle.'));
            }

            $visited[$cursor] = true;
            $parent = Category::query()->find($cursor);

            if ($parent === null) {
                throw new InvalidArgumentException(__('The selected parent category does not exist.'));
            }

            if ($categoryId !== null && $parent->id === $categoryId) {
                throw new InvalidArgumentException(__('A category cannot be assigned below one of its descendants.'));
            }

            if ($status === 'active' && $parent->status !== 'active') {
                throw new InvalidArgumentException(__('An active category must have an active parent.'));
            }

            $cursor = $parent->parent_id;
        }
    }
}
