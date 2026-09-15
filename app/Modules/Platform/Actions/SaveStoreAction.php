<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class SaveStoreAction
{
    /**
     * Allowed location types.
     */
    public const ALLOWED_TYPES = [
        'selling',
        'warehouse',
        'party',
        'damaged',
        'transit',
    ];

    /**
     * Locked store-dependency policy for archive and hard-delete operations.
     * Every operational or history-bearing store reference must be listed here
     * before those irreversible/history-bearing paths can proceed. Approval
     * records are intentionally excluded from archive checks because the
     * archive request itself is a preserved approval-history reference; hard
     * delete includes them.
     *
     * @var array<int, array{table: string, column: string, label: string}>
     */
    private const DEPENDENCY_POLICY = [
        ['table' => 'branch_selling_stores', 'column' => 'store_id', 'label' => 'POS branch mappings'],
        ['table' => 'cash_drawers', 'column' => 'store_id', 'label' => 'cash drawers'],
        ['table' => 'user_store_scopes', 'column' => 'store_id', 'label' => 'user store scopes'],
        ['table' => 'attachments', 'column' => 'store_id', 'label' => 'attachments'],
        ['table' => 'approval_records', 'column' => 'store_id', 'label' => 'approval history'],
        ['table' => 'purchase_orders', 'column' => 'store_id', 'label' => 'purchase orders'],
        ['table' => 'purchase_invoices', 'column' => 'store_id', 'label' => 'purchase invoices'],
        ['table' => 'purchase_returns', 'column' => 'store_id', 'label' => 'purchase returns'],
        ['table' => 'stock_movements', 'column' => 'store_id', 'label' => 'stock movements'],
        ['table' => 'stock_balances', 'column' => 'store_id', 'label' => 'stock balances'],
        ['table' => 'stock_period_snapshots', 'column' => 'store_id', 'label' => 'stock history snapshots'],
        ['table' => 'price_lines', 'column' => 'store_id', 'label' => 'price lines'],
        ['table' => 'label_queues', 'column' => 'store_id', 'label' => 'label queues'],
        ['table' => 'stock_transfers', 'column' => 'source_store_id', 'label' => 'outbound stock transfers'],
        ['table' => 'stock_transfers', 'column' => 'destination_store_id', 'label' => 'inbound stock transfers'],
        ['table' => 'inventory_adjustments', 'column' => 'store_id', 'label' => 'inventory adjustments'],
        ['table' => 'stock_counts', 'column' => 'store_id', 'label' => 'stock counts'],
        ['table' => 'pos_shifts', 'column' => 'store_id', 'label' => 'POS shifts'],
        ['table' => 'sales', 'column' => 'store_id', 'label' => 'sales'],
        ['table' => 'cash_movements', 'column' => 'store_id', 'label' => 'cash movements'],
        ['table' => 'customer_scopes', 'column' => 'store_id', 'label' => 'customer scopes'],
        ['table' => 'customer_merge_events', 'column' => 'store_id', 'label' => 'customer merge history'],
        ['table' => 'customer_consents', 'column' => 'store_id', 'label' => 'customer consent history'],
        ['table' => 'loyalty_adjustments', 'column' => 'store_id', 'label' => 'loyalty adjustments'],
        ['table' => 'loyalty_ledger', 'column' => 'store_id', 'label' => 'loyalty ledger history'],
        ['table' => 'gift_receipts', 'column' => 'store_id', 'label' => 'gift receipts'],
        ['table' => 'gift_cards', 'column' => 'store_id', 'label' => 'gift cards'],
        ['table' => 'retail_returns', 'column' => 'store_id', 'label' => 'retail returns'],
        ['table' => 'rental_assets', 'column' => 'store_id', 'label' => 'rental assets'],
        ['table' => 'asset_reservations', 'column' => 'store_id', 'label' => 'asset reservations'],
        ['table' => 'asset_checkouts', 'column' => 'store_id', 'label' => 'asset checkouts'],
        ['table' => 'asset_returns', 'column' => 'store_id', 'label' => 'asset returns'],
        ['table' => 'asset_events', 'column' => 'store_id', 'label' => 'asset event history'],
        ['table' => 'quotations', 'column' => 'store_id', 'label' => 'quotations'],
        ['table' => 'party_bookings', 'column' => 'store_id', 'label' => 'service bookings'],
        ['table' => 'party_payments', 'column' => 'store_id', 'label' => 'service payments'],
        ['table' => 'party_operating_orders', 'column' => 'store_id', 'label' => 'service operating orders'],
        ['table' => 'party_consumable_issues', 'column' => 'store_id', 'label' => 'consumable issues'],
        ['table' => 'party_consumable_returns', 'column' => 'store_id', 'label' => 'consumable returns'],
        ['table' => 'offline_devices', 'column' => 'store_id', 'label' => 'offline POS devices'],
        ['table' => 'offline_transactions', 'column' => 'store_id', 'label' => 'offline POS transactions'],
    ];

    /**
     * Create or update a store record with correlation ID and audit logging.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?int $id = null): Store
    {
        Gate::authorize($id ? 'branches_stores.edit' : 'branches_stores.create');
        $type = $data['type'] ?? 'selling';
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException(__('Invalid location type specified.'));
        }

        return DB::transaction(function () use ($data, $id, $type) {
            $storeQuery = Store::query();
            if (auth()->check()) {
                $storeQuery->visibleTo(auth()->user());
            }
            $existingStore = $id ? $storeQuery->findOrFail($id) : null;
            $branchId = isset($data['branch_id']) && $data['branch_id'] !== ''
                ? (int) $data['branch_id']
                : $existingStore?->branch_id;
            $branchQuery = Branch::query()
                ->where('status', 'active')
                ->whereHas('company', fn ($query) => $query->where('status', 'active'));
            if (auth()->check()) {
                $branchQuery->visibleTo(auth()->user());
            }
            $branch = $branchId === null ? null : $branchQuery->whereKey($branchId)->lockForUpdate()->first();
            if ($branchId !== null && $branch === null) {
                throw new InvalidArgumentException($type === 'selling'
                    ? __('Select an active branch for this point of sale.')
                    : __('Select an active branch for this store.'));
            }
            if ($type === 'selling' && $branch === null) {
                throw new InvalidArgumentException(__('Select an active branch for this point of sale.'));
            }

            $company = $branch === null
                ? Company::query()->where('status', 'active')->first()
                : Company::query()->whereKey($branch->company_id)->where('status', 'active')->first();
            if ($company === null) {
                throw new InvalidArgumentException(__('Complete active Company Settings before creating stores.'));
            }

            $attributes = [
                'company_id' => $company->id,
                'branch_id' => $branch?->id,
                'price_list_id' => $type === 'selling' && filled($data['price_list_id'] ?? null) ? (int) $data['price_list_id'] : null,
                'code' => strtoupper(trim($data['code'])),
                'type' => $type,
                'name_ar' => trim($data['name_ar']),
                'name_en' => trim($data['name_en']),
                'status' => $data['status'] ?? 'active',
                'allows_negative_stock' => (bool) ($data['allows_negative_stock'] ?? false),
                'policy_notes' => isset($data['policy_notes']) && trim((string) $data['policy_notes']) !== '' ? trim((string) $data['policy_notes']) : null,
            ];
            if ($attributes['price_list_id'] !== null && ! PriceList::query()->where('company_id', $company->id)->whereKey($attributes['price_list_id'])->where('status', 'active')->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', today()))->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', today()))->exists()) {
                throw new InvalidArgumentException(__('Select an active valid price list for this Sales Outlet.'));
            }

            if ($id) {
                $store = $storeQuery->lockForUpdate()->findOrFail($id);

                // A status=inactive mutation is the approval-backed archive
                // path, never a silent edit-form bypass.
                if ($attributes['status'] === 'inactive' && $store->status === 'active') {
                    throw new InvalidArgumentException(__('Use Request archive for the approval-backed inactive transition.'));
                }

                $oldData = $store->toArray();
                $store->update($attributes);
                $actionName = 'update_store';
                $changes = [
                    'before' => $oldData,
                    'after' => $store->toArray(),
                ];
            } else {
                $store = Store::create($attributes);

                if ($store->type === 'selling' && $store->status === 'active' && $store->branch_id !== null
                    && ! BranchSellingStore::query()->where('branch_id', $store->branch_id)->where('status', 'active')->exists()) {
                    app(SaveBranchSellingStoreMappingAction::class)->execute($store->branch_id, $store->id);
                }

                $actionName = 'create_store';
                $changes = [
                    'after' => $store->toArray(),
                ];
            }

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: $actionName,
                source: $store,
                before: $changes['before'] ?? null,
                after: $changes['after'] ?? null,
                branchId: $store->branch_id,
                storeId: $store->id,
            );

            return $store;
        });
    }

    /**
     * Toggle store status (active / inactive) with dependency checks and audit logging.
     */
    public function toggleStatus(int $id): Store
    {
        Gate::authorize('branches_stores.edit');

        return DB::transaction(function () use ($id) {
            $storeQuery = Store::query();
            if (auth()->check()) {
                $storeQuery->visibleTo(auth()->user());
            }
            $store = $storeQuery->findOrFail($id);
            $newStatus = $store->status === 'active' ? 'inactive' : 'active';

            if ($newStatus === 'inactive') {
                $this->assertDeactivationSafe($store->id, true);
            } elseif ($store->branch_id !== null) {
                $branchQuery = Branch::query();
                if (auth()->check()) {
                    $branchQuery->visibleTo(auth()->user());
                }
                $branch = $branchQuery->lockForUpdate()->find($store->branch_id);
                if ($branch === null || $branch->status !== 'active') {
                    throw new InvalidArgumentException(__('Select an active branch for this store.'));
                }
                $store = $storeQuery->lockForUpdate()->findOrFail($id);
            }

            $oldStatus = $store->status;
            $store->update(['status' => $newStatus]);

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'toggle_store_status',
                source: $store,
                before: ['status' => $oldStatus],
                after: ['status' => $newStatus],
                branchId: $store->branch_id,
                storeId: $store->id,
            );

            return $store;
        });
    }

    /** @return list<array{table: string, column: string, label: string, count: int}> */
    public function dependencyReport(int|Store $store, bool $lock = false, bool $includeApprovalHistory = false): array
    {
        $storeId = $store instanceof Store ? $store->id : $store;
        $dependencies = [];

        foreach (self::DEPENDENCY_POLICY as $policy) {
            if (! $includeApprovalHistory && $policy['table'] === 'approval_records') {
                continue;
            }
            if (! Schema::hasTable($policy['table']) || ! Schema::hasColumn($policy['table'], $policy['column'])) {
                continue;
            }

            $query = DB::table($policy['table'])->where($policy['column'], $storeId);
            if ($lock) {
                $query->lockForUpdate();
            }
            $count = $query->select('id')->get()->count();
            if ($count > 0) {
                $dependencies[] = [...$policy, 'count' => $count];
            }
        }

        return $dependencies;
    }

    /**
     * Enforce the same dependency policy for every store lifecycle path.
     *
     * @throws InvalidArgumentException
     */
    public function assertStoreDependencyFree(int|Store $store, string $operation, bool $lock = true, bool $includeApprovalHistory = false): void
    {
        $storeId = $store instanceof Store ? $store->id : $store;
        if ($lock) {
            Store::query()->lockForUpdate()->findOrFail($storeId);
        }

        $dependencies = $this->dependencyReport($storeId, $lock, $includeApprovalHistory);
        if ($dependencies === []) {
            return;
        }

        $activeMapping = collect($dependencies)->first(fn (array $dependency): bool => $dependency['table'] === 'branch_selling_stores');
        if ($activeMapping !== null && DB::table('branch_selling_stores')
            ->where('store_id', $storeId)
            ->where('status', 'active')
            ->exists()) {
            throw new InvalidArgumentException(__('Cannot :operation this location because it is actively mapped to a POS branch. Unmap the POS branch first.', ['operation' => $operation]));
        }

        $summary = collect($dependencies)
            ->map(fn (array $dependency): string => $dependency['count'].' '.$dependency['label'])
            ->implode(', ');

        throw new InvalidArgumentException(__('Cannot :operation this location because these records still reference it: :dependencies. Resolve the listed dependencies first. Historical records are preserved; they are not deleted.', [
            'operation' => $operation,
            'dependencies' => $summary,
        ]));
    }

    /**
     * Reversible deactivation only stops directly unsafe active operations.
     * Historical/ordinary dependency rows remain valid on an inactive store.
     *
     * @throws InvalidArgumentException
     */
    public function assertDeactivationSafe(int|Store $store, bool $lock = true): void
    {
        $storeId = $store instanceof Store ? $store->id : $store;
        if ($lock) {
            Store::query()->lockForUpdate()->findOrFail($storeId);
        }

        if (Schema::hasTable('branch_selling_stores') && DB::table('branch_selling_stores')
            ->where('store_id', $storeId)
            ->where('status', 'active')
            ->exists()) {
            throw new InvalidArgumentException(__('Cannot deactivate this location while it is actively mapped to a POS branch. Unmap POS first.'));
        }

        if (Schema::hasTable('pos_shifts') && DB::table('pos_shifts')
            ->where('store_id', $storeId)
            ->whereIn('status', ['open', 'closing_submitted', 'variance_review'])
            ->exists()) {
            throw new InvalidArgumentException(__('Cannot deactivate this location while a POS shift is still active. Close the shift first.'));
        }
    }
}
