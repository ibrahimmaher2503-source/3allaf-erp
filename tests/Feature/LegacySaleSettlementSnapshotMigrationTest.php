<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Retail\Models\Sale;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class LegacySaleSettlementSnapshotMigrationTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_it_reconciles_approved_snapshots_from_captured_payments_only(): void
    {
        [$cashier, $customer, $method, $branchId, $storeId] = $this->context();
        $unpaid = $this->sale($cashier, $customer, $branchId, $storeId, 'approved', '500.00');
        $partial = $this->sale($cashier, $customer, $branchId, $storeId, 'approved', '500.00');
        $paid = $this->sale($cashier, $customer, $branchId, $storeId, 'approved', '500.00');
        $draft = $this->sale($cashier, $customer, $branchId, $storeId, 'draft', '500.00');
        $this->payment($partial, $cashier, $method, '200.00');
        $this->payment($paid, $cashier, $method, '500.00');

        $this->migration()->up();

        self::assertSame(['0.00', '500.0000', 'unpaid'], $this->snapshot($unpaid));
        self::assertSame(['200.00', '300.0000', 'partial'], $this->snapshot($partial));
        self::assertSame(['500.00', '0.0000', 'paid'], $this->snapshot($paid));
        self::assertSame(['99.00', '401.0000', 'partial'], $this->snapshot($draft));
    }

    public function test_it_fails_before_writing_when_an_approved_sale_is_overpaid(): void
    {
        [$cashier, $customer, $method, $branchId, $storeId] = $this->context();
        $stale = $this->sale($cashier, $customer, $branchId, $storeId, 'approved', '500.00');
        $overpaid = $this->sale($cashier, $customer, $branchId, $storeId, 'approved', '500.00');
        $this->payment($overpaid, $cashier, $method, '500.01');

        try {
            $this->migration()->up();
            self::fail('The migration accepted an overpaid approved sale.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('overpaid', $exception->getMessage());
        }

        self::assertSame(['99.00', '401.0000', 'partial'], $this->snapshot($stale));
    }

    /** @return array{User, Customer, PaymentMethod, int, int} */
    private function context(): array
    {
        $now = now();
        $cashier = User::factory()->create(['is_super_admin' => true, 'status' => 'active']);
        $companyId = DB::table('companies')->insertGetId(['code' => 'AR-'.Str::random(8), 'name_ar' => 'اختبار', 'name_en' => 'Test', 'currency_code' => 'EGP', 'created_at' => $now, 'updated_at' => $now]);
        $branchId = DB::table('branches')->insertGetId(['company_id' => $companyId, 'code' => 'AR-'.Str::random(8), 'name_ar' => 'فرع', 'name_en' => 'Branch', 'created_at' => $now, 'updated_at' => $now]);
        $storeId = DB::table('stores')->insertGetId(['company_id' => $companyId, 'branch_id' => $branchId, 'code' => 'AR-'.Str::random(8), 'type' => 'selling', 'name_ar' => 'مخزن', 'name_en' => 'Store', 'created_at' => $now, 'updated_at' => $now]);
        $customer = Customer::query()->create(['public_id' => (string) Str::uuid(), 'name_ar' => 'عميل', 'name_en' => 'Customer', 'status' => 'active', 'customer_type' => 'credit', 'credit_limit' => '5000.00', 'created_by' => $cashier->id, 'created_branch_id' => $branchId, 'created_store_id' => $storeId, 'idempotency_key' => (string) Str::uuid()]);
        $method = PaymentMethod::query()->create(['code' => 'AR-'.Str::random(8), 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash', 'status' => 'active']);

        return [$cashier, $customer, $method, $branchId, $storeId];
    }

    private function sale(User $cashier, Customer $customer, int $branchId, int $storeId, string $status, string $payable): Sale
    {
        return Sale::query()->create([
            'branch_id' => $branchId,
            'store_id' => $storeId,
            'cashier_id' => $cashier->id,
            'customer_id' => $customer->id,
            'status' => $status,
            'idempotency_key' => (string) Str::uuid(),
            'currency_code' => 'EGP',
            'subtotal' => $payable,
            'total' => $payable,
            'payable_total' => $payable,
            'paid_total' => '99.00',
            'outstanding_amount' => '401.00',
            'payment_status' => 'partial',
            'approved_at' => $status === 'approved' ? now() : null,
        ]);
    }

    private function payment(Sale $sale, User $cashier, PaymentMethod $method, string $amount): void
    {
        DB::table('sale_payments')->insert([
            'sale_id' => $sale->id,
            'payment_method_id' => $method->id,
            'method_code' => $method->code,
            'method_type' => $method->type,
            'amount' => $amount,
            'change_amount' => 0,
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $cashier->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{string, string, string} */
    private function snapshot(Sale $sale): array
    {
        $sale->refresh();

        return [(string) $sale->paid_total, (string) $sale->outstanding_amount, (string) $sale->payment_status];
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_15_000122_reconcile_legacy_sale_settlement_snapshots.php');
    }
}
