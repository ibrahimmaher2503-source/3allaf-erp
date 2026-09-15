<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\CashControl\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Expense> */
final class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'expense_category_id' => fn (array $attributes) => ExpenseCategoryFactory::new()->create(['company_id' => $attributes['company_id']])->id,
            'cash_account_id' => null,
            'payment_method_id' => null,
            'expense_date' => today(),
            'amount' => '300.0000',
            'description' => 'مصروف صناعي للاختبار',
            'reference' => 'SYN-'.$this->faker->unique()->numerify('######'),
            'status' => 'approved',
            'idempotency_key' => (string) Str::uuid(),
            'payload_hash' => hash('sha256', (string) Str::uuid()),
            'created_by' => UserFactory::new(),
            'approved_by' => UserFactory::new(),
            'approved_at' => now(),
        ];
    }
}
