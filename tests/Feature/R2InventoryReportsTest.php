<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Store;
use App\Modules\Reporting\Queries\InventoryReport;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Validation\ValidationException;

final class R2InventoryReportsTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_historical_valuation_uses_movement_value_not_current_cost(): void
    {
        [$user, $company, $store, $product] = $this->scope();
        $this->movement($user, $product, $store, 'opening_inventory', '100', '20', '2000', '2026-08-01 08:00:00');
        $this->movement($user, $product, $store, 'purchase_receipt', '100', '30', '3000', '2026-08-10 08:00:00');
        $this->movement($user, $product, $store, 'sale', '-40', '25', '-1000', '2026-08-20 08:00:00');
        $this->movement($user, $product, $store, 'purchase_receipt', '100', '50', '5000', '2026-09-02 08:00:00');
        $product->update(['average_cost' => '34.62']);

        $report = app(InventoryReport::class)->valuation($user, ['company_id' => $company->id, 'store_id' => $store->id, 'as_of_date' => '2026-08-31'], true);
        $row = collect($report['rows'])->firstWhere('product_id', $product->id);

        self::assertSame('160.000000', $row->quantity_as_of);
        self::assertSame('4000.0000', $row->value_as_of);
        self::assertSame('25.0000', $row->unit_cost_as_of);
        self::assertSame('4000.0000', $report['summary']['total_value']);
    }

    public function test_movement_card_reconciles_opening_in_out_closing_and_store_scope(): void
    {
        [$user, $company, $store, $product] = $this->scope();
        $other = Store::factory()->warehouse()->create(['company_id' => $company->id, 'branch_id' => $store->branch_id]);
        $this->movement($user, $product, $store, 'opening_inventory', '100', '20', '2000', '2026-08-01 08:00:00');
        foreach ([
            ['purchase_receipt', '10', '20', '200', '2026-09-01 08:00:00'],
            ['retail_return', '2.5', '20', '50', '2026-09-02 08:00:00'],
            ['inventory_entry', '1.5', '20', '30', '2026-09-03 08:00:00'],
            ['sale', '-5', '20', '-100', '2026-09-04 08:00:00'],
            ['purchase_return', '-2', '20', '-40', '2026-09-05 08:00:00'],
            ['transfer_dispatch', '-4', '20', '-80', '2026-09-06 08:00:00'],
        ] as $event) $this->movement($user, $product, $store, ...$event);
        $this->movement($user, $product, $other, 'transfer_receipt', '4', '20', '80', '2026-09-06 09:00:00');

        $report = app(InventoryReport::class)->movementCard($user, ['company_id' => $company->id, 'product_id' => $product->id, 'store_id' => $store->id, 'date_from' => '2026-09-01', 'date_to' => '2026-09-30']);

        self::assertSame('100.000000', $report['opening']);
        self::assertSame('14.000000', $report['incoming']);
        self::assertSame('11.000000', $report['outgoing']);
        self::assertSame('103.000000', $report['closing']);
        self::assertSame('103.000000', $report['rows']->last()->running_balance);
        self::assertCount(6, $report['rows']);
        foreach ($report['rows'] as $row) self::assertNotSame($other->id, (int) ($row->store_id ?? 0));
    }

    public function test_today_movement_quantity_reconciles_with_current_balance_and_negative_stock_is_visible(): void
    {
        [$user, $company, $store, $product] = $this->scope();
        $negative = Product::factory()->feed()->create();
        $kg = Unit::query()->where('code', 'KG')->firstOrFail();
        ProductUnit::query()->create(['product_id' => $negative->id, 'unit_id' => $kg->id, 'conversion_factor' => '1', 'is_base_unit' => true, 'is_purchase_unit' => true, 'is_sale_unit' => true]);
        $this->movement($user, $product, $store, 'opening_inventory', '2.500000', '24', '60', now()->subHour()->utc()->format('Y-m-d H:i:s'));
        $this->movement($user, $negative, $store, 'sale', '-1.250000', '10', '-12.5', now()->subMinutes(30)->utc()->format('Y-m-d H:i:s'));
        StockBalance::query()->create(['product_id' => $product->id, 'store_id' => $store->id, 'on_hand' => '2.5', 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 24, 'total_value' => 60, 'version' => 1]);
        StockBalance::query()->create(['product_id' => $negative->id, 'store_id' => $store->id, 'on_hand' => '-1.25', 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 10, 'total_value' => '-12.5', 'version' => 1]);

        $report = app(InventoryReport::class)->valuation($user, ['company_id' => $company->id, 'store_id' => $store->id, 'as_of_date' => now('Africa/Cairo')->toDateString()]);

        self::assertSame('0.000000', $report['today_reconciliation_difference']);
        self::assertSame(1, $report['summary']['negative_products']);
        self::assertSame('47.5000', $report['summary']['total_value']);
    }

    public function test_store_from_another_company_is_rejected(): void
    {
        [$user, $company, , $product] = $this->scope();
        $otherCompany = Company::factory()->create();
        $otherBranch = Branch::factory()->create(['company_id' => $otherCompany->id]);
        $otherStore = Store::factory()->warehouse()->create(['company_id' => $otherCompany->id, 'branch_id' => $otherBranch->id]);

        $this->expectException(ValidationException::class);

        app(InventoryReport::class)->movementCard($user, [
            'company_id' => $company->id,
            'product_id' => $product->id,
            'store_id' => $otherStore->id,
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
        ]);
    }

    /** @return array{User, Company, Store, Product} */
    private function scope(): array
    {
        $user = User::factory()->create(['status' => 'active', 'is_super_admin' => true, 'email_verified_at' => now()]);
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->warehouse()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $product = Product::factory()->feed()->create();
        $kg = Unit::query()->where('code', 'KG')->firstOrFail();
        ProductUnit::query()->create(['product_id' => $product->id, 'unit_id' => $kg->id, 'conversion_factor' => '1', 'is_base_unit' => true, 'is_purchase_unit' => true, 'is_sale_unit' => true]);
        $this->actingAs($user);

        return [$user, $company, $store, $product];
    }

    private function movement(User $user, Product $product, Store $store, string $type, string $quantity, string $unitCost, string $totalCost, string $postedAt): void
    {
        StockMovement::query()->create([
            'product_id' => $product->id, 'store_id' => $store->id, 'movement_type' => $type,
            'quantity' => $quantity, 'unit_cost' => $unitCost, 'total_cost' => $totalCost,
            'consumed_cost' => bccomp($quantity, '0', 6) < 0 ? ltrim($totalCost, '-') : '0',
            'idempotency_key' => 'r2-report-'.str()->uuid(), 'posted_at' => $postedAt, 'created_by' => $user->id,
        ]);
    }
}
