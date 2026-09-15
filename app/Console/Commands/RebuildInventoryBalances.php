<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildInventoryBalances extends Command
{
    protected $signature = 'inventory:rebuild-balances
        {--apply : Persist rebuilt balances; without this flag the command is a dry run}
        {--product= : Limit the rebuild to one product ID}
        {--store= : Limit the rebuild to one store ID}';

    protected $description = 'Compare or rebuild stock balances from append-only stock movements';

    public function handle(): int
    {
        $productId = $this->integerOption('product');
        $storeId = $this->integerOption('store');

        $movements = DB::table('stock_movements')
            ->select([
                'product_id',
                'store_id',
            ])
            ->selectRaw('SUM(quantity) as on_hand')
            ->selectRaw('SUM(COALESCE(total_cost, quantity * COALESCE(unit_cost, 0))) as total_value')
            ->when($productId !== null, fn ($query) => $query->where('product_id', $productId))
            ->when($storeId !== null, fn ($query) => $query->where('store_id', $storeId))
            ->groupBy('product_id', 'store_id')
            ->get();

        $existing = DB::table('stock_balances')
            ->when($productId !== null, fn ($query) => $query->where('product_id', $productId))
            ->when($storeId !== null, fn ($query) => $query->where('store_id', $storeId))
            ->get()
            ->keyBy(fn (object $balance): string => $this->key((int) $balance->product_id, (int) $balance->store_id));

        $expectedKeys = [];
        $divergences = 0;
        $apply = (bool) $this->option('apply');

        if ($apply) {
            DB::beginTransaction();
        }

        try {
            $this->info($apply ? 'Applying inventory balance rebuild.' : 'Dry run; no balances will be changed.');

            foreach ($movements as $movement) {
                $product = (int) $movement->product_id;
                $store = (int) $movement->store_id;
                $key = $this->key($product, $store);
                $expectedKeys[$key] = true;

                $onHand = (float) $movement->on_hand;
                $totalValue = (float) $movement->total_value;
                $averageCost = abs($onHand) < 0.000001 ? 0.0 : $totalValue / $onHand;
                $current = $existing->get($key);
                $diverged = $current === null
                    || ! $this->same($current->on_hand, $onHand)
                    || ! $this->same($current->total_value, $totalValue)
                    || ! $this->same($current->average_cost, $averageCost);

                if ($diverged) {
                    $divergences++;
                }

                $this->line(sprintf(
                    '%s product=%d store=%d on_hand=%s value=%s average_cost=%s',
                    $diverged ? 'DIVERGED' : 'OK',
                    $product,
                    $store,
                    $this->number($onHand),
                    $this->number($totalValue),
                    $this->number($averageCost),
                ));

                if ($this->option('apply')) {
                    $now = now();
                    DB::table('stock_balances')->updateOrInsert(
                        ['product_id' => $product, 'store_id' => $store],
                        [
                            'on_hand' => $onHand,
                            'average_cost' => $averageCost,
                            'total_value' => $totalValue,
                            'updated_at' => $now,
                            'created_at' => $current === null ? $now : $current->created_at,
                        ],
                    );
                }
            }

            if ($apply) {
                $stale = $existing->keys()->diff(array_keys($expectedKeys));
                foreach ($stale as $key) {
                    [$staleProduct, $staleStore] = array_map('intval', explode(':', $key));
                    DB::table('stock_balances')
                        ->where('product_id', $staleProduct)
                        ->where('store_id', $staleStore)
                        ->delete();
                    $this->line("REMOVED stale balance product={$staleProduct} store={$staleStore}");
                }
            }

            if ($apply) {
                DB::commit();
            }
        } catch (\Throwable $exception) {
            if ($apply) {
                DB::rollBack();
            }

            throw $exception;
        }

        $this->info("Divergences: {$divergences}; movement groups: {$movements->count()}; existing balances: {$existing->count()}");

        return $divergences === 0 ? self::SUCCESS : ($apply ? self::SUCCESS : self::FAILURE);
    }

    private function integerOption(string $name): ?int
    {
        $value = $this->option($name);

        return $value === null || $value === '' ? null : (int) $value;
    }

    private function key(int $productId, int $storeId): string
    {
        return "{$productId}:{$storeId}";
    }

    private function same(mixed $actual, float $expected): bool
    {
        return abs((float) $actual - $expected) < 0.000001;
    }

    private function number(float $value): string
    {
        return number_format($value, 6, '.', '');
    }
}
