<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Pricing\Services\BarcodeSvgRenderer;
use PHPUnit\Framework\TestCase;

final class V0122Hotfix2CorrectiveTest extends TestCase
{
    private function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }

    public function test_product_card_normalizes_unchecked_quantity_and_persists_canonical_price(): void
    {
        $form = $this->source('resources/views/catalog/product-form.blade.php');
        $save = $this->source('app/Modules/Catalog/Actions/SaveProductAction.php');
        $readiness = $this->source('app/Modules/Platform/Support/ProductPricingReadiness.php');

        self::assertStringContainsString('FILTER_VALIDATE_BOOLEAN', $form);
        self::assertStringContainsString("'productForm.fractional_quantity' => ['required', 'boolean']", $form);
        self::assertStringNotContainsString("'productForm.fractional_quantity' => ['prohibited']", $form);
        self::assertStringContainsString("'productForm.sale_price' => ['required', 'numeric', 'min:0.01']", $form);
        self::assertStringContainsString("'sale_price' => \$pricing['sale_price']", $save);
        self::assertStringContainsString("\$value('sale_price')", $readiness);
        self::assertStringContainsString('product-validation-failed', $form);
    }

    public function test_barcode_renderer_emits_real_ean13_and_code128_bars(): void
    {
        $renderer = new BarcodeSvgRenderer;
        foreach (['4006381333931', 'TESTLOCAL0001'] as $value) {
            $svg = $renderer->render($value);
            self::assertStringContainsString('<svg class="barcode-svg"', $svg);
            self::assertGreaterThan(20, substr_count($svg, '<rect '));
            self::assertStringContainsString('aria-label="'.$value.'"', $svg);
        }
        self::assertTrue($renderer->validEan13('4006381333931'));
        self::assertFalse($renderer->validEan13('4006381333932'));
    }

    public function test_workspace_and_legacy_invoice_use_one_canonical_renderer(): void
    {
        $pricing = $this->source('routes/pricing.php');
        $purchase = $this->source('routes/purchasing.php');
        $view = $this->source('resources/views/pricing/label-print.blade.php');

        self::assertStringContainsString('BarcodeLabelService', $pricing);
        self::assertStringContainsString("redirect()->route('pricing.labels'", $purchase);
        self::assertStringContainsString('data-canonical-label-renderer', $view);
        self::assertStringContainsString('@page{size:', $view);
        self::assertStringContainsString('a4_rows', $view);
        self::assertFileDoesNotExist(dirname(__DIR__, 2).'/resources/views/purchasing/destination-labels.blade.php');
    }

    public function test_arabic_workspace_strings_are_complete_and_raw_key_is_absent(): void
    {
        $arabic = json_decode($this->source('lang/ar.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('لا يوجد باركود لهذا المنتج', $arabic['No barcode for this product']);
        self::assertSame('إضافة باركود', $arabic['Add barcode']);
        self::assertSame('يرجى تصحيح الحقل المحدد.', $arabic['Please correct the highlighted field.']);
        self::assertSame('المنتجات والمخزون', $arabic['Products & inventory']);
        self::assertStringNotContainsString('product form.fractional quantity', json_encode($arabic, JSON_UNESCAPED_UNICODE));
    }
}
