<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Platform\Support\ProductPricingReadiness;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

final class V021ReadinessPricingHotfixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::getInstance()->instance('translator', new class
        {
            public function get(string $key, array $replace = [], ?string $locale = null): string
            {
                return $key;
            }
        });
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }

    private function product(string|int|null $price): array
    {
        return ['id'=>7,'item_code'=>'TEST-ITEM-0001','name_ar'=>'اختبار — صنف','name_en'=>'TEST-Product','model_number'=>'TEST-MODEL-0001','category_id'=>3,'category_active'=>true,'barcode_registration_type'=>'international','sale_price'=>$price];
    }

    public function test_product_cards_and_pricing_share_the_same_current_assessment(): void
    {
        $evaluator = new ProductPricingReadiness;
        $invalid = $evaluator->assessProducts([$this->product('0')]);

        self::assertFalse($invalid['product_cards_complete']);
        self::assertFalse($invalid['pricing_products_complete']);
        self::assertSame(1, $invalid['affected_count']);
        self::assertSame($invalid['affected_products'], $invalid['all_affected_products']);
        self::assertSame('invalid_base_price', $invalid['affected_products'][0]['issues'][0]);

        $corrected = $evaluator->assessProducts([$this->product('25.00')]);
        self::assertTrue($corrected['product_cards_complete']);
        self::assertTrue($corrected['pricing_products_complete']);
        self::assertSame(0, $corrected['affected_count']);
    }

    public function test_readiness_ui_localization_prerequisite_and_list_zero_counts_are_explicit(): void
    {
        $setup = $this->source('app/Modules/Platform/Support/InitialSetupStatus.php');
        $component = $this->source('resources/views/components/setup/product-pricing-readiness.blade.php');
        $pricing = $this->source('resources/views/pricing/lists.blade.php');
        $arabic = json_decode($this->source('lang/ar.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertStringContainsString("pricing_prerequisite_complete", $setup);
        self::assertStringContainsString("'requires_completion'", $setup);
        self::assertStringContainsString("Affected Product Cards: :count", $component);
        self::assertStringContainsString("catalog.products.edit", $component);
        self::assertStringContainsString("Inherited prices", $component);
        self::assertStringContainsString("Manual overrides", $component);
        self::assertStringContainsString('ProductPricingReadiness::class', $setup);
        self::assertSame('يتطلب استكمال', $arabic['Requires completion']);
        self::assertMatchesRegularExpression('/\p{Arabic}/u', $arabic['Complete the affected Product Cards before Pricing can be ready.']);
        self::assertDoesNotMatchRegularExpression('/[A-Za-z]{3,}/', $arabic['Complete the affected Product Cards before Pricing can be ready.']);
    }

    public function test_uat_products_are_positive_separate_marked_and_real_products_are_not_repaired(): void
    {
        $uat = $this->source('app/Modules/Platform/Services/UatDataset.php');

        self::assertStringContainsString("RAJEH_UAT_ALLOW_PRODUCTION')!=='CONFIRMED'", $uat);
        self::assertStringContainsString("TEST-ITEM-INT-", $uat);
        self::assertStringContainsString("TEST-ITEM-LOC-", $uat);
        self::assertStringContainsString("'average_cost'=>10,'sale_price'=>25", $uat);
        self::assertStringContainsString("'average_cost'=>12,'sale_price'=>30", $uat);
        self::assertStringContainsString("\$this->mark(\$batch,'products',\$internationalProduct->id", $uat);
        self::assertStringContainsString("\$this->mark(\$batch,'products',\$localProduct->id", $uat);
        self::assertStringContainsString("where('document_type', 'purchase_invoice')", $uat);
        self::assertStringNotContainsString('Product::query()->update(', $uat);
        self::assertStringNotContainsString('updateOrCreate', $uat);
    }

    public function test_all_post_deployment_corrections_remain_in_source(): void
    {
        $form = $this->source('resources/views/catalog/product-form.blade.php');
        $purchase = $this->source('resources/views/purchasing/invoices.blade.php');

        self::assertStringNotContainsString('($isEditing)<flux:', $form);
        self::assertStringNotContainsString('icon="barcode"', $purchase);
        self::assertStringContainsString('wire:model.live.debounce.300ms="search"', $purchase);
        self::assertStringContainsString('limit(20)', $purchase);
        self::assertStringContainsString('productSearchRequest', $purchase);
    }
}
