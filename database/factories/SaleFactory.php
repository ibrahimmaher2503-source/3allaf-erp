<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Sale> */
final class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'store_id' => function (): int {
                $branch = BranchFactory::new()->create();

                return StoreFactory::new()->create(['branch_id' => $branch->id, 'company_id' => $branch->company_id])->id;
            },
            'branch_id' => fn (array $attributes) => Store::query()->findOrFail($attributes['store_id'])->branch_id,
            'cashier_id' => UserFactory::new(),
            'status' => 'draft',
            'idempotency_key' => (string) Str::uuid(),
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'total' => '100.00',
            'paid_total' => '0.00',
            'outstanding_amount' => '100.0000',
            'payment_status' => 'unpaid',
            'change_total' => '0.00',
            'cash_rounding_amount' => '0.00',
            'payable_total' => '100.00',
            'currency_code' => 'EGP',
            'lock_version' => 1,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => ['customer_id' => CustomerFactory::new()->cash(), 'document_number' => 'SALE-'.$this->faker->unique()->numerify('######'), 'status' => 'approved', 'paid_total' => '100.00', 'outstanding_amount' => '0.0000', 'payment_status' => 'paid', 'approved_at' => now()]);
    }

    public function partiallyPaid(): static
    {
        return $this->state(fn (): array => ['customer_id' => CustomerFactory::new()->credit(), 'document_number' => 'SALE-'.$this->faker->unique()->numerify('######'), 'status' => 'approved', 'paid_total' => '40.00', 'outstanding_amount' => '60.0000', 'payment_status' => 'partial', 'approved_at' => now()]);
    }

    public function credit(): static
    {
        return $this->state(fn (): array => ['customer_id' => CustomerFactory::new()->credit(), 'document_number' => 'SALE-'.$this->faker->unique()->numerify('######'), 'status' => 'approved', 'paid_total' => '0.00', 'outstanding_amount' => '100.0000', 'payment_status' => 'unpaid', 'approved_at' => now()]);
    }
}
