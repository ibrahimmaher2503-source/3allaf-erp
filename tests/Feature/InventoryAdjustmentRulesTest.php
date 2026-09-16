<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Inventory\Actions\SaveInventoryAdjustmentAction;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;

final class InventoryAdjustmentRulesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_fractional_feed_adjustment_and_controlled_reason_are_saved(): void
    {
        $user = User::factory()->create(['status' => 'active', 'is_super_admin' => true]);
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->warehouse()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $product = Product::factory()->fractional()->create();
        $kg = Unit::query()->where('code', 'KG')->firstOrFail();
        ProductUnit::query()->create(['product_id' => $product->id, 'unit_id' => $kg->id, 'conversion_factor' => '1', 'is_base_unit' => true, 'is_purchase_unit' => true, 'is_sale_unit' => true]);
        
        \DB::table('document_sequences')->insert(['document_type' => 'inventory_adjustment', 'scope_type' => 'branch', 'scope_id' => $branch->id, 'scope_key' => 'branch:'.$branch->id, 'prefix' => 'ADJ-', 'padding_length' => 6, 'next_value' => 1, 'reset_rule' => 'never', 'status' => 'active', 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user);
        self::assertTrue((bool) Product::query()->findOrFail($product->id)->fractional_quantity);

        $document = app(SaveInventoryAdjustmentAction::class)->execute(
            ['store_id' => $store->id, 'adjustment_type' => 'adjustment', 'reason_code' => 'weight_loss'],
            [['product_id' => $product->id, 'quantity_delta' => '-0.75', 'unit_cost' => '12.5000']],
        );

        self::assertSame('weight_loss', $document->reason_code);
        self::assertSame('-0.750000', $document->lines->firstOrFail()->quantity_delta);
    }

}
