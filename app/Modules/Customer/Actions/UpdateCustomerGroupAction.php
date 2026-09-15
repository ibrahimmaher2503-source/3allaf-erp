<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class UpdateCustomerGroupAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, CustomerGroup $group, Store $store, array $data): CustomerGroup
    {
        Gate::forUser($actor)->authorize('customers.edit');
        abort_unless($store->status === 'active' && $actor->canAccessStore((int) $store->id), 403);
        abort_unless((int) $group->company_id === (int) $store->company_id, 404);

        $nameAr = trim((string) ($data['name_ar'] ?? $group->name_ar));
        $nameEn = trim((string) ($data['name_en'] ?? $group->name_en));
        $code = strtoupper(trim((string) ($data['code'] ?? $group->code ?? '')));
        $sortOrder = max(0, (int) ($data['sort_order'] ?? $group->sort_order ?? 0));
        $status = (string) ($data['status'] ?? $group->status);
        if ($nameAr === '' || $nameEn === '') {
            throw new InvalidArgumentException(__('Customer group Arabic and English names are required.'));
        }
        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException(__('The customer group status is invalid.'));
        }

        $parentId = filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null;
        if ($parentId === (int) $group->id) {
            throw new InvalidArgumentException(__('A customer group cannot be its own parent.'));
        }

        try {
            return DB::transaction(function () use ($actor, $group, $store, $nameAr, $nameEn, $code, $sortOrder, $status, $parentId): CustomerGroup {
                $locked = CustomerGroup::query()->forCompany((int) $store->company_id)->lockForUpdate()->findOrFail($group->id);
                $parent = $parentId === null
                    ? null
                    : CustomerGroup::query()->forCompany((int) $store->company_id)->active()->lockForUpdate()->find($parentId);
                if ($parentId !== null && $parent === null) {
                    throw new InvalidArgumentException(__('The selected parent group is not available in this company.'));
                }
                if ($parent !== null && $this->isDescendantOf($parent, $locked->id)) {
                    throw new InvalidArgumentException(__('A customer group cannot be nested beneath one of its own descendants.'));
                }
                if ($status === 'inactive' && $locked->children()->where('status', 'active')->exists()) {
                    throw new InvalidArgumentException(__('Move or deactivate child groups before deactivating this parent group.'));
                }
                if (CustomerGroup::query()->forCompany((int) $store->company_id)
                    ->where('id', '<>', $locked->id)
                    ->where(function ($query) use ($nameAr, $nameEn): void {
                        $query->where('name_ar', $nameAr)->orWhere('name_en', $nameEn);
                    })->exists()) {
                    throw new InvalidArgumentException(__('A customer group with this name already exists in this company.'));
                }

                $before = $locked->only(['parent_id', 'name_ar', 'name_en', 'status', 'lock_version']);
                $locked->fill([
                    'parent_id' => $parent?->id,
                    'code' => $code,
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'sort_order' => $sortOrder,
                    'status' => $status,
                    'updated_by' => $actor->id,
                    'lock_version' => ((int) $locked->lock_version) + 1,
                ])->save();

                $saved = $locked->fresh(['parent']);
                app(RecordAuditEvent::class)->execute(
                    category: 'customer_master_data',
                    event: 'customer_group_updated',
                    source: $saved,
                    before: $before,
                    after: $saved->only(['parent_id', 'name_ar', 'name_en', 'status', 'lock_version']),
                    branchId: (int) $store->branch_id,
                    storeId: (int) $store->id,
                    metadata: ['actor_id' => $actor->id, 'company_scoped' => true],
                );

                return $saved;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw new InvalidArgumentException(__('A customer group with this name already exists in this company.'), previous: $exception);
        }
    }

    private function isDescendantOf(CustomerGroup $candidate, int $ancestorId): bool
    {
        $seen = [];
        while ($candidate->parent_id !== null) {
            if (in_array((int) $candidate->parent_id, $seen, true)) {
                return true;
            }
            $seen[] = (int) $candidate->id;
            if ((int) $candidate->parent_id === $ancestorId) {
                return true;
            }
            $candidate = CustomerGroup::query()->findOrFail($candidate->parent_id);
        }

        return false;
    }
}
