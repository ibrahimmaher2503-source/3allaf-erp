<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Customer\Models\CustomerReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CustomerReceipt> */
final class CustomerReceiptFactory extends Factory
{
    protected $model = CustomerReceipt::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'customer_id' => CustomerFactory::new()->credit(),
            'payment_method_id' => PaymentMethodFactory::new(),
            'cash_account_id' => null,
            'receipt_date' => today(),
            'amount' => '500.0000',
            'reference' => 'SYN-'.$this->faker->unique()->numerify('######'),
            'status' => 'approved',
            'created_by' => UserFactory::new(),
            'approved_by' => UserFactory::new(),
            'approved_at' => now(),
            'idempotency_key' => (string) Str::uuid(),
            'payload_hash' => hash('sha256', (string) Str::uuid()),
        ];
    }
}
