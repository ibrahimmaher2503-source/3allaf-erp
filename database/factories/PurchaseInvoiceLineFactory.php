<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseInvoiceLine> */
final class PurchaseInvoiceLineFactory extends Factory
{
    protected $model = PurchaseInvoiceLine::class;

    public function definition(): array
    {
        return [
            'purchase_invoice_id' => PurchaseInvoiceFactory::new(),
            'product_id' => ProductFactory::new()->rawMaterial(),
            'entered_quantity' => '1000.000000',
            'conversion_factor_snapshot' => '1.000000',
            'entered_unit_price' => '1.0000',
            'quantity' => '1000.000000',
            'quantity_received' => '0.000000',
            'unit_cost' => '1.0000',
            'discount_amount' => '0.0000',
            'tax_amount' => '0.0000',
            'subtotal' => '1000.0000',
            'line_total' => '1000.0000',
        ];
    }
}
