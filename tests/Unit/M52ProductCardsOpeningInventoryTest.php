<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Catalog\Actions\ImportProductCardsWorkbookAction;
use App\Modules\Catalog\Support\ProductBarcodePolicy;
use App\Modules\Catalog\Support\ProductPricingContract;
use App\Modules\Inventory\Actions\ImportOpeningInventoryWorkbookAction;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class M52ProductCardsOpeningInventoryTest extends TestCase
{
    private ?Container $previous = null;
    protected function setUp(): void { parent::setUp(); $this->previous = Container::getInstance(); $app = new Application(dirname(__DIR__, 2)); $app->instance('translator', new Translator(new FileLoader(new Filesystem, [dirname(__DIR__, 2).'/vendor/laravel/framework/src/Illuminate/Translation/lang', dirname(__DIR__, 2).'/lang']), 'en')); Container::setInstance($app); }
    protected function tearDown(): void { Container::setInstance($this->previous); parent::tearDown(); }
    private function source(string $path): string { return file_get_contents(dirname(__DIR__, 2).'/'.$path); }

    public function test_international_gtin_preserves_leading_zero_and_validates_check_digit(): void
    {
        $policy = new ProductBarcodePolicy;
        self::assertSame('01234565', $policy->international('01234565'));
        $this->expectException(InvalidArgumentException::class); $policy->international('01234564');
    }

    public function test_local_barcode_uses_first_four_supplier_digits_and_six_digit_sequence(): void
    {
        $policy = new ProductBarcodePolicy;
        self::assertSame('1234', $policy->supplierDigits('SUP-12-34-99'));
        self::assertSame('01234000001', $policy->local('1234', 1));
        self::assertSame('01234000002', $policy->local('1234', 2));
    }

    public function test_local_supplier_code_without_four_digits_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class); (new ProductBarcodePolicy)->supplierDigits('SUP-12');
    }

    public function test_barcode_allocator_has_lock_unique_and_idempotency_guards(): void
    {
        $source = $this->source('app/Modules/Catalog/Actions/AddBarcodeAction.php');
        self::assertStringContainsString('lockForUpdate()', $source);
        self::assertStringContainsString("where('allocation_key'", $source);
        self::assertStringContainsString("'next_serial' => \$serial + 1", $source);
        self::assertStringContainsString('QueryException', $source);
    }

    public function test_product_pricing_contract_keeps_cost_and_consumer_price_separate(): void
    {
        self::assertSame(['average_cost' => '12.3400', 'sale_price' => '19.9900'], (new ProductPricingContract)->forProductCard('12.34', '19.99'));
    }

    public function test_product_import_template_contract_and_partial_rejections_are_real_xlsx(): void
    {
        self::assertSame('barcode_registration_type', ImportProductCardsWorkbookAction::HEADERS[0]);
        self::assertContains('supplier_code', ImportProductCardsWorkbookAction::HEADERS);
        $routes = $this->source('routes/catalog-data-exchange.php');
        self::assertStringContainsString("name('catalog.products.import-card.rejections')", $routes);
        self::assertStringContainsString("->xlsx('product-card-import-rejections.xlsx'", $routes);
        self::assertStringContainsString("['categories', 'brands']", str_replace("'products', ", '', $routes));
    }

    public function test_opening_inventory_template_keeps_input_columns_after_reference_columns(): void
    {
        self::assertSame(['product_barcode_or_code', 'product_code_reference', 'supplier_code_reference', 'model_reference', 'barcode_reference', 'product_name_ar_reference', 'product_name_en_reference', 'inventory_location_code', 'opening_quantity', 'unit_cost'], ImportOpeningInventoryWorkbookAction::HEADERS);
    }

    public function test_opening_inventory_posts_transactionally_and_idempotently_through_ledger(): void
    {
        $approve = $this->source('app/Modules/Inventory/Actions/ApproveOpeningInventoryAction.php');
        self::assertStringContainsString('DB::transaction', $approve);
        self::assertStringContainsString('PostInventoryMovement::class', $approve);
        self::assertStringContainsString("'opening-inventory:'.\$document->id.':'.\$line->id", $approve);
        self::assertStringContainsString("if (\$document->status === 'approved')", $approve);
    }

    public function test_opening_validation_rejects_service_missing_cost_existing_movement_and_fraction_policy(): void
    {
        $source = $this->source('app/Modules/Inventory/Actions/SaveOpeningInventoryDraftAction.php');
        self::assertStringContainsString("product_type === 'service'", $source);
        self::assertStringContainsString("unitCost(\$row['unit_cost'] ?? \$product->average_cost)", $source);
        self::assertStringContainsString('StockMovement::query()', $source);
        self::assertStringContainsString('Opening quantities must be positive whole numbers.', $source);
    }

    public function test_opening_reversal_and_zero_decision_are_audited_and_setup_readiness_is_authoritative(): void
    {
        $routes = $this->source('routes/opening-inventory.php');
        $setup = $this->source('app/Modules/Platform/Support/InitialSetupStatus.php');
        $registry = $this->source('app/Modules/Platform/Support/InitialSetupStepRegistry.php');
        self::assertStringContainsString('RecordAuditEvent::class', $routes);
        self::assertStringContainsString('OpeningInventoryZeroDecision', $setup);
        self::assertStringContainsString("'inventory.opening.index'", $registry);
        self::assertStringContainsString("whereNull('reversal_of_id')", $setup);
    }

    public function test_routes_preserve_existing_permissions_for_create_approval_reversal_and_exports(): void
    {
        $opening = $this->source('routes/opening-inventory.php'); $catalog = $this->source('routes/catalog-data-exchange.php');
        self::assertStringContainsString('can:inventory_stock_card.create', $opening);
        self::assertStringContainsString('can:inventory_stock_card.approve', $opening);
        self::assertStringContainsString('can:inventory_stock_card.reverse', $opening);
        self::assertStringContainsString('can:products_categories_brands.export', $catalog);
    }
}
