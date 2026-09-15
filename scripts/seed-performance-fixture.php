<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = getopt('', ['products::', 'movements::']);
$productCount = max(1, (int) ($options['products'] ?? 50_000));
$movementCount = max(1, (int) ($options['movements'] ?? 1_000_000));
$now = now()->toDateTimeString();

if (DB::table('products')->exists() || DB::table('stock_movements')->exists()) {
    fwrite(STDERR, "Performance fixture requires a fresh dedicated database. Refusing to append to existing data.\n");
    exit(1);
}

$categoryId = DB::table('categories')->insertGetId([
    'code' => 'PERF-CAT',
    'name_ar' => 'بيانات أداء مؤقتة',
    'name_en' => 'Temporary performance data',
    'status' => 'active',
    'sort_order' => 0,
    'created_at' => $now,
    'updated_at' => $now,
]);
$brandId = DB::table('brands')->insertGetId([
    'code' => 'PERF-BRAND',
    'name_ar' => 'علامة أداء مؤقتة',
    'name_en' => 'Temporary performance brand',
    'status' => 'active',
    'created_at' => $now,
    'updated_at' => $now,
]);
$storeId = DB::table('stores')->insertGetId([
    'code' => 'PERF-STORE',
    'type' => 'selling',
    'name_ar' => 'مخزن أداء مؤقت',
    'name_en' => 'Temporary performance store',
    'status' => 'active',
    'allows_negative_stock' => false,
    'created_at' => $now,
    'updated_at' => $now,
]);

foreach (array_chunk(range(1, $productCount), 1000) as $chunk) {
    $rows = [];
    foreach ($chunk as $number) {
        $rows[] = [
            'item_code' => sprintf('PERF-%06d', $number),
            'name_ar' => sprintf('منتج أداء %06d', $number),
            'name_en' => sprintf('Performance product %06d', $number),
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'status' => 'active',
            'barcode_mode' => 'none',
            'lock_version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
    DB::table('products')->insert($rows);
}

$productIds = DB::table('products')->orderBy('id')->pluck('id')->all();
$startedAt = microtime(true);
foreach (array_chunk(range(1, $movementCount), 1000) as $chunk) {
    $rows = [];
    foreach ($chunk as $number) {
        $productId = $productIds[($number - 1) % $productCount];
        $quantity = (($number % 20) + 1) / 2;
        $unitCost = (($number % 500) + 100) / 100;
        $movementType = $number % 5 === 0 ? 'outbound' : 'inbound';
        $rows[] = [
            'product_id' => $productId,
            'store_id' => $storeId,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => round($quantity * $unitCost, 4),
            'consumed_cost' => $movementType === 'outbound' ? round($quantity * $unitCost, 4) : null,
            'source_type' => 'performance_fixture',
            'source_id' => $number,
            'source_line_id' => $number,
            'idempotency_key' => sprintf('perf-movement-%07d', $number),
            'posted_at' => now()->subMinutes($movementCount - $number)->toDateTimeString(),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
    DB::table('stock_movements')->insert($rows);

    if (count($rows) === 1000 && $chunk[0] % 100_000 === 1) {
        printf("Inserted %d/%d movements\n", $chunk[0] - 1, $movementCount);
    }
}

printf(
    "Performance fixture ready: products=%d movements=%d elapsed=%.2fs database=%s\n",
    DB::table('products')->count(),
    DB::table('stock_movements')->count(),
    microtime(true) - $startedAt,
    (string) config('database.connections.'.config('database.default').'.database'),
);
