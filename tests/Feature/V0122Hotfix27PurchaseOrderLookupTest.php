<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\SavePurchaseOrderAction;
use App\Modules\Purchasing\Actions\SyncSupplierProductAssociation;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;

final class V0122Hotfix27PurchaseOrderLookupTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_purchasing_lookup_filters_incrementally_and_uses_only_latest_approved_supplier_price(): void
    {
        [$actor, $supplier, $store, $linked, $other] = $this->fixtures();
        $this->approvedInvoice($supplier, $store, $linked, '11.2500', '2026-08-01', 'EGP', 'approved');
        $this->approvedInvoice($supplier, $store, $linked, '17.7500', '2026-09-02', 'EGP', 'approved');
        $this->approvedInvoice($supplier, $store, $linked, '999.0000', '2026-09-09', 'EGP', 'draft');
        $this->approvedInvoice($supplier, $store, $linked, '888.0000', '2026-09-10', 'EGP', 'cancelled');

        $filtered = $this->actingAs($actor)->getJson(route('transaction-products.search', [
            'q' => '001', 'context' => 'purchasing', 'supplier_id' => $supplier->id, 'supplier_only' => 1, 'currency_code' => 'EGP',
        ]))->assertOk()->json('data');
        self::assertSame([$linked->id], array_column($filtered, 'id'));
        self::assertSame('17.7500', $filtered[0]['unit_cost']);
        self::assertSame('last_supplier_price', $filtered[0]['price_source']);
        self::assertSame('2026-09-02', $filtered[0]['price_date']);
        self::assertSame('piece', $filtered[0]['unit_of_measure']);
        self::assertSame('SUP-001-7', $filtered[0]['supplier_item_code']);

        $all = $this->getJson(route('transaction-products.search', [
            'q' => '001', 'context' => 'purchasing', 'supplier_id' => $supplier->id, 'supplier_only' => 0,
        ]))->assertOk()->json('data');
        self::assertEqualsCanonicalizing([$linked->id, $other->id], array_column($all, 'id'));

        $narrowed = $this->getJson(route('transaction-products.search', [
            'q' => '0012', 'context' => 'purchasing', 'supplier_id' => $supplier->id, 'supplier_only' => 0,
        ]))->assertOk()->json('data');
        self::assertSame([$other->id], array_column($narrowed, 'id'));

        $arabicNames = $this->getJson(route('transaction-products.search', [
            'q' => 'منتج', 'context' => 'purchasing', 'supplier_id' => $supplier->id, 'supplier_only' => 0,
        ]))->assertOk()->json('data');
        self::assertEqualsCanonicalizing([$linked->id, $other->id], array_column($arabicNames, 'id'));

        $exactBarcode = $this->getJson(route('transaction-products.search', [
            'q' => '6221234567890', 'context' => 'purchasing', 'supplier_id' => $supplier->id, 'supplier_only' => 1,
        ]))->assertOk()->json('data');
        self::assertCount(1, $exactBarcode);
        self::assertTrue($exactBarcode[0]['exact']);
        self::assertTrue($exactBarcode[0]['exact_unique']);

        $exactSupplierCode = $this->getJson(route('transaction-products.search', [
            'q' => 'SUP-001-7', 'context' => 'purchasing', 'supplier_id' => $supplier->id, 'supplier_only' => 1,
        ]))->assertOk()->json('data');
        self::assertSame([$linked->id], array_column($exactSupplierCode, 'id'));
        self::assertTrue($exactSupplierCode[0]['exact_unique']);
    }

    public function test_supplier_return_lookup_is_source_invoice_and_store_scope_constrained(): void
    {
        [$actor, $supplier, $store, $linked, $other] = $this->fixtures();
        $invoice = $this->approvedInvoice($supplier, $store, $linked, '12.0000', '2026-09-03', 'EGP', 'approved');

        $data = $this->actingAs($actor)->getJson(route('transaction-products.search', [
            'q' => '001', 'context' => 'purchasing', 'supplier_id' => $supplier->id, 'supplier_only' => 0, 'purchase_invoice_id' => $invoice->id,
        ]))->assertOk()->json('data');

        self::assertSame([$linked->id], array_column($data, 'id'));
        self::assertNotContains($other->id, array_column($data, 'id'));
    }

    public function test_lookup_denies_an_actor_without_any_transaction_permission_and_association_occurs_only_on_action(): void
    {
        [$actor, $supplier, , $linked, $other] = $this->fixtures();
        $unauthorized = User::factory()->create(['status' => 'active']);

        $this->actingAs($unauthorized)->getJson(route('transaction-products.search', ['q' => '001']))->assertForbidden();
        self::assertFalse(ProductSupplier::query()->where('supplier_id', $supplier->id)->where('product_id', $other->id)->exists());

        $this->actingAs($actor);
        app(SyncSupplierProductAssociation::class)->associate($supplier->id, $other->id, $actor->id);
        self::assertTrue(ProductSupplier::query()->where('supplier_id', $supplier->id)->where('product_id', $other->id)->exists());
    }

    public function test_authorized_create_route_renders_full_page_and_shared_action_saves_a_draft(): void
    {
        [$actor, $supplier, $store, $linked] = $this->fixtures();
        $unauthorized = User::factory()->create(['status' => 'active']);

        $this->actingAs($unauthorized)->get(route('purchasing.orders.create'))->assertForbidden();
        $this->actingAs($actor)->get(route('purchasing.orders.create'))
            ->assertOk()
            ->assertSee('data-guide="po-create-page"', false)
            ->assertSee('data-product-line-editor', false);

        $order = app(SavePurchaseOrderAction::class)->execute(
            ['supplier_id' => $supplier->id, 'store_id' => $store->id, 'order_date' => '2026-09-10', 'notes' => 'Hotfix27 full-page draft'],
            [['product_id' => $linked->id, 'quantity_ordered' => 2, 'unit_cost' => '17.7500']],
        );

        self::assertSame('draft', $order->status);
        self::assertSame($supplier->id, $order->supplier_id);
        self::assertCount(1, $order->lines);
        self::assertSame('35.5000', $order->subtotal);
    }

    /** @return array{User, Supplier, Store, Product, Product} */
    private function fixtures(): array
    {
        $actor = User::factory()->create(['status' => 'active']);
        $actor->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->warehouse()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $supplier = Supplier::query()->create(['code' => 'SUP-H27-'.str()->random(6), 'name_ar' => 'مورد المنتجات', 'name_en' => 'Product Supplier', 'status' => 'active']);
        $linked = Product::factory()->create(['item_code' => 'H27-001-A-'.str()->random(4), 'model_number' => 'MODEL-A1', 'name_ar' => 'منتج ألفا 001', 'name_en' => 'Product Alpha 001', 'unit_of_measure' => 'piece', 'average_cost' => '9.50']);
        $other = Product::factory()->create(['item_code' => 'H27-B-'.str()->random(4), 'model_number' => 'MODEL-0012-B', 'name_ar' => 'منتج بيتا', 'name_en' => 'Product Beta', 'unit_of_measure' => 'piece', 'average_cost' => '8.50']);
        Barcode::query()->create(['product_id' => $linked->id, 'barcode' => '6221234567890', 'source' => 'supplier', 'status' => 'active', 'is_primary' => true]);
        ProductSupplier::query()->create(['product_id' => $linked->id, 'supplier_id' => $supplier->id, 'supplier_item_code' => 'SUP-001-7', 'is_preferred' => false, 'created_by' => $actor->id, 'updated_by' => $actor->id]);

        return [$actor, $supplier, $store, $linked, $other];
    }

    private function approvedInvoice(Supplier $supplier, Store $store, Product $product, string $cost, string $date, string $currency, string $status): PurchaseInvoice
    {
        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'H27-INV-'.str()->random(8),
            'supplier_id' => $supplier->id,
            'store_id' => $store->id,
            'invoice_date' => $date,
            'currency_code' => $currency,
            'status' => $status,
            'subtotal' => $cost,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $cost,
            'idempotency_key' => (string) str()->uuid(),
            'approved_at' => $status === 'approved' ? $date.' 12:00:00' : null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        $invoice->lines()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'quantity_received' => $status === 'approved' ? 1 : 0,
            'unit_cost' => $cost,
            'base_consumer_price' => 20,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'subtotal' => $cost,
            'line_total' => $cost,
        ]);

        return $invoice;
    }
}
