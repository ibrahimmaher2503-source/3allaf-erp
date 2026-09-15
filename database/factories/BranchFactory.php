<?php

namespace Database\Factories;

use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Branch> */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        $code = 'BR-'.$this->faker->unique()->bothify('###');

        return [
            'company_id' => Company::factory(),
            'code' => $code,
            'name_ar' => 'فرع '.$code,
            'name_en' => 'Branch '.$code,
            'timezone' => 'UTC',
            'status' => 'active',
            'policy_notes' => 'Deterministic automated test fixture.',
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
