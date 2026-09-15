<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryBatch;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InventoryBatchTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        restore_exception_handler();
        restore_error_handler();
        parent::tearDown();
    }

    public function test_batches_reference_products_without_storing_quantity_and_history_can_remain_unbatched(): void
    {
        $now = now();
        $companyId = DB::table('companies')->insertGetId(['code' => 'BATCH-T', 'name_ar' => 'اختبار', 'name_en' => 'Batch Test', 'currency_code' => 'EGP', 'created_at' => $now, 'updated_at' => $now]);
        $branchId = DB::table('branches')->insertGetId(['company_id' => $companyId, 'code' => 'BATCH-B', 'name_ar' => 'فرع', 'name_en' => 'Branch', 'created_at' => $now, 'updated_at' => $now]);
        $storeId = DB::table('stores')->insertGetId(['company_id' => $companyId, 'branch_id' => $branchId, 'code' => 'BATCH-S', 'type' => 'selling', 'name_ar' => 'مخزن', 'name_en' => 'Store', 'created_at' => $now, 'updated_at' => $now]);
        $categoryId = DB::table('categories')->insertGetId(['code' => 'BATCH-C', 'name_ar' => 'أعلاف', 'name_en' => 'Feed', 'created_at' => $now, 'updated_at' => $now]);
        $productId = DB::table('products')->insertGetId(['item_code' => 'BATCH-P', 'name_ar' => 'علف اختبار', 'name_en' => 'Test Feed', 'category_id' => $categoryId, 'track_batches' => true, 'track_expiry' => true, 'created_at' => $now, 'updated_at' => $now]);

        $batch = InventoryBatch::query()->create(['product_id' => $productId, 'batch_number' => 'LOT-260915-A', 'production_date' => today()->subDay(), 'expiry_date' => today()->addMonth(), 'status' => 'active']);
        DB::table('stock_movements')->insert(['product_id' => $productId, 'store_id' => $storeId, 'batch_id' => null, 'movement_type' => 'legacy_opening', 'quantity' => 10, 'idempotency_key' => 'BATCH-HISTORY-1', 'posted_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        $movementId = DB::table('stock_movements')->insertGetId(['product_id' => $productId, 'store_id' => $storeId, 'batch_id' => $batch->id, 'movement_type' => 'purchase_receipt', 'quantity' => 20, 'idempotency_key' => 'BATCH-NEW-1', 'posted_at' => $now, 'created_at' => $now, 'updated_at' => $now]);

        self::assertFalse(Schema::hasColumn('inventory_batches', 'quantity'));
        self::assertSame($productId, $batch->product->id);
        self::assertSame($batch->id, StockMovement::query()->findOrFail($movementId)->batch->id);
        self::assertSame(1, StockMovement::query()->where('product_id', $productId)->whereNull('batch_id')->count());

        $this->expectException(InvalidArgumentException::class);
        InventoryBatch::query()->create(['product_id' => Product::query()->findOrFail($productId)->id, 'batch_number' => 'BAD-DATES', 'production_date' => today(), 'expiry_date' => today()->subDay(), 'status' => 'active']);
    }
}
