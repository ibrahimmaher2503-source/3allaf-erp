<?php

namespace Database\Factories;

use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Store> */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $code = 'ST-'.$this->faker->unique()->bothify('###');

        return [
            'company_id' => fn (array $attributes) => Branch::find($attributes['branch_id'])?->company_id,
            'branch_id' => Branch::factory(),
            'code' => $code,
            'type' => 'selling',
            'name_ar' => 'متجر '.$code,
            'name_en' => 'Store '.$code,
            'status' => 'active',
            'allows_negative_stock' => false,
            'policy_notes' => 'Deterministic automated test fixture.',
        ];
    }

    public function warehouse(): static
    {
        return $this->state(['type' => 'warehouse']);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
