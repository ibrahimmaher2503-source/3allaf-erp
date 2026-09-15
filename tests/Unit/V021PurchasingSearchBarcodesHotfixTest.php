<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Catalog\Support\ProductBarcodePolicy;
use PHPUnit\Framework\TestCase;

final class V021PurchasingSearchBarcodesHotfixTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }

    public function test_uat_uses_two_products_and_real_barcode_services_only(): void
    {
        $service = $this->source('app/Modules/Platform/Services/UatDataset.php');
        foreach (['TEST-ITEM-INT-','TEST-ITEM-LOC-',"'barcode_registration_type'=>'international'","'barcode_registration_type'=>'local'",'AddBarcodeAction::class','addSupplierBarcode','allocateLocalBarcode',"'TEST-SUP-1234-'", "mark(\$batch,'barcode_sequences'"] as $contract) {
            self::assertStringContainsString($contract, $service);
        }
        self::assertStringNotContainsString('TESTLOCAL', $service);
        self::assertStringContainsString("getenv('RAJEH_UAT_ALLOW_PRODUCTION')!=='CONFIRMED'", $service);
        self::assertStringContainsString("firstOrCreate(['uat_batch_id'=>\$batch->id", $service);
    }

    public function test_gtin_and_local_barcode_formats_are_valid(): void
    {
        $policy = new ProductBarcodePolicy();
        self::assertSame('6220000000013', $policy->international('6220000000013'));
        self::assertSame('01234000001', $policy->local('1234', 1));
    }

    public function test_product_card_does_not_expose_internal_blade_state(): void
    {
        $view = $this->source('resources/views/catalog/product-form.blade.php');
        self::assertStringNotContainsString("                    (\$isEditing &&", $view);
        self::assertStringContainsString("@if (\$isEditing && \$productForm['product_type'] === 'digital')", $view);
        self::assertStringContainsString("__('Digital product (legacy)')", $view);
    }
}
