<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\PurchaseReturnLine;
use App\Modules\Purchasing\Models\StockBalance;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class UpdatePurchaseReturnDraftAction
{
    /** @param array<int, array<string, mixed>> $lines */
    public function execute(int $id, int $reasonId, array $lines, ?int $expectedVersion = null): PurchaseReturn
    {
        Gate::authorize('purchase_returns.edit');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $actorId = (int) $actor->id;

        return DB::transaction(function () use ($actor, $actorId, $id, $reasonId, $lines, $expectedVersion): PurchaseReturn {
            $return = PurchaseReturn::query()
                ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
                ->lockForUpdate()
                ->findOrFail($id);
            if ($expectedVersion !== null && $return->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This supplier return was modified in another session.'));
            }
            if ($return->status !== 'draft') {
                throw new InvalidArgumentException(__('Only draft supplier returns can be edited.'));
            }
            $invoice = PurchaseInvoice::query()
                ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($return->purchase_invoice_id);
            $reason = SupplierReturnReason::query()->whereKey($reasonId)->where('is_active', true)->firstOrFail();
            if ($invoice->status !== 'approved' || $invoice->supplier_id !== $return->supplier_id || $invoice->store_id !== $return->store_id) {
                throw new InvalidArgumentException(__('The source invoice is no longer eligible.'));
            }
            if ($lines === []) {
                throw new InvalidArgumentException(__('A supplier return must contain at least one invoice line.'));
            }
            $seen = [];
            $indexed = [];
            foreach ($lines as $input) {
                $sourceId = (int) ($input['purchase_invoice_line_id'] ?? 0);
                if (isset($seen[$sourceId])) {
                    throw new InvalidArgumentException(__('A source invoice line can appear only once.'));
                }
                $seen[$sourceId] = true;
                $indexed[$sourceId] = $input;
            }
            ksort($indexed);

            $normalized = [];
            $subtotal = '0';
            foreach ($indexed as $sourceId => $input) {
                $quantity = bcadd((string) \App\Support\ProductQuantity::normalize($input['quantity'] ?? null), '0', 6);
                $source = $invoice->lines->firstWhere('id', $sourceId);
                if ($source === null || bccomp($quantity, '0', 6) <= 0 || (isset($input['product_id']) && (int) $input['product_id'] !== (int) $source->product_id)) {
                    throw new InvalidArgumentException(__('Every line must reference an approved invoice line with positive quantity.'));
                }
                $other = $this->decimal(PurchaseReturnLine::query()->where('purchase_invoice_line_id', $source->id)->where('purchase_return_id', '!=', $return->id)->whereHas('purchaseReturn', static fn ($q) => $q->whereNotIn('status', ['rejected', 'cancelled', 'reversed']))->sum('quantity'));
                $received = $this->decimal($source->quantity_received);
                $remaining = bcsub($received, $other, 6);
                $balance = StockBalance::query()->where('product_id', $source->product_id)->where('store_id', $return->store_id)->lockForUpdate()->first();
                $onHand = $balance === null ? '0.000000' : $this->decimal($balance->on_hand);
                if (bccomp($quantity, $remaining, 6) > 0 || $balance === null || bccomp($quantity, $onHand, 6) > 0) {
                    throw new InvalidArgumentException(__('Return quantity exceeds eligible source quantity or current on-hand.'));
                }
                $cost = $this->decimal($source->unit_cost);
                $total = bcmul($quantity, $cost, 4);
                $subtotal = bcadd($subtotal, $total, 4);
                $normalized[] = ['purchase_invoice_line_id' => $source->id, 'product_id' => $source->product_id, 'quantity' => $quantity, 'unit_cost' => $cost, 'total_cost' => $total];
            }
            $before = $return->only(['reason_id', 'subtotal', 'total_amount', 'lock_version']);
            $return->lines()->delete();
            $return->lines()->createMany($normalized);
            foreach ($normalized as $line) {
                app(SyncSupplierProductAssociation::class)->associate($return->supplier_id, (int) $line['product_id'], $actorId);
            }
            $return->update(['reason_id' => $reason->id, 'subtotal' => $subtotal, 'total_amount' => $subtotal, 'updated_by' => $actorId, 'lock_version' => $return->lock_version + 1]);
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'edit_supplier_return_draft', source: $return, before: $before, after: $return->only(['reason_id', 'subtotal', 'total_amount', 'lock_version']), storeId: $return->store_id, reasonCode: $reason->code);

            return $return->fresh(['supplier', 'store', 'reason', 'purchaseInvoice', 'lines.product']);
        });
    }

    /** @return numeric-string */
    private function decimal(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__('Invalid decimal value.'));
        }

        // @phpstan-ignore argument.type
        return bcadd($value, '0', 6);
    }
}
