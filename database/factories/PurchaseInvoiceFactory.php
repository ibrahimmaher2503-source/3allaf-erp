<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Purchasing\Models\PurchaseInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PurchaseInvoice> */
final class PurchaseInvoiceFactory extends Factory
{
    protected $model = PurchaseInvoice::class;

    public function definition(): array
    {
        return [
            'invoice_number' => 'PINV-'.$this->faker->unique()->numerify('######'),
            'supplier_id' => SupplierFactory::new(),
            'store_id' => function (): int {
                $branch = BranchFactory::new()->create();

                return StoreFactory::new()->warehouse()->create(['branch_id' => $branch->id, 'company_id' => $branch->company_id])->id;
            },
            'invoice_date' => today(),
            'currency_code' => 'EGP',
            'status' => 'draft',
            'subtotal' => '1000.0000',
            'tax_amount' => '0.0000',
            'discount_amount' => '0.0000',
            'total_amount' => '1000.0000',
            'idempotency_key' => (string) Str::uuid(),
            'lock_version' => 1,
        ];
    }
}
