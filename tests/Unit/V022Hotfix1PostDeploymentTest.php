<?php

namespace Tests\Unit;

use App\Modules\Pricing\Services\BarcodeSvgRenderer;
use App\Support\ProductQuantity;
use PHPUnit\Framework\TestCase;

final class V022Hotfix1PostDeploymentTest extends TestCase
{
    private function source(string $path):string{return file_get_contents(dirname(__DIR__,2).'/'.$path);}
    private function labelWorkspaceSource(): string
    {
        $source = $this->source('resources/views/pricing/labels.blade.php');

        foreach (glob(dirname(__DIR__, 2).'/resources/views/pricing/labels/*.blade.php') as $partial) {
            $source .= (string) file_get_contents($partial);
        }

        return $source;
    }

    public function test_quantity_contract_is_integer_and_diagnostic_is_read_only():void
    {
        self::assertSame('2',ProductQuantity::format('2.000000'));self::assertSame('-2',ProductQuantity::format('-2.000'));
        foreach(['routes/inventory.php','routes/opening-inventory.php','routes/retail.php','resources/views/purchasing/invoices.blade.php','resources/views/purchasing/returns.blade.php'] as $path)self::assertStringContainsString('integer',$this->source($path));
        $diagnostic=$this->source('routes/console.php');self::assertStringContainsString('diagnose-fractional-quantities',$diagnostic);self::assertStringContainsString('Read-only report of fractional product quantities; never changes data.',$diagnostic);
    }

    public function test_barcode_renderer_outputs_scanner_structures():void
    {
        $renderer=new BarcodeSvgRenderer();$ean=$renderer->render('4006381333931','ean13');$code=$renderer->render('TJ-ABC-001','code128');
        foreach([$ean,$code] as $svg){self::assertStringContainsString('<svg',$svg);self::assertStringContainsString('<rect',$svg);self::assertStringContainsString('barcode-svg',$svg);}
        self::assertStringContainsString('4006381333931',$ean);self::assertStringContainsString('TJ-ABC-001',$code);
    }

    public function test_setup_filters_labels_localization_and_version_contracts():void
    {
        self::assertStringContainsString("\$status = \$complete ? 'completed'",$this->source('app/Modules/Platform/Support/InitialSetupStatus.php'));
        $filter=$this->source('resources/views/catalog/product-options.blade.php').$this->source('app/Modules/Catalog/Actions/DeleteProductOptionAction.php');foreach(['products_categories_brands.delete','wire:confirm','archive','replace','RecordAuditEvent'] as $needle)self::assertStringContainsString($needle,$filter);
        $labels=$this->source('routes/pricing.php').$this->labelWorkspaceSource().$this->source('resources/views/pricing/label-print.blade.php').$this->source('app/Modules/Pricing/Services/BarcodeLabelService.php');foreach(['name_ar','name_en','item_code','model_number','barcode_id','template_id','printer_id','copies','BarcodeSvgRenderer','show_price','finalPrice'] as $needle)self::assertStringContainsString($needle,$labels);
        self::assertStringContainsString("0.1.22-hotfix41",$this->source('app/Support/ApplicationVersion.php'));
        $ar=json_decode($this->source('lang/ar.json'),true,512,JSON_THROW_ON_ERROR);foreach(['Cities and localities','Stable code','Legacy — not recorded','Reset filters','PurchaseInvoice','purchase_distribution_in'] as $key)self::assertNotSame($key,$ar[$key]??$key);
    }

    public function test_pricing_remediation_and_print_layout_are_explicit():void
    {
        $pricing=$this->source('app/Modules/Platform/Support/ProductPricingReadiness.php').$this->source('resources/views/components/setup/product-pricing-readiness.blade.php').$this->source('resources/views/catalog/product-form.blade.php');foreach(['must be greater than zero','base-consumer-price','Cost price','Base consumer selling price'] as $needle)self::assertStringContainsString($needle,$pricing);
        $print=$this->source('database/migrations/2026_09_01_000103_add_barcode_label_layout_to_print_templates.php').$this->source('resources/views/platform/admin/settings.blade.php').$this->source('routes/pricing.php').$this->source('resources/views/pricing/label-print.blade.php').$this->source('app/Modules/Pricing/Services/BarcodeLabelService.php');foreach(['layout_settings','width_mm','height_mm','203','300','orientation','margin_mm','gap_mm','a4_rows','a4_columns','symbology','show_price','setPaper','data-paper-size'] as $needle)self::assertStringContainsString($needle,$print);
    }
}
