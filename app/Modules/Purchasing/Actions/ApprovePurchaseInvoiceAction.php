<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\ApproveRequest;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderLine;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\StockMovement;
use App\Modules\Purchasing\Services\PurchaseInvoiceCalculator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ApprovePurchaseInvoiceAction
{
    public function execute(int $id, ?int $expectedVersion = null): PurchaseInvoice
    {
        Gate::authorize('purchase_invoices.approve');

        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $actorId = (int) $actor->getAuthIdentifier();

        return DB::transaction(function () use ($actor, $actorId, $id, $expectedVersion): PurchaseInvoice {
            $visibleStoreIds = Store::query()->visibleTo($actor)->select('id');
            $invoice = PurchaseInvoice::query()
                ->whereIn('store_id', $visibleStoreIds)
                ->with(['lines.distributions', 'lines.product', 'charges'])
                ->lockForUpdate()
                ->findOrFail($id);
            if ($expectedVersion !== null && $invoice->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This invoice was modified in another session. Please reload before approving.'));
            }
            if ($invoice->status === 'approved') {
                return $invoice->fresh(['supplier', 'store', 'lines.product']);
            }
            if (! in_array($invoice->status, ['awaiting_distribution', 'submitted'], true)) {
                throw new InvalidArgumentException(__('Only purchase invoices awaiting distribution can be approved.'));
            }
            if ($invoice->created_by === $actorId && ! $actor->canBypassApproval()) {
                throw new InvalidArgumentException(__('The invoice creator cannot approve the same invoice.'));
            }

            $before = $invoice->only(['invoice_number', 'status', 'total_amount', 'lock_version']);
            $approval = ApprovalRecord::query()
                ->where('source_type', 'purchase_invoices')
                ->where('source_id', (string) $invoice->id)
                ->where('requested_action', 'approve')
                ->where('approval_state', ApprovalState::Pending->value)
                ->lockForUpdate()
                ->firstOrFail();
            $this->snapshotLandedCost($invoice);
            $invoice->load('lines.distributions');
            $this->validateBusinessRules($invoice);

            $number = $invoice->invoice_number ?: app(AllocatePurchaseInvoiceNumberAction::class)->execute((int) $invoice->store->branch_id);
            foreach ($invoice->lines as $line) {
                $this->postLine($invoice, $line, $actorId);
                $this->updateProductCard($invoice, $line, $actorId);
            }
            $this->createDestinationTransfers($invoice, $actorId);
            $this->updatePurchaseOrderState($invoice);

            $invoice->update([
                'invoice_number' => $number,
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $actorId,
                'lock_version' => $invoice->lock_version + 1,
            ]);
            foreach ($invoice->lines as $line) {
                app(SyncSupplierProductAssociation::class)->recordApprovedPrice($invoice, $line, $actorId);
            }
            app(ApproveRequest::class)->execute($approval, (string) $before['lock_version'], decisionNote: __('Purchase invoice and stock receipt approved.'));
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'approve_purchase_invoice', source: $invoice, before: $before, after: $invoice->only(['invoice_number', 'status', 'approved_at', 'approved_by', 'lock_version']), storeId: $invoice->store_id, metadata: ['stock_posted' => true, 'wac_posted' => true, 'approval_record_id' => $approval->id]);

            return $invoice->fresh(['supplier', 'store', 'lines.product']);
        });
    }

    private function validateBusinessRules(PurchaseInvoice $invoice): void
    {
        foreach ($invoice->lines as $line) {
            $idempotencyKey = 'purchase-invoice:'.$invoice->id.':line:'.$line->id;
            if (StockMovement::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                continue;
            }
            if ($this->compare($line->quantity, '0') <= 0) {
                throw new InvalidArgumentException(__('Received quantity must be greater than zero.'));
            }
            $distributed = $line->distributions->reduce(fn (string $total, $allocation): string => $this->add($total, $allocation->quantity, 6), '0');
            if ($this->compare($distributed, $line->quantity) !== 0) {
                throw new InvalidArgumentException(__('Every purchased unit must be distributed before approval.'));
            }
            if ($line->distributions->isEmpty() || $line->distributions->contains(fn ($allocation): bool => $this->compare($allocation->quantity, '0') <= 0 || (int) $allocation->destination_store_id === (int) $invoice->store_id)) {
                throw new InvalidArgumentException(__('Distribution destinations and quantities are invalid.'));
            }

            $balance = StockBalance::query()->where('product_id', $line->product_id)->where('store_id', $invoice->store_id)->lockForUpdate()->first();
            $onHand = $balance?->on_hand ?? '0';
            if ($this->compare($onHand, '0') < 0) {
                throw new InvalidArgumentException(__('Negative on-hand balance must be reconciled before receiving.'));
            }

            $quantity = $this->decimal($line->quantity);
            $lineCost = $this->decimal($line->subtotal);
            $newQuantity = $this->add($onHand, $quantity, 6);
            $newValue = $this->add($balance?->total_value ?? '0', $lineCost, 4);
            $this->divide($newValue, $newQuantity, 4);
            $this->divide($lineCost, $quantity, 4);
        }

        $this->validatePurchaseOrderRules($invoice);
    }

    private function createDestinationTransfers(PurchaseInvoice $invoice, int $actorId): void
    {
        $poster = app(PostInventoryMovement::class);
        foreach ($invoice->distributions->groupBy('destination_store_id') as $destinationId => $allocations) {
            $transfer = StockTransfer::query()->where('purchase_invoice_id', $invoice->id)->where('destination_store_id', $destinationId)->lockForUpdate()->first();
            if ($transfer !== null) {
                if ($transfer->status !== 'in_transit') {
                    throw new InvalidArgumentException(__('The linked destination transfer is not in the expected state.'));
                }

                continue;
            }
            $transfer = StockTransfer::query()->create([
                'purchase_invoice_id' => $invoice->id,
                'transfer_number' => app(AllocateDocumentNumber::class)->executeForBranch('stock_transfer', (int) $invoice->store->branch_id),
                'source_store_id' => $invoice->store_id,
                'destination_store_id' => (int) $destinationId,
                'status' => 'approved',
                'reason_code' => 'purchase_distribution',
                'requested_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => now(),
                'idempotency_key' => 'purchase-distribution:'.$invoice->id.':destination:'.$destinationId,
                'notes' => __('Automatically created from approved purchase invoice :invoice.', ['invoice' => $invoice->invoice_number ?: $invoice->id]),
            ]);
            foreach ($allocations as $allocation) {
                $line = $invoice->lines->firstWhere('id', $allocation->purchase_invoice_line_id);
                if (! $line) {
                    throw new InvalidArgumentException(__('The distribution contains a line outside this invoice.'));
                }
                $unitCost = (string) ($line->inventory_unit_cost ?? $this->divide($line->subtotal, $line->quantity, 6));
                $transferLine = $transfer->lines()->create(['product_id' => $line->product_id, 'quantity_requested' => $allocation->quantity, 'quantity_dispatched' => 0, 'quantity_received' => 0, 'unit_cost' => $unitCost]);
                $quantity = $this->decimal($allocation->quantity);
                $poster->execute($line->product_id, $invoice->store_id, '-'.$quantity, 'transfer_dispatch', $unitCost, 'PURCHASE-TRANSFER-DISPATCH:'.$transfer->id.':'.$transferLine->id, StockTransfer::class, $transfer->id, $transferLine->id);
                $poster->adjustInTransit($line->product_id, (int) $destinationId, $quantity);
                $transferLine->mutateApprovedParentLine(['quantity_dispatched' => $quantity]);
            }
            $transfer->mutateApprovedDocument(['status' => 'in_transit', 'dispatched_by' => $actorId, 'dispatched_at' => now(), 'lock_version' => $transfer->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('inventory', 'dispatch_purchase_distribution_transfer', $transfer, ['status' => 'approved'], $transfer->only(['status', 'dispatched_by', 'dispatched_at', 'lock_version']), storeId: $invoice->store_id, metadata: ['purchase_invoice_id' => $invoice->id, 'destination_store_id' => (int) $destinationId]);
        }
        foreach ($invoice->lines as $line) {
            $remaining = StockBalance::query()->where('product_id', $line->product_id)->where('store_id', $invoice->store_id)->lockForUpdate()->firstOrFail();
            if ($this->compare($remaining->on_hand, '0') !== 0) {
                throw new InvalidArgumentException(__('The temporary receiving warehouse must finish with zero remaining quantity for this receipt.'));
            }
        }
    }

    private function updateProductCard(PurchaseInvoice $invoice, PurchaseInvoiceLine $line, int $actorId): void
    {
        $product = Product::query()->lockForUpdate()->findOrFail($line->product_id);
        $before = ['average_cost' => $product->average_cost, 'sale_price' => $product->sale_price];
        $line->update(['previous_product_cost' => $product->average_cost, 'previous_base_consumer_price' => $product->sale_price]);
        $product->update(['average_cost' => $line->inventory_unit_cost ?? $line->unit_cost, 'sale_price' => $line->base_consumer_price, 'lock_version' => $product->lock_version + 1]);
        app(RecordAuditEvent::class)->execute(category: 'catalog', event: 'purchase_invoice_update_product_cost_and_base_price', source: $product, before: $before, after: ['average_cost' => $product->average_cost, 'sale_price' => $product->sale_price], storeId: $invoice->store_id, metadata: ['purchase_invoice_id' => $invoice->id, 'purchase_invoice_line_id' => $line->id, 'actor_id' => $actorId]);
    }

    private function postLine(PurchaseInvoice $invoice, PurchaseInvoiceLine $line, int $actorId): void
    {
        $idempotencyKey = 'purchase-invoice:'.$invoice->id.':line:'.$line->id;
        if (StockMovement::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }
        if ($this->compare($line->quantity, '0') <= 0) {
            throw new InvalidArgumentException(__('Received quantity must be greater than zero.'));
        }

        $balance = StockBalance::query()->where('product_id', $line->product_id)->where('store_id', $invoice->store_id)->lockForUpdate()->first();
        if ($balance === null) {
            $balance = StockBalance::query()->create(['product_id' => $line->product_id, 'store_id' => $invoice->store_id, 'on_hand' => 0, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 0, 'total_value' => 0, 'version' => 0]);
            $balance = StockBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
        }
        if ($this->compare($balance->on_hand, '0') < 0) {
            throw new InvalidArgumentException(__('Negative on-hand balance must be reconciled before receiving.'));
        }

        $quantity = $this->decimal($line->quantity);
        $lineCost = $this->add($line->subtotal, $line->allocated_charge_amount ?? '0', 4);
        $newQuantity = $this->add($balance->on_hand, $quantity, 6);
        $newValue = $this->add($balance->total_value, $lineCost, 4);
        $newAverage = $this->divide($newValue, $newQuantity, 4);

        StockMovement::query()->create([
            'product_id' => $line->product_id,
            'store_id' => $invoice->store_id,
            'movement_type' => 'purchase_receipt',
            'quantity' => $quantity,
            'unit_cost' => $line->inventory_unit_cost ?? $this->divide($lineCost, $quantity, 6),
            'total_cost' => $lineCost,
            'consumed_cost' => 0,
            'source_type' => PurchaseInvoice::class,
            'source_id' => $invoice->id,
            'source_line_id' => $line->id,
            'idempotency_key' => $idempotencyKey,
            'posted_at' => now(),
            'created_by' => $actorId,
        ]);
        $balance->update(['on_hand' => $newQuantity, 'average_cost' => $newAverage, 'total_value' => $newValue, 'version' => $balance->version + 1]);
        $line->update(['quantity_received' => $quantity]);
    }

    private function snapshotLandedCost(PurchaseInvoice $invoice): void
    {
        $charges = $invoice->charges->reduce(
            fn (string $total, $charge): string => $this->add($total, $charge->amount, 4),
            '0.0000',
        );
        $allocations = app(PurchaseInvoiceCalculator::class)->allocateLandedCost(
            $invoice->lines->map(fn (PurchaseInvoiceLine $line): array => [
                'id' => (int) $line->id,
                'subtotal' => (string) $line->subtotal,
                'quantity' => (string) $line->quantity,
            ])->all(),
            $charges,
        );

        foreach ($allocations as $allocation) {
            PurchaseInvoiceLine::query()->whereKey($allocation['id'])->update([
                'allocated_charge_amount' => $allocation['allocated_charge_amount'],
                'inventory_unit_cost' => $allocation['inventory_unit_cost'],
            ]);
        }
    }

    private function updatePurchaseOrderState(PurchaseInvoice $invoice): void
    {
        if (! $invoice->purchase_order_id) {
            return;
        }
        $order = PurchaseOrder::query()
            ->whereKey($invoice->purchase_order_id)
            ->where('store_id', $invoice->store_id)
            ->where('supplier_id', $invoice->supplier_id)
            ->with('lines')
            ->lockForUpdate()
            ->firstOrFail();
        foreach ($invoice->lines as $line) {
            if (! $line->purchase_order_line_id) {
                continue;
            }
            $poLine = PurchaseOrderLine::query()
                ->where('purchase_order_id', $order->id)
                ->where('product_id', $line->product_id)
                ->whereKey($line->purchase_order_line_id)
                ->lockForUpdate()
                ->first();
            if (! $poLine) {
                throw new InvalidArgumentException(__('The invoice line is not linked to the selected purchase order.'));
            }
            $newReceived = $this->add($poLine->quantity_received, $line->quantity, 6);
            if ($this->compare($newReceived, $poLine->quantity_ordered) > 0) {
                throw new InvalidArgumentException(__('Over-receipt is not allowed by the approved policy.'));
            }
            $poLine->mutateApprovedParentLine(['quantity_received' => $newReceived]);
        }
        $allReceived = $order->lines()->whereColumn('quantity_received', '<', 'quantity_ordered')->doesntExist();
        $someReceived = $order->lines()->where('quantity_received', '>', 0)->exists();
        $order->mutateApprovedDocument([
            'receiving_status' => $allReceived ? 'fully_received' : ($someReceived ? 'partially_received' : 'not_received'),
            'lock_version' => $order->lock_version + 1,
        ]);
    }

    private function validatePurchaseOrderRules(PurchaseInvoice $invoice): void
    {
        if (! $invoice->purchase_order_id) {
            foreach ($invoice->lines as $line) {
                if ($line->purchase_order_line_id) {
                    throw new InvalidArgumentException(__('The invoice line is not linked to the selected purchase order.'));
                }
            }

            return;
        }

        $order = PurchaseOrder::query()
            ->whereKey($invoice->purchase_order_id)
            ->where('store_id', $invoice->store_id)
            ->where('supplier_id', $invoice->supplier_id)
            ->lockForUpdate()
            ->firstOrFail();
        foreach ($invoice->lines as $line) {
            if (! $line->purchase_order_line_id) {
                continue;
            }
            $poLine = PurchaseOrderLine::query()
                ->where('purchase_order_id', $order->id)
                ->whereKey($line->purchase_order_line_id)
                ->lockForUpdate()
                ->first();
            if (! $poLine) {
                throw new InvalidArgumentException(__('The invoice line is not linked to the selected purchase order.'));
            }
            if ((int) $poLine->product_id !== (int) $line->product_id) {
                throw new InvalidArgumentException(__('The invoice line product does not match the selected purchase-order line.'));
            }
            $newReceived = $this->add($poLine->quantity_received, $line->quantity, 6);
            if ($this->compare($newReceived, $poLine->quantity_ordered) > 0) {
                throw new InvalidArgumentException(__('Over-receipt is not allowed by the approved policy.'));
            }
        }
    }

    /** @return numeric-string */
    private function decimal(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__('Invalid decimal value.'));
        }

        // @phpstan-ignore argument.type
        return bcadd($value, '0', 6);
    }

    /** @return numeric-string */
    private function add(mixed $left, mixed $right, int $scale): string
    {
        return bcadd($this->decimal($left), $this->decimal($right), $scale);
    }

    private function compare(mixed $left, mixed $right): int
    {
        return bccomp($this->decimal($left), $this->decimal($right), 6);
    }

    /** @return numeric-string */
    private function divide(mixed $left, mixed $right, int $scale): string
    {
        if ($this->compare($right, '0') === 0) {
            throw new InvalidArgumentException(__('Division by zero is not allowed.'));
        }

        return bcdiv($this->decimal($left), $this->decimal($right), $scale);
    }
}
