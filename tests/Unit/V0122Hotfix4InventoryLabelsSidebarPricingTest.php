<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Inventory\Support\InventoryMovementLabel;
use App\Support\ProductQuantity;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use UnexpectedValueException;

final class V0122Hotfix4InventoryLabelsSidebarPricingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container;
        Container::setInstance($container);
        $container->instance('translator', new Translator(new ArrayLoader, 'en'));
        $container->instance('log', new NullLogger);
        Facade::setFacadeApplication($container);
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }

    private function labelWorkspaceSource(): string
    {
        $source = $this->source('resources/views/pricing/labels.blade.php');

        foreach (glob(dirname(__DIR__, 2).'/resources/views/pricing/labels/*.blade.php') as $partial) {
            $source .= (string) file_get_contents($partial);
        }

        return $source;
    }

    public function test_product_quantity_is_central_strict_and_decimal_safe(): void
    {
        self::assertSame('14', ProductQuantity::format('14.000'));
        self::assertSame('2', ProductQuantity::format('2.000000'));
        self::assertSame('-2', ProductQuantity::format('-2.000000'));
        self::assertSame('0', ProductQuantity::format('0.000'));
        self::assertSame(-2, ProductQuantity::normalizeSigned('-2'));
        self::assertSame('1.5', ProductQuantity::format('1.500000'));
        try {
            ProductQuantity::format('1.1234567');
            self::fail('Over-precision presentation data must fail.');
        } catch (UnexpectedValueException $e) {
            self::assertStringContainsString('Invalid product quantity', $e->getMessage());
        }
        self::assertStringContainsString('MAX_DECIMAL_PLACES = 6', $this->source('app/Support/ProductQuantity.php'));
        self::assertStringContainsString('ProductQuantity::format', $this->source('resources/views/components/product-quantity.blade.php'));
    }

    public function test_every_inventory_movement_has_a_localized_safe_label(): void
    {
        foreach (InventoryMovementLabel::options() as $key => $label) {
            self::assertNotSame($key, $label);
        }
        self::assertSame('Purchase receipt reversal', InventoryMovementLabel::label('purchase_receipt_reversal'));
        self::assertSame('Unknown movement', InventoryMovementLabel::label('legacy_private_key'));
        $report = $this->source('app/Modules/Reporting/Queries/ReportSnapshot.php');
        self::assertStringContainsString('InventoryMovementLabel::label', $report);
        self::assertStringNotContainsString("'type' => \$movement->movement_type", $report);
    }

    public function test_label_workspace_is_bounded_stateful_and_scanner_ready(): void
    {
        $routes = $this->source('routes/pricing.php');
        $view = $this->labelWorkspaceSource();
        self::assertStringContainsString('forPage($page, 21)', $routes);
        self::assertStringContainsString('$products = $prefill === [] ? collect()', $routes);
        self::assertStringContainsString('x-on:input.debounce.350ms', $view);
        self::assertStringContainsString('x-on:keydown.enter.prevent="search(true)"', $view);
        self::assertStringContainsString('if (exact && payload.data.length === 1)', $view);
        self::assertStringContainsString('if (!this.has(product.id))', $view);
        self::assertMatchesRegularExpression('/min="1"\s+max="500"\s+step="1"/', $view);
        self::assertStringContainsString("formaction=\"{{ route('pricing.labels.barcodes.store') }}\"", $view);
    }

    public function test_base_consumer_price_is_immediate_fallback_and_unpriced_definition(): void
    {
        $resolver = $this->source('app/Modules/Pricing/Services/EffectivePriceResolver.php');
        $workspace = $this->source('resources/views/pricing/index.blade.php');
        self::assertStringContainsString("'amount'=>(string)\$product->sale_price", $resolver);
        self::assertStringContainsString("whereNull('sale_price')->orWhere('sale_price', '<=', 0)", $workspace);
        self::assertStringNotContainsString('whereNotIn(\'id\', $pricedProductIds)', $workspace);
        self::assertStringContainsString('$price = $list ? app(PriceListResolver::class)->resolve($product, $list)->finalPrice : $product->sale_price', $this->source('app/Modules/Pricing/Services/BarcodeLabelService.php'));
    }

    public function test_desktop_sidebar_uses_document_scroll_and_mobile_keeps_drawer_scroll(): void
    {
        $css = $this->source('resources/css/app.css');
        $layout = $this->source('resources/views/layouts/app/sidebar.blade.php');
        $navigation = $this->source('config/navigation.php');
        self::assertStringContainsString('body.app-layout .app-sidebar .app-navigation { flex:0 0 auto!important', $css);
        self::assertStringContainsString('overflow:visible!important', $css);
        self::assertStringContainsString('@media(max-width:1023px)', $css);
        self::assertStringContainsString('overflow-y:auto!important', $css);
        self::assertStringContainsString('collapsible="mobile"', $layout);
        self::assertStringContainsString('customers.groups.index', $navigation);
    }
}
