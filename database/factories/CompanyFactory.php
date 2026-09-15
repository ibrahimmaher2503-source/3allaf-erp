<?php

namespace Database\Factories;

use App\Modules\Platform\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $code = 'TST-'.$this->faker->unique()->bothify('###');

        return [
            'code' => $code,
            'name_ar' => 'شركة اختبار '.$code,
            'name_en' => 'Test Company '.$code,
            'currency_code' => 'EGP',
            'currency_symbol' => 'EGP',
            'timezone' => 'UTC',
            'locale_default' => 'en',
            'status' => 'active',
            'policy_notes' => 'Deterministic automated test fixture.',
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
