<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Retail\Models\SaleLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SaleLine> */
final class SaleLineFactory extends Factory
{
    protected $model = SaleLine::class;

    public function definition(): array
    {
        $code = 'SKU-'.$this->faker->unique()->numerify('######');

        return [
            'sale_id' => SaleFactory::new(),
            'product_id' => ProductFactory::new()->feed(),
            'line_number' => 1,
            'item_code' => $code,
            'name_ar' => 'علف صناعي '.$code,
            'name_en' => 'Synthetic Feed '.$code,
            'entered_quantity' => '2.000000',
            'conversion_factor_snapshot' => '1.000000',
            'entered_unit_price' => '50.0000',
            'quantity' => '2.000000',
            'unit_price' => '50.0000',
            'gross_amount' => '100.00',
            'discount_amount' => '0.00',
            'allocated_invoice_discount' => '0.00',
            'net_amount' => '100.00',
        ];
    }
}
