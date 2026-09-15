<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\ProductStoreAssignment;
use App\Modules\Inventory\Queries\SearchAssignableProducts;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SaveProductLocationAssignmentsAction
{
    /** @param array<string,mixed> $selection @param list<int> $targetStoreIds */
    public function execute(int $companyId, int $branchId, array $targetStoreIds, array $selection, ?int $copySourceStoreId = null): int
    {
        Gate::authorize('inventory_stock_card.create');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $targetStoreIds = array_values(array_unique(array_map('intval', $targetStoreIds)));
        if ($companyId < 1 || $branchId < 1 || $targetStoreIds === []) throw ValidationException::withMessages(['locations' => __('Select an authorized company, branch, and at least one inventory location.')]);

        return DB::transaction(function () use ($actor, $companyId, $branchId, $targetStoreIds, $selection, $copySourceStoreId): int {
            $targets = Store::query()->visibleTo($actor)->with('branch:id,company_id')->whereIn('id', $targetStoreIds)->where('company_id', $companyId)->where('branch_id', $branchId)->where('status', 'active')->whereIn('type', ['warehouse', 'selling'])->lockForUpdate()->get();
            if ($targets->count() !== count($targetStoreIds) || $targets->contains(fn (Store $store): bool => (int) $store->branch?->company_id !== $companyId)) throw ValidationException::withMessages(['locations' => __('Every selected location must belong to the selected authorized company and branch.')]);

            if ($copySourceStoreId !== null) {
                $source = Store::query()->visibleTo($actor)->whereKey($copySourceStoreId)->where('company_id', $companyId)->where('status', 'active')->whereIn('type', ['warehouse', 'selling'])->first();
                if ($source === null) throw ValidationException::withMessages(['copy_source_store_id' => __('The source assortment location is outside your authorized company scope.')]);
                $productIds = ProductStoreAssignment::query()->where('store_id', $source->id)->where('company_id', $companyId)->where('status', 'active')
                    ->whereHas('product', fn ($query) => $query->sellable()->where('product_type', '!=', 'service'))
                    ->pluck('product_id')->map(fn ($id): int => (int) $id)->all();
            } else {
                $mode = $selection['mode'] ?? 'explicit';
                if ($mode === 'filtered') {
                    $filters = (array) ($selection['filters'] ?? []);
                    $filterStoreId = filter_var($filters['store_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if (($filters['not_assigned'] ?? false) && ($filterStoreId === false || ! in_array($filterStoreId, $targetStoreIds, true))) {
                        throw ValidationException::withMessages(['products' => __('The not-assigned filter must use one of the selected authorized target locations.')]);
                    }
                    $query = app(SearchAssignableProducts::class)->query($filters);
                    $excluded = array_values(array_unique(array_map('intval', (array) ($selection['excluded_ids'] ?? []))));
                    if ($excluded !== []) $query->whereNotIn('products.id', $excluded);
                    $productIds = $query->limit(30001)->pluck('products.id')->map(fn ($id): int => (int) $id)->all();
                } else {
                    $productIds = array_values(array_unique(array_filter(array_map('intval', (array) ($selection['ids'] ?? [])))));
                }
            }
            if ($productIds === [] || count($productIds) > 30000) throw ValidationException::withMessages(['products' => __('Select between 1 and 30,000 eligible products. Use the XLSX workflow for larger batches.')]);
            $eligibleIds = Product::query()->sellable()->where('product_type', '!=', 'service')->whereIn('id', $productIds)->lockForUpdate()->pluck('id')->map(fn ($id): int => (int) $id)->all();
            if (count($eligibleIds) !== count($productIds)) throw ValidationException::withMessages(['products' => __('One or more selected products are inactive, a service, or a non-stockable variation family.')]);

            $now = now(); $affected = 0;
            foreach (array_chunk($productIds, 500) as $chunk) {
                foreach ($targets as $target) {
                    $rows = array_map(fn (int $productId): array => ['company_id' => $companyId, 'branch_id' => $branchId, 'store_id' => $target->id, 'product_id' => $productId, 'status' => 'active', 'created_by' => $actor->id, 'updated_by' => $actor->id, 'created_at' => $now, 'updated_at' => $now], $chunk);
                    ProductStoreAssignment::query()->upsert($rows, ['store_id', 'product_id'], ['status', 'updated_by', 'updated_at']);
                    $affected += count($rows);
                }
            }
            app(RecordAuditEvent::class)->execute('inventory', $copySourceStoreId ? 'copy_product_location_assortment' : 'bulk_assign_products_to_locations', metadata: ['company_id' => $companyId, 'branch_id' => $branchId, 'store_ids' => $targetStoreIds, 'product_count' => count($productIds), 'assignment_count' => $affected, 'copy_source_store_id' => $copySourceStoreId, 'actor_id' => $actor->id]);

            return $affected;
        });
    }
}
