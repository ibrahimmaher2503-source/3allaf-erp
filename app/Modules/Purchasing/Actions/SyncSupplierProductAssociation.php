<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;

final class SyncSupplierProductAssociation
{
    public function associate(int $supplierId, int $productId, int $actorId): ProductSupplier
    {
        $relation = ProductSupplier::query()
            ->where('supplier_id', $supplierId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if ($relation !== null) {
            return $relation;
        }

        $relation = ProductSupplier::query()->create([
            'supplier_id' => $supplierId,
            'product_id' => $productId,
            'is_preferred' => false,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        app(RecordAuditEvent::class)->execute(
            category: 'master_data',
            event: 'create_product_supplier',
            source: $relation,
            before: null,
            after: $relation->only(['product_id', 'supplier_id', 'supplier_item_code', 'is_preferred']),
            metadata: ['source' => 'purchasing_document'],
        );

        return $relation;
    }

    public function recordApprovedPrice(PurchaseInvoice $invoice, PurchaseInvoiceLine $line, int $actorId): ProductSupplier
    {
        $relation = $this->associate((int) $invoice->supplier_id, (int) $line->product_id, $actorId);
        $before = $relation->only(['last_purchase_price', 'last_purchase_date']);
        $relation->update([
            'last_purchase_price' => $line->unit_cost,
            'last_purchase_date' => $invoice->approved_at ?? now(),
            'updated_by' => $actorId,
        ]);

        app(RecordAuditEvent::class)->execute(
            category: 'procurement',
            event: 'update_product_supplier_purchase_history',
            source: $relation,
            before: $before,
            after: $relation->fresh()->only(['last_purchase_price', 'last_purchase_date']),
            storeId: $invoice->store_id,
            metadata: ['purchase_invoice_id' => $invoice->id, 'purchase_invoice_line_id' => $line->id],
        );

        return $relation->fresh();
    }
}
