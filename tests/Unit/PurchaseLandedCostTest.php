<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Catalog\Services\ConvertProductQuantity;
use App\Modules\Purchasing\Services\PurchaseInvoiceCalculator;
use PHPUnit\Framework\TestCase;

final class PurchaseLandedCostTest extends TestCase
{
    public function test_charges_are_allocated_by_value_and_preserve_the_exact_total(): void
    {
        $result = (new PurchaseInvoiceCalculator(new ConvertProductQuantity))->allocateLandedCost([
            ['id' => 1, 'subtotal' => '24000.0000', 'quantity' => '1500.000000'],
            ['id' => 2, 'subtotal' => '8000.0000', 'quantity' => '500.000000'],
        ], '500.0000');

        self::assertSame('375.0000', $result[0]['allocated_charge_amount']);
        self::assertSame('125.0000', $result[1]['allocated_charge_amount']);
        self::assertSame('16.250000', $result[0]['inventory_unit_cost']);
        self::assertSame('16.250000', $result[1]['inventory_unit_cost']);
        self::assertSame('500.0000', bcadd($result[0]['allocated_charge_amount'], $result[1]['allocated_charge_amount'], 4));
        self::assertSame('32500.0000', bcadd('32000.0000', '500.0000', 4));
    }
}
