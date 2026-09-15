<?php

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseOrder;
use BcMath\Number;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SavePurchaseOrderAction
{
    /**
     * Create or update a draft Purchase Order with line items.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function execute(array $data, array $lines, ?int $id = null, ?int $expectedVersion = null): PurchaseOrder
    {
        Gate::authorize($id ? 'purchase_orders.edit' : 'purchase_orders.create');

        if (empty($lines)) {
            throw new InvalidArgumentException(__('A purchase order must contain at least one line item.'));
        }
        if (collect($lines)->pluck('product_id')->filter()->duplicates()->isNotEmpty()) {
            throw new InvalidArgumentException(__('A product may appear only once; scan it again to increase quantity.'));
        }

        return DB::transaction(function () use ($data, $lines, $id, $expectedVersion): PurchaseOrder {
            $userId = Auth::id();
            $po = $id === null ? null : PurchaseOrder::query()->lockForUpdate()->findOrFail($id);

            if ($po !== null) {
                if ($expectedVersion !== null && $po->lock_version !== $expectedVersion) {
                    throw new InvalidArgumentException(__('This purchase order was modified in another session. Please reload before saving.'));
                }

                if ($po->status !== 'draft') {
                    throw new InvalidArgumentException(__('Only draft purchase orders can be edited.'));
                }
            }

            $supplierId = (int) ($data['supplier_id'] ?? 0);
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplierId);
            if ($supplier->status !== 'active') {
                throw new InvalidArgumentException(__('Cannot create or update a purchase order for an inactive supplier.'));
            }

            $storeId = (int) ($data['store_id'] ?? 0);
            if ($storeId < 1) throw new InvalidArgumentException(__('A receiving store or warehouse is required so the purchase order has an explicit branch.'));
            $store = Store::query()->findOrFail($storeId);
            $this->assertStoreScope($storeId);
            $branchId = $store->branch_id;
            if ($branchId === null) throw new InvalidArgumentException(__('The receiving store must belong to a branch.'));
            if ($po !== null && $po->branch_id !== null && (int)$po->branch_id !== (int)$branchId) {
                throw new InvalidArgumentException(__('A numbered draft cannot move to another branch; create a new purchase order for that branch.'));
            }

            $validatedLines = [];
            $lineSubtotalSum = new Number('0');

            foreach ($lines as $index => $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                $product = Product::query()->sellable()->findOrFail($productId);
                if ($product->status !== 'active') {
                    throw new InvalidArgumentException(__('Product :name is inactive and cannot be ordered.', ['name' => $product->name_en ?: $product->name_ar]));
                }

                $qty = $this->decimal($line['quantity_ordered'] ?? null, 6, 'Quantity ordered', false);
                $unitCost = $this->decimal($line['unit_cost'] ?? null, 4, 'Unit cost', true);

                $lineSubtotal = $qty->mul($unitCost)->round(4);
                $lineSubtotalSum = $lineSubtotalSum->add($lineSubtotal)->round(4);

                $validatedLines[] = [
                    'product_id' => $product->id,
                    'line_number' => $index + 1,
                    'quantity_ordered' => (string) $qty,
                    'quantity_received' => '0.000000',
                    'unit_cost' => (string) $unitCost,
                    'subtotal' => (string) $lineSubtotal,
                    'notes' => ! empty($line['notes']) ? trim((string) $line['notes']) : null,
                    'updated_by' => $userId,
                ];
            }

            $taxAmount = new Number('0'); // Explicit local zero or TBD tax
            $totalAmount = $lineSubtotalSum->add($taxAmount)->round(4);

            $poNumber = $po !== null ? $po->po_number : app(AllocatePurchaseOrderNumberAction::class)->execute((int) $branchId);

            $attributes = [
                'po_number' => $poNumber,
                'supplier_id' => $supplier->id,
                'store_id' => $storeId,
                'branch_id' => $branchId,
                'status' => 'draft',
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => ! empty($data['expected_delivery_date']) ? $data['expected_delivery_date'] : null,
                'payment_terms' => ! empty($data['payment_terms']) ? trim((string) $data['payment_terms']) : null,
                'notes' => ! empty($data['notes']) ? trim((string) $data['notes']) : null,
                'subtotal' => (string) $lineSubtotalSum,
                'tax_amount' => (string) $taxAmount,
                'total_amount' => (string) $totalAmount,
                'updated_by' => $userId,
            ];

            if ($po === null) {
                $attributes['created_by'] = $userId;
                $attributes['lock_version'] = 0;
                $po = PurchaseOrder::query()->create($attributes);
                $event = 'create_purchase_order';
                $before = null;
            } else {
                $before = $po->only(['po_number', 'supplier_id', 'store_id', 'status', 'subtotal', 'tax_amount', 'total_amount', 'lock_version']);
                $po->update([
                    ...$attributes,
                    'lock_version' => $po->lock_version + 1,
                ]);
                $event = 'update_purchase_order';

                $po->lines()->delete();
            }

            foreach ($validatedLines as $lineData) {
                $po->lines()->create([
                    ...$lineData,
                    'created_by' => $userId,
                ]);
                app(SyncSupplierProductAssociation::class)->associate($supplier->id, (int) $lineData['product_id'], (int) $userId);
            }

            app(RecordAuditEvent::class)->execute(
                category: 'procurement',
                event: $event,
                source: $po,
                before: $before,
                after: $po->fresh(['lines'])->only(['po_number', 'supplier_id', 'store_id', 'status', 'subtotal', 'tax_amount', 'total_amount', 'lock_version']),
                branchId: $po->branch_id,
                storeId: $po->store_id,
                metadata: ['line_count' => count($validatedLines)],
            );

            return $po->fresh(['supplier', 'store', 'lines.product']);
        });
    }

    private function assertStoreScope(int $storeId): void
    {
        $user = Auth::user();
        if (! $user instanceof User || $user->is_super_admin) {
            return;
        }

        if (! Store::query()->visibleTo($user)->whereKey($storeId)->exists()) {
            throw new InvalidArgumentException(__('You are not authorized for the selected purchase-order store.'));
        }
    }

    private function decimal(mixed $value, int $scale, string $label, bool $allowZero): Number
    {
        $value = trim((string) $value);
        $this->assertDecimalFormat($value, $scale, $label);

        $normalized = new Number($value);
        if (! $allowZero && $normalized->compare(new Number('0')) <= 0) {
            throw new InvalidArgumentException(__(':label must be greater than zero.', ['label' => $label]));
        }

        return $normalized->round($scale);
    }

    /** @phpstan-assert numeric-string $value */
    private function assertDecimalFormat(string $value, int $scale, string $label): void
    {
        $pattern = $scale === 0
            ? '/^\d+$/D'
            : '/^\d+(?:\.\d{1,'.$scale.'})?$/D';

        if ($value === '' || preg_match($pattern, $value) !== 1) {
            throw new InvalidArgumentException(__(':label must be a valid decimal with up to :scale decimal places.', ['label' => $label, 'scale' => $scale]));
        }
    }
}
