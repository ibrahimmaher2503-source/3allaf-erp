<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\SupplierGroup;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SaveSupplierGroupAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data, int $companyId, ?int $id = null): SupplierGroup
    {
        Gate::authorize($id === null ? 'suppliers.create' : 'suppliers.edit');

        $nameAr = trim((string) ($data['name_ar'] ?? ''));
        $nameEn = trim((string) ($data['name_en'] ?? ''));
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $sortOrder = max(0, (int) ($data['sort_order'] ?? 0));
        $status = (string) ($data['status'] ?? 'active');
        $parentId = filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null;

        if ($nameAr === '') {
            throw new InvalidArgumentException(__('Supplier group Arabic name is required.'));
        }
        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException(__('The supplier group status is invalid.'));
        }
        if ($id !== null && $parentId === $id) {
            throw new InvalidArgumentException(__('A supplier group cannot be its own parent.'));
        }

        try {
            return DB::transaction(function () use ($companyId, $id, $nameAr, $nameEn, $code, $sortOrder, $status, $parentId): SupplierGroup {
                $actorId = Auth::id();
                $group = $id === null
                    ? null
                    : SupplierGroup::query()->forCompany($companyId)->lockForUpdate()->findOrFail($id);
                $parent = $parentId === null
                    ? null
                    : SupplierGroup::query()->forCompany($companyId)->active()->lockForUpdate()->find($parentId);

                if ($parentId !== null && $parent === null) {
                    throw new InvalidArgumentException(__('The selected parent supplier group is not available in this company.'));
                }
                if ($group !== null && $parent !== null && $this->isDescendantOf($parent, $group->id, $companyId)) {
                    throw new InvalidArgumentException(__('A supplier group cannot be nested beneath one of its own descendants.'));
                }
                if ($group !== null && $status === 'inactive' && $group->children()->where('status', 'active')->exists()) {
                    throw new InvalidArgumentException(__('Move or deactivate child supplier groups before deactivating this parent group.'));
                }

                $duplicate = SupplierGroup::query()->forCompany($companyId)
                    ->when($group !== null, fn ($query) => $query->where('id', '<>', $group->id))
                    ->where(function ($query) use ($nameAr, $nameEn): void {
                        $query->where('name_ar', $nameAr);
                        if ($nameEn !== '') {
                            $query->orWhere('name_en', $nameEn);
                        }
                    })->exists();
                if ($duplicate) {
                    throw new InvalidArgumentException(__('A supplier group with this name already exists in this company.'));
                }

                $attributes = [
                    'company_id' => $companyId,
                    'parent_id' => $parent?->id,
                    'code' => $code,
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn !== '' ? $nameEn : null,
                    'sort_order' => $sortOrder,
                    'status' => $status,
                    'updated_by' => $actorId,
                ];
                if ($group === null) {
                    $group = SupplierGroup::query()->create($attributes + [
                        'created_by' => $actorId,
                        'lock_version' => 1,
                    ]);
                    $event = 'supplier_group_created';
                    $before = null;
                } else {
                    $before = $group->only(['parent_id', 'name_ar', 'name_en', 'status', 'lock_version']);
                    $group->fill($attributes + ['lock_version' => ((int) $group->lock_version) + 1])->save();
                    $event = 'supplier_group_updated';
                }

                $saved = $group->fresh(['parent']);
                app(RecordAuditEvent::class)->execute(
                    category: 'supplier_master_data',
                    event: $event,
                    source: $saved,
                    before: $before,
                    after: $saved->only(['company_id', 'parent_id', 'name_ar', 'name_en', 'status', 'lock_version']),
                    metadata: ['actor_id' => $actorId, 'company_scoped' => true],
                );

                return $saved;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw new InvalidArgumentException(__('A supplier group with this name already exists in this company.'), previous: $exception);
        }
    }

    private function isDescendantOf(SupplierGroup $candidate, int $ancestorId, int $companyId): bool
    {
        $seen = [];
        while ($candidate->parent_id !== null) {
            if (in_array((int) $candidate->id, $seen, true)) {
                return true;
            }
            $seen[] = (int) $candidate->id;
            if ((int) $candidate->parent_id === $ancestorId) {
                return true;
            }
            $candidate = SupplierGroup::query()->forCompany($companyId)->findOrFail($candidate->parent_id);
        }

        return false;
    }
}
