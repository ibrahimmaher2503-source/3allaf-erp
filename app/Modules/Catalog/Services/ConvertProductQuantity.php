<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use InvalidArgumentException;

final class ConvertProductQuantity
{
    public function execute(Product $product, ProductUnit $productUnit, string $enteredQuantity): string
    {
        if ($product->getKey() === null || $productUnit->product_id === null || (string) $product->getKey() !== (string) $productUnit->product_id) {
            throw new InvalidArgumentException('The selected unit does not belong to the product.');
        }

        if (! preg_match('/^\d+(?:\.\d{1,6})?$/', $enteredQuantity) || bccomp($enteredQuantity, '0', 6) <= 0) {
            throw new InvalidArgumentException('Entered quantity must be a positive decimal with at most six decimal places.');
        }

        $factor = (string) $productUnit->conversion_factor;
        if (! preg_match('/^\d+(?:\.\d{1,6})?$/', $factor) || bccomp($factor, '0', 6) <= 0) {
            throw new InvalidArgumentException('Conversion factor must be a positive decimal.');
        }

        return bcmul($enteredQuantity, $factor, 6);
    }
}
