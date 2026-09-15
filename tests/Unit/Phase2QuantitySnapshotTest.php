<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Services\ConvertProductQuantity;
use App\Modules\Purchasing\Services\PurchaseInvoiceCalculator;
use App\Support\ProductQuantity;
use PHPUnit\Framework\TestCase;

final class Phase2QuantitySnapshotTest extends TestCase
{
    public function test_purchase_line_converts_entered_unit_quantity_and_price_to_base_values(): void
    {
        $calculator = new PurchaseInvoiceCalculator(new ConvertProductQuantity);
        $product = $this->product(10, true);
        $ton = $this->productUnit($product, '1000', 3, 101);

        $line = $calculator->calculateLine([
            'product' => $product,
            'product_unit' => $ton,
            'entered_quantity' => '2',
            'entered_unit_price' => '16000',
            'discount_value' => '0',
            'tax_rate' => '0',
        ]);

        self::assertSame(101, $line['product_unit_id']);
        self::assertSame('2.000000', $line['entered_quantity']);
        self::assertSame('1000.000000', $line['conversion_factor_snapshot']);
        self::assertSame('2000.000000', $line['quantity']);
        self::assertSame('16000.0000', $line['entered_unit_price']);
        self::assertSame('16.0000', $line['unit_cost']);
        self::assertSame('32000.0000', $line['subtotal']);
    }

    public function test_bag_factor_remains_product_specific(): void
    {
        $calculator = new PurchaseInvoiceCalculator(new ConvertProductQuantity);
        $fiftyKgProduct = $this->product(10, true);
        $twentyFiveKgProduct = $this->product(20, true);

        $fifty = $calculator->calculateLine(['product' => $fiftyKgProduct, 'product_unit' => $this->productUnit($fiftyKgProduct, '50', 3), 'quantity' => '3', 'unit_cost' => '860', 'discount_value' => '0', 'tax_rate' => '0']);
        $twentyFive = $calculator->calculateLine(['product' => $twentyFiveKgProduct, 'product_unit' => $this->productUnit($twentyFiveKgProduct, '25', 3), 'quantity' => '3', 'unit_cost' => '430', 'discount_value' => '0', 'tax_rate' => '0']);

        self::assertSame('150.000000', $fifty['quantity']);
        self::assertSame('75.000000', $twentyFive['quantity']);
        self::assertSame('2580.0000', $fifty['subtotal']);
        self::assertSame('1290.0000', $twentyFive['subtotal']);
    }

    public function test_decimal_precision_normalizes_without_float_arithmetic(): void
    {
        self::assertSame('7.500', ProductQuantity::normalize('7.5', decimalPlaces: 3));
    }

    private function product(int $id, bool $fractional): Product
    {
        $product = new Product(['fractional_quantity' => $fractional]);
        $product->setAttribute('id', $id);
        $product->exists = true;

        return $product;
    }

    private function productUnit(Product $product, string $factor, int $decimalPlaces, ?int $id = null): ProductUnit
    {
        $unit = new Unit(['decimal_places' => $decimalPlaces, 'status' => 'active']);
        $productUnit = new ProductUnit(['product_id' => $product->id, 'unit_id' => 1, 'conversion_factor' => $factor]);
        if ($id !== null) {
            $productUnit->setAttribute('id', $id);
        }
        $productUnit->setRelation('unit', $unit);

        return $productUnit;
    }
}
