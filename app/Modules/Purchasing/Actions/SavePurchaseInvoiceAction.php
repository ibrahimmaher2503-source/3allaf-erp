<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Services\PurchaseInvoiceCalculator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SavePurchaseInvoiceAction
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function execute(array $data, array $lines, ?int $id = null, ?int $expectedVersion = null): PurchaseInvoice
    {
        Gate::authorize('purchase_invoices.draft');
        if (collect($lines)->pluck('product_id')->filter()->duplicates()->isNotEmpty()) {
            throw new InvalidArgumentException(__('A product may appear only once; scan it again to increase quantity.'));
        }
        if (! Gate::allows('purchase_invoices.change_cost')) {
            throw new InvalidArgumentException(__('You are not allowed to change purchase cost.'));
        }
        if (! Gate::allows('purchase_invoices.change_list0_price')) {
            throw new InvalidArgumentException(__('You are not allowed to change Base Consumer Price List 0.'));
        }
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        return DB::transaction(function () use ($actor, $data, $lines, $id, $expectedVersion): PurchaseInvoice {
            $userId = $actor->id;
            $invoice = $id === null
                ? null
                : PurchaseInvoice::query()
                    ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
                    ->with('charges')
                    ->lockForUpdate()
                    ->findOrFail($id);

            if ($invoice !== null) {
                if ($expectedVersion !== null && $invoice->lock_version !== $expectedVersion) {
                    throw new InvalidArgumentException(__('This invoice was modified in another session. Please reload before saving.'));
                }
                if ($invoice->status !== 'draft') {
                    throw new InvalidArgumentException(__('Only draft purchase invoices can be edited.'));
                }
            }

            $store = Store::query()
                ->visibleTo($actor)
                ->with('branch:id,company_id')
                ->lockForUpdate()
                ->findOrFail((int) ($data['store_id'] ?? 0));
            $operationalCompanyId = $store->company_id ?? $store->branch?->company_id;
            if ($store->company_id !== null && $store->branch?->company_id !== null && (int) $store->company_id !== (int) $store->branch->company_id) {
                throw new InvalidArgumentException(__('The selected receiving store has inconsistent company ownership.'));
            }

            $supplier = Supplier::query()->with('supplierGroup:id,company_id')->lockForUpdate()->findOrFail((int) ($data['supplier_id'] ?? 0));
            if ($supplier->status !== 'active') {
                throw new InvalidArgumentException(__('Cannot create an invoice for an inactive supplier.'));
            }
            if ($supplier->supplierGroup !== null && ($operationalCompanyId === null || (int) $supplier->supplierGroup->company_id !== (int) $operationalCompanyId)) {
                throw new InvalidArgumentException(__('The selected supplier does not belong to the receiving store company.'));
            }

            $purchaseOrderId = ! empty($data['purchase_order_id']) ? (int) $data['purchase_order_id'] : null;
            $purchaseOrder = $purchaseOrderId === null
                ? null
                : PurchaseOrder::query()
                    ->where('store_id', $store->id)
                    ->with(['store:id,company_id,branch_id', 'branch:id,company_id'])
                    ->lockForUpdate()
                    ->findOrFail($purchaseOrderId);
            if ($purchaseOrder !== null) {
                if (! in_array($purchaseOrder->status, ['approved', 'partially_received', 'received'], true)) {
                    throw new InvalidArgumentException(__('Only procurement-manager-approved purchase orders can be converted to invoices.'));
                }
                if ((int) $purchaseOrder->supplier_id !== (int) $supplier->id) {
                    throw new InvalidArgumentException(__('The selected purchase order does not belong to the invoice supplier.'));
                }
                if ((int) $purchaseOrder->branch_id !== (int) $store->branch_id) {
                    throw new InvalidArgumentException(__('The selected purchase order does not belong to the receiving store branch.'));
                }
                $orderCompanyId = $purchaseOrder->store?->company_id ?? $purchaseOrder->branch?->company_id;
                if ($operationalCompanyId !== null && $orderCompanyId !== null && (int) $orderCompanyId !== (int) $operationalCompanyId) {
                    throw new InvalidArgumentException(__('The selected purchase order does not belong to the receiving store company.'));
                }
                if ($purchaseOrder->branch?->company_id !== null && $operationalCompanyId !== null && (int) $purchaseOrder->branch->company_id !== (int) $operationalCompanyId) {
                    throw new InvalidArgumentException(__('The selected purchase order branch does not belong to the receiving store company.'));
                }
            }
            if (($data['supplier_reference'] ?? '') !== '') {
                $duplicate = PurchaseInvoice::query()
                    ->where('supplier_id', $supplier->id)
                    ->where('supplier_reference', trim((string) $data['supplier_reference']))
                    ->when($invoice, fn ($query) => $query->whereKeyNot($invoice->id))
                    ->whereNotIn('status', ['cancelled', 'rejected'])
                    ->exists();
                if ($duplicate) {
                    throw new InvalidArgumentException(__('This supplier invoice reference already exists for the selected supplier.'));
                }
            }

            $submittedOrderLineIds = collect($lines)
                ->map(fn (array $line): ?int => filled($line['purchase_order_line_id'] ?? null) ? (int) $line['purchase_order_line_id'] : null)
                ->filter(fn (?int $lineId): bool => $lineId !== null)
                ->unique()
                ->values();
            if ($submittedOrderLineIds->isNotEmpty() && $purchaseOrder === null) {
                throw new InvalidArgumentException(__('A purchase order is required when an invoice line references a purchase-order line.'));
            }
            $purchaseOrderLines = $submittedOrderLineIds->isEmpty()
                ? collect()
                : $purchaseOrder->lines()->whereKey($submittedOrderLineIds)->lockForUpdate()->get()->keyBy('id');
            if ($purchaseOrderLines->count() !== $submittedOrderLineIds->count()) {
                throw new InvalidArgumentException(__('Every referenced purchase-order line must belong to the selected purchase order.'));
            }

            $normalizedLines = [];
            foreach ($lines as $index => $line) {
                $product = Product::query()->sellable()->with('baseProductUnit.unit')->findOrFail((int) ($line['product_id'] ?? 0));
                if ($product->status !== 'active') {
                    throw new InvalidArgumentException(__('Product :name is inactive and cannot be invoiced.', ['name' => $product->name_en ?: $product->name_ar]));
                }
                $purchaseOrderLineId = filled($line['purchase_order_line_id'] ?? null) ? (int) $line['purchase_order_line_id'] : null;
                $purchaseOrderLine = $purchaseOrderLineId === null ? null : $purchaseOrderLines->get($purchaseOrderLineId);
                if ($purchaseOrderLine !== null && (int) $purchaseOrderLine->product_id !== (int) $product->id) {
                    throw new InvalidArgumentException(__('The referenced purchase-order line does not belong to the selected product.'));
                }
                $productUnit = filled($line['product_unit_id'] ?? null)
                    ? $product->productUnits()->with('unit')->find((int) $line['product_unit_id'])
                    : $product->baseProductUnit;
                if (filled($line['product_unit_id'] ?? null) && ! $productUnit instanceof ProductUnit) {
                    throw new InvalidArgumentException(__('The selected unit does not belong to the product.'));
                }
                if ($productUnit?->unit?->status !== null && $productUnit->unit->status !== 'active') {
                    throw new InvalidArgumentException(__('The selected product unit is inactive.'));
                }
                $normalizedLines[] = [...$line, 'product' => $product, 'product_unit' => $productUnit, 'entered_quantity' => $line['quantity'] ?? null, 'entered_unit_price' => $line['unit_cost'] ?? null, 'product_id' => $product->id, 'purchase_order_line_id' => $purchaseOrderLine?->id, 'line_number' => $index + 1];
            }

            $totals = app(PurchaseInvoiceCalculator::class)->calculateDocument($normalizedLines);
            $chargeInputs = array_key_exists('charges', $data)
                ? $data['charges']
                : ($invoice?->charges->map->only(['charge_type', 'amount', 'notes'])->all() ?? []);
            if (! is_array($chargeInputs)) {
                throw new InvalidArgumentException(__('Purchase charges must be a list.'));
            }
            foreach ($totals['calculated'] as $index => $calculatedLine) {
                $purchaseOrderLineId = $normalizedLines[$index]['purchase_order_line_id'];
                if ($purchaseOrderLineId === null) {
                    continue;
                }
                $purchaseOrderLine = $purchaseOrderLines->get($purchaseOrderLineId);
                $remaining = bcsub((string) $purchaseOrderLine->quantity_ordered, (string) $purchaseOrderLine->quantity_received, 6);
                if (bccomp((string) $calculatedLine['quantity'], $remaining, 6) > 0) {
                    throw new InvalidArgumentException(__('Invoice quantity cannot exceed the remaining purchase-order quantity.'));
                }
            }
            $attributes = [
                'invoice_number' => $invoice?->invoice_number,
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $purchaseOrder?->id,
                'store_id' => $store->id,
                'supplier_reference' => ! empty($data['supplier_reference']) ? trim((string) $data['supplier_reference']) : null,
                'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
                'currency_code' => ! empty($data['currency_code']) ? strtoupper(trim((string) $data['currency_code'])) : null,
                'status' => 'draft',
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['taxAmount'],
                'discount_amount' => $totals['discountAmount'],
                'total_amount' => $totals['totalAmount'],
                'notes' => ! empty($data['notes']) ? trim((string) $data['notes']) : null,
                'updated_by' => $userId,
            ];

            if ($invoice === null) {
                $attributes['idempotency_key'] = (string) Str::uuid();
                $attributes['created_by'] = $userId;
                $attributes['lock_version'] = 0;
                $invoice = PurchaseInvoice::query()->create($attributes);
                $event = 'create_purchase_invoice';
                $before = null;
            } else {
                $before = $invoice->only(['supplier_id', 'store_id', 'status', 'subtotal', 'tax_amount', 'discount_amount', 'total_amount', 'lock_version']);
                $invoice->update([...$attributes, 'lock_version' => $invoice->lock_version + 1]);
                $event = 'update_purchase_invoice';
                $invoice->lines()->delete();
            }

            foreach ($totals['calculated'] as $index => $line) {
                $product = Product::query()->findOrFail($normalizedLines[$index]['product_id']);
                $invoice->lines()->create([
                    ...$line,
                    'product_id' => $normalizedLines[$index]['product_id'],
                    'purchase_order_line_id' => $normalizedLines[$index]['purchase_order_line_id'],
                    'base_consumer_price' => $normalizedLines[$index]['base_consumer_price'] ?? $product->sale_price,
                    'quantity_received' => 0,
                ]);
                app(SyncSupplierProductAssociation::class)->associate($supplier->id, $product->id, (int) $userId);
            }
            $invoice = app(SavePurchaseInvoiceChargesAction::class)->execute($invoice->id, $chargeInputs);

            app(RecordAuditEvent::class)->execute(
                category: 'procurement',
                event: $event,
                source: $invoice,
                before: $before,
                after: $invoice->fresh(['lines'])->only(['id', 'supplier_id', 'store_id', 'status', 'subtotal', 'tax_amount', 'discount_amount', 'total_amount', 'lock_version']),
                storeId: $invoice->store_id,
                metadata: ['line_count' => count($totals['calculated']), 'purchase_charge_total' => bcsub((string) $invoice->total_amount, (string) $totals['totalAmount'], 4)],
            );

            return $invoice->fresh(['supplier', 'store', 'lines.product', 'charges']);
        });
    }
}
