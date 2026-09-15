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

final class CreateCustomerGroupAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, Store $store, array $data): CustomerGroup
    {
        Gate::forUser($actor)->authorize('customers.edit');
        abort_unless($store->status === 'active' && $actor->canAccessStore((int) $store->id), 403);

        $nameAr = trim((string) ($data['name_ar'] ?? ''));
        $nameEn = trim((string) ($data['name_en'] ?? ''));
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $sortOrder = max(0, (int) ($data['sort_order'] ?? 0));
        if ($nameAr === '' || $nameEn === '') {
            throw new InvalidArgumentException(__('Customer group Arabic and English names are required.'));
        }

        $parentId = filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null;

        try {
            return DB::transaction(function () use ($actor, $store, $nameAr, $nameEn, $code, $sortOrder, $parentId): CustomerGroup {
                $parent = $parentId === null
                    ? null
                    : CustomerGroup::query()->forCompany((int) $store->company_id)->active()->lockForUpdate()->find($parentId);
                if ($parentId !== null && $parent === null) {
                    throw new InvalidArgumentException(__('The selected parent group is not available in this company.'));
                }

                if (CustomerGroup::query()->forCompany((int) $store->company_id)->where(function ($query) use ($nameAr, $nameEn): void {
                    $query->where('name_ar', $nameAr)->orWhere('name_en', $nameEn);
                })->exists()) {
                    throw new InvalidArgumentException(__('A customer group with this name already exists in this company.'));
                }

                $group = CustomerGroup::query()->create([
                    'company_id' => $store->company_id,
                    'parent_id' => $parent?->id,
                    'code' => $code,
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'sort_order' => $sortOrder,
                    'status' => 'active',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'lock_version' => 1,
                ]);

                app(RecordAuditEvent::class)->execute(
                    category: 'customer_master_data',
                    event: 'customer_group_created',
                    source: $group,
                    after: $group->only(['company_id', 'parent_id', 'name_ar', 'name_en', 'status', 'lock_version']),
                    branchId: (int) $store->branch_id,
                    storeId: (int) $store->id,
                    metadata: ['actor_id' => $actor->id, 'company_scoped' => true],
                );

                return $group->fresh(['parent']);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw new InvalidArgumentException(__('A customer group with this name already exists in this company.'), previous: $exception);
        }
    }
}
