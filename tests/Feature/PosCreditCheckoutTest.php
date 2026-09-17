<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Support\CustomerBalance;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Retail\Actions\CapturePaymentAction;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Models\Sale;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionMethod;

final class PosCreditCheckoutTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_unpaid_credit_sale_has_no_payment_and_keeps_full_ar(): void
    {
        [$sale, , $customer] = $this->draft('credit', '500.00', '1000.00');

        $this->assertSame(['0.00', '500.0000', 'unpaid'], $this->settlement($sale));
        $this->assertSame(0, $sale->payments()->count());
        $this->approve($sale, '0.00', '500.00', 'unpaid');
        $this->assertSame('500.0000', app(CustomerBalance::class)->for($customer));
    }

    public function test_partial_cash_keeps_only_the_collected_amount_and_ar(): void
    {
        [$sale, $cashier, $customer, $method] = $this->draft('credit', '500.00', '1000.00');
        $key = 'POS-CREDIT-PARTIAL-'.Str::uuid();
        $payment = app(CapturePaymentAction::class)->execute($cashier, $sale, $method, '200.00', $key, '200.00');

        $this->assertSame('200.00', (string) $payment->amount);
        $this->assertSame($payment->id, app(CapturePaymentAction::class)->execute($cashier, $sale, $method, '200.00', $key, '200.00')->id);
        $this->assertSame(1, $sale->payments()->count());
        $this->assertSame(['200.00', '300.0000', 'partial'], $this->settlement($sale));
        $this->approve($sale, '200.00', '300.00', 'partial');
        $this->assertSame('300.0000', app(CustomerBalance::class)->for($customer));
    }

    public function test_full_cash_still_settles_a_cash_customer(): void
    {
        [$sale, $cashier, , $method] = $this->draft('cash', '500.00');
        $payment = app(CapturePaymentAction::class)->execute($cashier, $sale, $method, '0.00', 'POS-CASH-FULL-'.Str::uuid(), '500.00');

        $this->assertSame('500.00', (string) $payment->amount);
        $this->assertSame(['500.00', '0.0000', 'paid'], $this->settlement($sale));
    }

    public function test_credit_limit_rejects_unpaid_and_partial_excess(): void
    {
        [$sale, $cashier, , $method] = $this->draft('credit', '500.00', '250.00');
        try {
            $this->settlement($sale);
            $this->fail('Unpaid sale exceeded the customer credit limit.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('credit limit', strtolower($exception->getMessage()));
        }

        app(CapturePaymentAction::class)->execute($cashier, $sale, $method, '200.00', 'POS-LIMIT-PARTIAL-'.Str::uuid(), '200.00');
        $this->expectException(InvalidArgumentException::class);
        $this->settlement($sale);
    }

    public function test_cash_customer_cannot_leave_a_partial_balance(): void
    {
        [$sale, $cashier, , $method] = $this->draft('cash', '500.00');
        $this->expectException(InvalidArgumentException::class);
        app(CapturePaymentAction::class)->execute($cashier, $sale, $method, '200.00', 'POS-CASH-UNDER-'.Str::uuid(), '200.00');
    }

    public function test_cash_customer_cannot_complete_with_zero_payment(): void
    {
        [$sale] = $this->draft('cash', '500.00');

        $this->expectException(InvalidArgumentException::class);
        $this->settlement($sale);
    }

    /** @return array{Sale, User, Customer, PaymentMethod} */
    private function draft(string $type, string $payable, ?string $limit = null): array
    {
        $now = now();
        $cashier = User::factory()->create(['is_super_admin' => true, 'status' => 'active']);
        $company = DB::table('companies')->insertGetId(['code' => 'POS-'.Str::random(8), 'name_ar' => 'اختبار', 'name_en' => 'Test', 'currency_code' => 'EGP', 'created_at' => $now, 'updated_at' => $now]);
        $branch = DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'POS-'.Str::random(8), 'name_ar' => 'فرع', 'name_en' => 'Branch', 'created_at' => $now, 'updated_at' => $now]);
        $store = DB::table('stores')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'code' => 'POS-'.Str::random(8), 'type' => 'selling', 'name_ar' => 'مخزن', 'name_en' => 'Store', 'created_at' => $now, 'updated_at' => $now]);
        $customer = Customer::query()->create(['public_id' => (string) Str::uuid(), 'name_ar' => 'عميل', 'name_en' => 'Customer', 'status' => 'active', 'customer_type' => $type, 'credit_limit' => $limit, 'created_by' => $cashier->id, 'created_branch_id' => $branch, 'created_store_id' => $store, 'idempotency_key' => (string) Str::uuid()]);
        $method = PaymentMethod::query()->create(['code' => 'POS-'.Str::random(8), 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash', 'status' => 'active']);
        $sale = Sale::query()->create(['branch_id' => $branch, 'store_id' => $store, 'cashier_id' => $cashier->id, 'customer_id' => $customer->id, 'status' => 'draft', 'idempotency_key' => (string) Str::uuid(), 'currency_code' => 'EGP', 'subtotal' => $payable, 'total' => $payable, 'payable_total' => $payable, 'paid_total' => '0.00', 'outstanding_amount' => $payable, 'payment_status' => 'unpaid']);

        return [$sale, $cashier, $customer, $method];
    }

    private function settlement(Sale $sale): array
    {
        return (new ReflectionMethod(RetailSaleAction::class, 'settlement'))->invoke(app(RetailSaleAction::class), $sale);
    }

    private function approve(Sale $sale, string $paid, string $outstanding, string $status): void
    {
        $sale->update(['status' => 'approved', 'paid_total' => $paid, 'outstanding_amount' => $outstanding, 'payment_status' => $status, 'approved_at' => now()]);
    }
}
