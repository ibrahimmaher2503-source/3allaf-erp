<?php

namespace Database\Factories;

use App\Modules\Platform\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentMethod> */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        $code = 'cash-'.$this->faker->unique()->bothify('###');

        return [
            'code' => $code,
            'name_ar' => 'نقدي '.$code,
            'name_en' => 'Cash '.$code,
            'type' => 'cash',
            'requires_evidence' => false,
            'offline_eligible' => true,
            'status' => 'active',
            'policy_notes' => 'Deterministic automated test fixture.',
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
