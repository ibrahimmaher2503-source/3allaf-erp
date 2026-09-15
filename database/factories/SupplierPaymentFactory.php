<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Purchasing\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SupplierPayment> */
final class SupplierPaymentFactory extends Factory
{
    protected $model = SupplierPayment::class;

    public function definition(): array
    {
        return [
            'supplier_id' => SupplierFactory::new()->credit(),
            'payment_method_id' => PaymentMethodFactory::new(),
            'cash_account_id' => null,
            'payment_date' => today(),
            'currency_code' => 'EGP',
            'amount' => '5000.0000',
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
