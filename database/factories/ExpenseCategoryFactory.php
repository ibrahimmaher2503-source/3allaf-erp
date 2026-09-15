<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\CashControl\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExpenseCategory> */
final class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    public function definition(): array
    {
        $code = 'EXP-'.$this->faker->unique()->numerify('#####');

        return ['company_id' => CompanyFactory::new(), 'code' => $code, 'name_ar' => 'مصروف صناعي '.$code, 'name_en' => 'Synthetic Expense '.$code, 'active' => true];
    }
}
