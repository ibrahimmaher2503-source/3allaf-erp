<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Support\ProductQuantity;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Validation\ValidationException;

final class Phase2FractionalInventoryTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_fractional_base_quantity_survives_movement_and_in_transit_updates(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->warehouse()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $product = Product::factory()->fractional()->create();
        $kg = Unit::query()->where('code', 'KG')->firstOrFail();
        ProductUnit::query()->create(['product_id' => $product->id, 'unit_id' => $kg->id, 'conversion_factor' => '1', 'is_base_unit' => true, 'is_purchase_unit' => true, 'is_sale_unit' => true]);
        $this->actingAs($user);

        $movement = app(PostInventoryMovement::class)->execute($product->id, $store->id, '7.5', 'phase2_test', '10', 'phase2-test-'.str()->uuid());
        app(PostInventoryMovement::class)->adjustInTransit($product->id, $store->id, '2.5');

        $balance = StockBalance::query()->where('product_id', $product->id)->where('store_id', $store->id)->firstOrFail();
        self::assertSame('7.500000', $movement->quantity);
        self::assertSame('7.500000', $balance->on_hand);
        self::assertSame('2.500000', $balance->in_transit);
    }

    public function test_integer_default_rejects_fractional_quantity(): void
    {
        $this->expectException(ValidationException::class);
        ProductQuantity::normalize('7.5');
    }

    public function test_purchase_action_persists_entered_and_base_unit_snapshots(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->warehouse()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $supplier = Supplier::query()->create(['code' => 'PH2-'.str()->random(8), 'name_ar' => 'مورد اختبار', 'name_en' => 'Phase 2 supplier', 'status' => 'active']);
        $product = Product::factory()->fractional()->create(['model_number' => 'PH2-'.str()->random(6), 'sale_price' => '20']);
        Barcode::query()->create(['product_id' => $product->id, 'barcode' => 'PH2'.random_int(10000000, 99999999), 'source' => 'supplier', 'status' => 'active', 'is_primary' => true]);
        $ton = Unit::query()->where('code', 'TON')->firstOrFail();
        $productUnit = ProductUnit::query()->create(['product_id' => $product->id, 'unit_id' => $ton->id, 'conversion_factor' => '1000', 'is_base_unit' => false, 'is_purchase_unit' => true, 'is_sale_unit' => true]);
        $this->actingAs($user);

        $invoice = app(SavePurchaseInvoiceAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id, 'invoice_date' => today()->toDateString(), 'currency_code' => 'EGP'],
            [['product_id' => $product->id, 'product_unit_id' => $productUnit->id, 'quantity' => '0.5', 'unit_cost' => '16000', 'base_consumer_price' => '20', 'discount_value' => '0', 'tax_rate' => '0']],
        );

        $line = $invoice->lines->firstOrFail();
        self::assertSame($productUnit->id, $line->product_unit_id);
        self::assertSame('0.500000', $line->entered_quantity);
        self::assertSame('1000.000000', $line->conversion_factor_snapshot);
        self::assertSame('500.000000', $line->quantity);
        self::assertSame('16000.0000', $line->entered_unit_price);
        self::assertSame('16.0000', $line->unit_cost);
        self::assertSame('8000.0000', $line->subtotal);
    }
}
