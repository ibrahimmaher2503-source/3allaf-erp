<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
final class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        $code = 'SUP-'.$this->faker->unique()->numerify('#####');

        return [
            'code' => $code,
            'name_ar' => $this->faker->randomElement(['الوادي للأعلاف', 'مخازن الدلتا', 'الصفوة للأعلاف']).' '.$code,
            'name_en' => 'Synthetic Feed Supplier '.$code,
            'phone' => $this->faker->randomElement(['010', '011', '012', '015']).$this->faker->unique()->numerify('########'),
            'payment_policy' => 'cash',
            'settlement_method' => 'cash',
            'status' => 'active',
            'lock_version' => 1,
        ];
    }

    public function credit(): static
    {
        return $this->state(['payment_policy' => 'credit', 'credit_days' => 30, 'credit_limit' => '50000.0000']);
    }
}
