<?php

namespace Database\Factories;

use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CashDrawer> */
class CashDrawerFactory extends Factory
{
    protected $model = CashDrawer::class;

    public function definition(): array
    {
        $store = Store::factory();
        $code = 'DR-'.$this->faker->unique()->bothify('###');

        return [
            'company_id' => fn (array $attributes) => Store::find($attributes['store_id'])?->company_id,
            'branch_id' => fn (array $attributes) => Store::find($attributes['store_id'])?->branch_id,
            'store_id' => $store,
            'code' => $code,
            'name_ar' => 'درج '.$code,
            'name_en' => 'Drawer '.$code,
            'status' => 'active',
            'policy_notes' => 'Deterministic automated test fixture.',
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
