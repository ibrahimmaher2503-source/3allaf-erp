<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\ExecuteCorrection;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Data\CorrectionReferenceData;
use App\Modules\Platform\Enums\CorrectionType;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderLine;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ReversePurchaseInvoiceAction
{
    public function execute(int $id, string $reason, ?int $expectedVersion = null): PurchaseInvoice
    {
        Gate::authorize('purchase_invoices.reverse');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $actorId = (int) $actor->getAuthIdentifier();

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('A reversal reason is required.'));
        }

        return DB::transaction(function () use ($actor, $actorId, $id, $reason, $expectedVersion): PurchaseInvoice {
            $visibleStoreIds = Store::query()->visibleTo($actor)->select('id');
            $invoice = PurchaseInvoice::query()
                ->whereIn('store_id', $visibleStoreIds)
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($id);
            if ($expectedVersion !== null && $invoice->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This invoice was modified in another session.'));
            }
            if ($invoice->status === 'reversed') {
                return $invoice->fresh(['lines']);
            }
            if ($invoice->status !== 'approved') {
                throw new InvalidArgumentException(__('Only approved invoices can be reversed.'));
            }
            if ($invoice->supplierPaymentAllocations()->whereHas('payment', fn ($query) => $query->where('status', 'approved'))->exists()
                || $invoice->supplierAccountAdjustments()->where('status', 'approved')->exists()) {
                throw new InvalidArgumentException(__('A purchase invoice with supplier payments or account adjustments must be financially corrected before reversal.'));
            }
            $movements = StockMovement::query()->where('source_type', PurchaseInvoice::class)->where('source_id', $invoice->id)->where('movement_type', 'purchase_distribution_in')->lockForUpdate()->get();
            if ($movements->isEmpty()) {
                throw new InvalidArgumentException(__('No receipt movement exists to reverse.'));
            }
            $purchaseOrderLines = $this->validateReversal($invoice, $movements);

            $reversalMovementIds = [];
            foreach ($movements as $movement) {
                $reversalMovementIds[] = $this->reverseMovement($movement, $actorId);
            }
            foreach ($invoice->lines as $line) {
                if ($line->purchase_order_line_id) {
                    $poLine = $purchaseOrderLines[$line->id];
                    $poLine->mutateApprovedParentLine(['quantity_received' => $this->subtract($poLine->quantity_received, $line->quantity, 6)]);
                }
                $line->mutateApprovedParentLine(['quantity_received' => 0]);
                $product = \App\Modules\Catalog\Models\Product::query()->lockForUpdate()->findOrFail($line->product_id);
                $product->update(['average_cost' => $line->previous_product_cost, 'sale_price' => $line->previous_base_consumer_price, 'lock_version' => $product->lock_version + 1]);
            }

            $before = $invoice->only(['status', 'lock_version']);
            $requestId = Context::get('request_id') ?? (string) Str::uuid();
            $reference = new CorrectionReferenceData(
                originalSourceType: $invoice->sourceType(),
                originalSourceId: $invoice->sourceId(),
                originalSourceVersion: $invoice->sourceVersion(),
                originalSourceHash: $invoice->sourceHash(),
                correctionType: CorrectionType::Reversal,
                correctionSourceType: StockMovement::class,
                correctionSourceId: (string) min($reversalMovementIds),
                reason: $reason,
                requestedBy: $actorId,
                approvedBy: $actorId,
                branchId: $invoice->sourceBranchId(),
                storeId: $invoice->sourceStoreId(),
                requestId: $requestId,
                idempotencyKey: 'purchase-invoice-reversal:'.$invoice->id,
                createdAt: now(),
            );
            app(ExecuteCorrection::class)->execute(
                $reference,
                $invoice,
                $actor,
                [CorrectionType::Reversal],
                fn (User $user): mixed => Gate::forUser($user)->authorize('purchase_invoices.reverse'),
                function () use ($actorId, $invoice, $reason): PurchaseInvoice {
                    $invoice->mutateApprovedDocument(['status' => 'reversed', 'cancelled_at' => now(), 'cancelled_by' => $actorId, 'cancel_reason' => $reason, 'lock_version' => $invoice->lock_version + 1]);

                    return $invoice;
                },
            );
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'reverse_purchase_invoice', source: $invoice, before: $before, after: $invoice->only(['status', 'cancelled_at', 'cancelled_by', 'cancel_reason', 'lock_version']), storeId: $invoice->store_id, reasonText: $reason, metadata: ['stock_reversed' => true, 'reversal_movement_ids' => $reversalMovementIds, 'correction_request_id' => $requestId]);

            return $invoice->fresh(['lines']);
        });
    }

    /**
     * @param  iterable<StockMovement>  $movements
     * @return array<int, PurchaseOrderLine>
     */
    private function validateReversal(PurchaseInvoice $invoice, iterable $movements): array
    {
        foreach ($invoice->lines as $line) {
            $this->decimal($line->quantity);
        }

        foreach ($movements as $movement) {
            $key = 'reversal:stock-movement:'.$movement->id;
            if (StockMovement::query()->where('idempotency_key', $key)->exists()) {
                continue;
            }
            if (! Store::query()->whereKey($movement->store_id)->exists()) throw new InvalidArgumentException(__('The distribution destination no longer exists.'));

            $balance = StockBalance::query()->where('product_id', $movement->product_id)->where('store_id', $movement->store_id)->lockForUpdate()->firstOrFail();
            if ($this->compare($balance->on_hand, $movement->quantity) < 0) {
                throw new InvalidArgumentException(__('Cannot reverse a receipt after its quantity has been consumed or transferred.'));
            }
            $newQuantity = $this->subtract($balance->on_hand, $movement->quantity, 6);
            $newValue = $this->subtract($balance->total_value, $movement->total_cost ?? 0, 4);
            if ($this->compare($newQuantity, 0) !== 0) {
                $this->divide($newValue, $newQuantity, 4);
            }
        }

        $referencedLines = $invoice->lines->filter(fn ($line): bool => (bool) $line->purchase_order_line_id);
        if (! $invoice->purchase_order_id) {
            if ($referencedLines->isNotEmpty()) {
                throw new InvalidArgumentException(__('The invoice line is not linked to the selected purchase order.'));
            }

            return [];
        }

        $order = PurchaseOrder::query()
            ->whereKey($invoice->purchase_order_id)
            ->where('store_id', $invoice->store_id)
            ->where('supplier_id', $invoice->supplier_id)
            ->lockForUpdate()
            ->firstOrFail();
        $purchaseOrderLines = [];
        foreach ($referencedLines as $line) {
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
            $this->subtract($poLine->quantity_received, $line->quantity, 6);
            $purchaseOrderLines[$line->id] = $poLine;
        }

        return $purchaseOrderLines;
    }

    private function reverseMovement(StockMovement $movement, int $actorId): int
    {
        $key = 'reversal:stock-movement:'.$movement->id;
        if (StockMovement::query()->where('idempotency_key', $key)->exists()) {
            return (int) StockMovement::query()->where('idempotency_key', $key)->value('id');
        }
        $balance = StockBalance::query()->where('product_id', $movement->product_id)->where('store_id', $movement->store_id)->lockForUpdate()->firstOrFail();
        if ($this->compare($balance->on_hand, $movement->quantity) < 0) {
            throw new InvalidArgumentException(__('Cannot reverse a receipt after its quantity has been consumed or transferred.'));
        }
        $newQuantity = $this->subtract($balance->on_hand, $movement->quantity, 6);
        $newValue = $this->subtract($balance->total_value, $movement->total_cost ?? 0, 4);
        $newAverage = $this->compare($newQuantity, 0) === 0 ? '0.0000' : $this->divide($newValue, $newQuantity, 4);
        $reversal = StockMovement::query()->create(['product_id' => $movement->product_id, 'store_id' => $movement->store_id, 'movement_type' => 'purchase_receipt_reversal', 'quantity' => $this->subtract(0, $movement->quantity, 6), 'unit_cost' => $movement->unit_cost, 'total_cost' => $this->subtract(0, $movement->total_cost ?? 0, 4), 'consumed_cost' => 0, 'source_type' => PurchaseInvoice::class, 'source_id' => $movement->source_id, 'source_line_id' => $movement->source_line_id, 'idempotency_key' => $key, 'posted_at' => now(), 'reversal_of_id' => $movement->id, 'created_by' => $actorId]);
        $balance->update(['on_hand' => $newQuantity, 'total_value' => $newValue, 'average_cost' => $newAverage, 'version' => $balance->version + 1]);

        return $reversal->id;
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
    private function subtract(mixed $left, mixed $right, int $scale): string
    {
        return bcsub($this->decimal($left), $this->decimal($right), $scale);
    }

    private function compare(mixed $left, mixed $right): int
    {
        return bccomp($this->decimal($left), $this->decimal($right), 6);
    }

    /** @return numeric-string */
    private function divide(mixed $left, mixed $right, int $scale): string
    {
        if ($this->compare($right, 0) === 0) {
            throw new InvalidArgumentException(__('Division by zero is not allowed.'));
        }

        return bcdiv($this->decimal($left), $this->decimal($right), $scale);
    }
}
