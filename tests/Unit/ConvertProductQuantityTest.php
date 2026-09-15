<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Catalog\Services\ConvertProductQuantity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ConvertProductQuantityTest extends TestCase
{
    public function test_product_specific_factors_convert_tons_bags_and_fractional_quantities(): void
    {
        $product = $this->product(10);
        $converter = new ConvertProductQuantity;

        self::assertSame('1000.000000', $converter->execute($product, $this->unit(10, '1000'), '1'));
        self::assertSame('150.000000', $converter->execute($product, $this->unit(10, '50'), '3'));
        self::assertSame('7.500000', $converter->execute($product, $this->unit(10, '1'), '7.5'));

        $otherProduct = $this->product(20);
        self::assertSame('75.000000', $converter->execute($otherProduct, $this->unit(20, '25'), '3'));
    }

    public function test_unit_must_belong_to_product(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ConvertProductQuantity)->execute($this->product(10), $this->unit(20, '50'), '3');
    }

    public function test_zero_negative_and_over_precision_quantities_are_rejected(): void
    {
        $converter = new ConvertProductQuantity;
        $product = $this->product(10);
        $unit = $this->unit(10, '1');

        foreach (['0', '-1', '1.1234567'] as $quantity) {
            try {
                $converter->execute($product, $unit, $quantity);
                self::fail("Quantity {$quantity} should be rejected.");
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private function product(int $id): Product
    {
        $product = new Product;
        $product->setAttribute('id', $id);
        $product->exists = true;

        return $product;
    }

    private function unit(int $productId, string $factor): ProductUnit
    {
        return new ProductUnit([
            'product_id' => $productId,
            'unit_id' => 1,
            'conversion_factor' => $factor,
        ]);
    }
}
