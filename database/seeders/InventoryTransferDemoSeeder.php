<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Actions\SaveCategoryAction;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Actions\ApproveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SaveInventoryAdjustmentAction;
use App\Modules\Inventory\Actions\SubmitInventoryAdjustmentAction;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Actions\SaveBranchAction;
use App\Modules\Platform\Actions\SaveStoreAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Small local-only fixture for exercising the inventory-transfer create screen.
 * It intentionally does not seed customers, purchasing, POS, or sales data.
 */
final class InventoryTransferDemoSeeder extends Seeder
{
    private const BRANCH_CODE = 'TRANSFER-DEMO';
    private const SOURCE_CODE = 'TRANSFER-SOURCE';
    private const DESTINATION_CODE = 'TRANSFER-DEST';
    private const PRODUCT_CODE = 'TRANSFER-DEMO-001';
    private const OPENING_KEY = 'transfer-demo-opening-stock-001';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('InventoryTransferDemoSeeder is local-only.');
        }

        $administrator = User::query()
            ->where('status', 'active')
            ->where('is_super_admin', true)
            ->first();

        if ($administrator === null) {
            throw new LogicException('Run the normal local setup first so an active super administrator exists.');
        }

        Auth::login($administrator);

        try {
            DB::transaction(function (): void {
                $branch = $this->branch();
                [$source, $destination] = $this->stores($branch);
                $product = $this->product();
                $this->openingStock($source, $product);
            });
        } finally {
            Auth::logout();
        }
    }

    private function branch(): Branch
    {
        $branch = Branch::query()->where('code', self::BRANCH_CODE)->first();

        if ($branch === null) {
            return app(SaveBranchAction::class)->execute([
                'code' => self::BRANCH_CODE,
                'name_ar' => 'فرع تجربة تحويل المخزون',
                'name_en' => 'Inventory Transfer Demo Branch',
                'timezone' => 'Africa/Cairo',
                'status' => 'active',
                'policy_notes' => 'Local transfer-screen fixture only.',
            ]);
        }

        $branch->forceFill(['status' => 'active'])->save();

        return $branch->fresh();
    }

    /** @return array{Store, Store} */
    private function stores(Branch $branch): array
    {
        $source = $this->store($branch, self::SOURCE_CODE, 'warehouse', 'مخزن مصدر للتحويل', 'Transfer Source Warehouse');
        $destination = $this->store($branch, self::DESTINATION_CODE, 'selling', 'نقطة بيع مستقبلة للتحويل', 'Transfer Destination Store');

        return [$source, $destination];
    }

    private function store(Branch $branch, string $code, string $type, string $nameAr, string $nameEn): Store
    {
        $store = Store::query()->where('code', $code)->first();

        if ($store === null) {
            return app(SaveStoreAction::class)->execute([
                'branch_id' => $branch->id,
                'code' => $code,
                'type' => $type,
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'status' => 'active',
                'allows_negative_stock' => false,
                'policy_notes' => 'Local transfer-screen fixture only.',
            ]);
        }

        $store->forceFill([
            'branch_id' => $branch->id,
            'type' => $type,
            'status' => 'active',
            'allows_negative_stock' => false,
        ])->save();

        return $store->fresh();
    }

    private function product(): Product
    {
        $category = Category::query()->where('code', 'TRANSFER-DEMO-CATEGORY')->first();

        if ($category === null) {
            $category = app(SaveCategoryAction::class)->execute([
                'code' => 'TRANSFER-DEMO-CATEGORY',
                'name_ar' => 'منتجات تجربة التحويل',
                'name_en' => 'Transfer Demo Products',
                'parent_id' => null,
                'status' => 'active',
                'sort_order' => 999,
            ]);
        } else {
            $category->forceFill(['status' => 'active'])->save();
        }

        $product = Product::query()->where('item_code', self::PRODUCT_CODE)->first();

        if ($product === null) {
            return app(SaveProductAction::class)->execute([
                'item_code' => self::PRODUCT_CODE,
                'name_ar' => 'منتج تجربة تحويل المخزون',
                'name_en' => 'Inventory Transfer Demo Product',
                'description_ar' => 'منتج محلي لتجربة إنشاء تحويل مخزون.',
                'description_en' => 'Local product for testing inventory transfers.',
                'product_type' => 'standard',
                'unit_of_measure' => 'piece',
                'category_id' => $category->id,
                'status' => 'active',
                'reorder_threshold' => '1.0000',
                'fractional_quantity' => false,
            ]);
        }

        $product->forceFill(['category_id' => $category->id, 'status' => 'active'])->save();

        return $product->fresh();
    }

    private function openingStock(Store $source, Product $product): void
    {
        $balance = StockBalance::query()
            ->where('store_id', $source->id)
            ->where('product_id', $product->id)
            ->first();

        if ($balance !== null && bccomp((string) $balance->on_hand, '0', 6) > 0) {
            return;
        }

        $adjustment = InventoryAdjustment::query()->where('idempotency_key', self::OPENING_KEY)->first();

        if ($adjustment === null) {
            $adjustment = app(SaveInventoryAdjustmentAction::class)->execute([
                'store_id' => $source->id,
                'adjustment_type' => 'entry',
                'reason_code' => 'opening_stock',
                'reason_notes' => 'Local transfer-screen fixture opening stock.',
                'idempotency_key' => self::OPENING_KEY,
            ], [[
                'product_id' => $product->id,
                'quantity_delta' => '20.000000',
                'unit_cost' => '10.0000',
            ]]);
        }

        if ($adjustment->status === 'draft') {
            $adjustment = app(SubmitInventoryAdjustmentAction::class)->execute($adjustment->id);
        }

        if ($adjustment->status === 'submitted') {
            app(ApproveInventoryAdjustmentAction::class)->execute($adjustment->id);
        }
    }
}
