<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Actions;

use App\Models\User;
use App\Modules\Assets\Actions\EvaluateAssetAlertsAction;
use App\Modules\Reporting\Models\Alert;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;

final class EvaluateAlertsAction
{
    public function execute(?User $user = null): int
    {
        $count = app(EvaluateAssetAlertsAction::class)->execute($user);
        StockBalance::query()
            ->with(['product', 'store'])
            ->when($user !== null, fn ($query) => $query->whereIn('store_id', Store::query()->visibleTo($user)->select('id')))
            ->where(function ($query): void {
                $query->where('on_hand', '<=', 0)->orWhere(function ($low): void {
                    $low->where('on_hand', '>', 0)->whereHas('product', fn ($product): mixed => $product->whereColumn('products.reorder_threshold', '>=', 'stock_balances.on_hand'));
                });
            })
            ->chunkById(200, function ($balances) use (&$count): void {
            foreach ($balances as $balance) {
            $store = Store::query()->find($balance->store_id);
            if ($store === null) continue;
            $isZero = (float) $balance->on_hand <= 0;
            $alert = Alert::query()->firstOrCreate(['alert_key' => ($isZero ? 'zero-stock:' : 'low-stock:').$balance->product_id.':'.$balance->store_id], [
                'alert_type' => $isZero ? 'zero_stock' : 'low_stock', 'severity' => $isZero ? 'warning' : 'info',
                'title' => $isZero ? 'Zero stock item' : 'Low stock item',
                'description' => $isZero ? 'A product has no on-hand quantity in this store.' : 'A product is at or below its reorder threshold.',
                'source_type' => StockBalance::class, 'source_id' => (string) $balance->product_id, 'branch_id' => $store->branch_id, 'store_id' => $store->id,
                'status' => 'open', 'metadata' => ['product_id' => $balance->product_id, 'on_hand' => (float) $balance->on_hand, 'reorder_threshold' => (float) ($balance->product?->reorder_threshold ?? 0)],
            ]);
            if ($alert->wasRecentlyCreated) $count++;

            $priced = $balance->product !== null && bccomp((string) ($balance->product->sale_price ?? '0'), '0', 4) > 0;
            if (! $priced) {
                $unpriced = Alert::query()->firstOrCreate(['alert_key' => 'unpriced:'.$balance->product_id.':'.$balance->store_id], [
                    'alert_type' => 'unpriced_product', 'severity' => 'warning', 'title' => 'Unpriced product',
                    'description' => 'The canonical base consumer selling price is missing or not positive.',
                    'source_type' => Product::class, 'source_id' => (string) $balance->product_id, 'branch_id' => $store->branch_id, 'store_id' => $store->id,
                    'status' => 'open', 'metadata' => ['product_id' => $balance->product_id],
                ]);
                if ($unpriced->wasRecentlyCreated) $count++;
            } else {
                Alert::query()->where('alert_key', 'unpriced:'.$balance->product_id.':'.$balance->store_id)
                    ->where('status', '!=', 'resolved')->update(['status' => 'resolved', 'resolved_at' => now()]);
            }
            }
        });
        return $count;
    }
}
