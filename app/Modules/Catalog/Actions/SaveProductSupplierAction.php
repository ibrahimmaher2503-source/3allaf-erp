<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SaveProductSupplierAction
{
    /**
     * Link product to supplier or update relation parameters.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): ProductSupplier
    {
        Gate::authorize('suppliers.edit');

        return DB::transaction(function () use ($data): ProductSupplier {
            $productId = (int) ($data['product_id'] ?? 0);
            $supplierId = (int) ($data['supplier_id'] ?? 0);
            $isPreferred = (bool) ($data['is_preferred'] ?? false);
            $supplierItemCode = ! empty($data['supplier_item_code']) ? trim((string) $data['supplier_item_code']) : null;
            $notes = ! empty($data['notes']) ? trim((string) $data['notes']) : null;
            $userId = Auth::id();

            $product = Product::query()->lockForUpdate()->findOrFail($productId);
            if ($product->isFamily()) {
                throw new InvalidArgumentException(__('Supplier SKU relationships belong to sellable child SKUs, not variation families.'));
            }
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplierId);

            if ($supplier->status !== 'active') {
                throw new InvalidArgumentException(__('Cannot link an inactive supplier to a product.'));
            }

            $relation = ProductSupplier::query()
                ->where('product_id', $product->id)
                ->where('supplier_id', $supplier->id)
                ->first();

            $before = $relation ? $relation->only(['product_id', 'supplier_id', 'supplier_item_code', 'is_preferred', 'notes']) : null;

            // Preferred-supplier authority is intentionally separate from ordinary
            // supplier-link editing. Setting, replacing, or removing a preferred
            // link requires the documented sensitive permission.
            if ($isPreferred || ($relation?->is_preferred ?? false)) {
                Gate::authorize('suppliers.preferred_change');
            }

            if ($isPreferred) {
                // Clear preference on all other suppliers for this product
                ProductSupplier::query()
                    ->where('product_id', $product->id)
                    ->where('supplier_id', '!=', $supplier->id)
                    ->where('is_preferred', true)
                    ->update([
                        'is_preferred' => false,
                        'updated_by' => $userId,
                    ]);
            }

            if ($relation === null) {
                $relation = ProductSupplier::query()->create([
                    'product_id' => $product->id,
                    'supplier_id' => $supplier->id,
                    'supplier_item_code' => $supplierItemCode,
                    'is_preferred' => $isPreferred,
                    'notes' => $notes,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
                $event = 'create_product_supplier';
            } else {
                $relation->update([
                    'supplier_item_code' => $supplierItemCode,
                    'is_preferred' => $isPreferred,
                    'notes' => $notes,
                    'updated_by' => $userId,
                ]);
                $event = 'update_product_supplier';
            }

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: $event,
                source: $relation,
                before: $before,
                after: $relation->fresh()->only(['product_id', 'supplier_id', 'supplier_item_code', 'is_preferred', 'notes']),
            );

            return $relation->fresh();
        });
    }
}
