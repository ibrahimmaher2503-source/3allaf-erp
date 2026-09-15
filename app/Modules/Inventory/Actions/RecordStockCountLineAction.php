<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockCountLine;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RecordStockCountLineAction
{
    /** @param array<int|string, mixed> $countedQuantities @param array<int|string, mixed> $inputMethods */
    public function execute(int $id, array $countedQuantities, array $inputMethods = [], bool $recount = false): StockCount
    {
        Gate::authorize('stock_counts.edit');

        return DB::transaction(function () use ($id, $countedQuantities, $inputMethods, $recount): StockCount {
            $count = StockCount::query()->with('lines')->lockForUpdate()->findOrFail($id);
            if ($count->locations()->exists()) {
                throw new InvalidArgumentException(__('This count session uses append-only contributions. Use barcode or batch-add entry.'));
            }
            app(AssertInventoryStoreScope::class)->execute((int) $count->store_id);
            if (! in_array($count->status, ['draft', 'in_progress'], true)) {
                throw new InvalidArgumentException(__('Only open stock counts can record quantities.'));
            }
            if ($count->assigned_to !== null && (int) $count->assigned_to !== (int) Auth::id()) {
                throw new InvalidArgumentException(__('Only the assigned stock counter can record this count.'));
            }
            foreach ($countedQuantities as $productId => $value) {
                $product = Product::query()->sellable()->whereKey((int) $productId)->firstOrFail();
                $quantity = trim((string) $value);
                $quantity = bcadd((string) \App\Support\ProductQuantity::normalize($quantity, 'counted_quantity', true), '0', 6);
                $line = $count->lines()->where('product_id', $product->id)->lockForUpdate()->first();
                if ($line === null) {
                    $reference = StockBalance::query()->where('product_id', $product->id)->where('store_id', $count->store_id)->value('on_hand') ?? '0';
                    $line = $count->lines()->create(['product_id' => $product->id, 'reference_on_hand' => $reference]);
                }
                $line->update(['counted_quantity' => $quantity, 'is_counted' => true, 'input_method' => $recount ? 'recount' : (string) ($inputMethods[$productId] ?? 'manual'), 'recount_number' => $recount ? $line->recount_number + 1 : $line->recount_number, 'counted_at' => now()]);
                app(RecordAuditEvent::class)->execute('inventory', $recount ? 'recount_stock_count_line' : 'record_stock_count_line', $line, null, ['product_id' => $product->id, 'counted_quantity' => $quantity, 'input_method' => $line->input_method, 'recount_number' => $line->recount_number], storeId: $count->store_id, metadata: ['stock_count_id' => $count->id]);
            }
            return $count->fresh(['store', 'lines.product']);
        });
    }
}
