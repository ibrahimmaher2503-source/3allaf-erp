<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Customer\Actions\PostCustomerAccountAdjustmentAction;
use App\Modules\Customer\Actions\RecordCustomerReceiptAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Support\CustomerBalance;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Models\Sale;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class CustomerCreditReceiptsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        restore_exception_handler();
        restore_error_handler();
        parent::tearDown();
    }

    public function test_partial_sale_receipt_adjustment_and_idempotency_keep_ar_derived(): void
    {
        $now = now();
        $userId = DB::table('users')->insertGetId(['name' => 'AR Test', 'email' => 'ar@test.invalid', 'password' => 'x', 'is_super_admin' => true, 'created_at' => $now, 'updated_at' => $now]);
        $companyId = DB::table('companies')->insertGetId(['code' => 'AR-T', 'name_ar' => 'اختبار', 'name_en' => 'AR Test', 'currency_code' => 'EGP', 'created_at' => $now, 'updated_at' => $now]);
        $branchId = DB::table('branches')->insertGetId(['company_id' => $companyId, 'code' => 'AR-B', 'name_ar' => 'فرع', 'name_en' => 'Branch', 'created_at' => $now, 'updated_at' => $now]);
        $storeId = DB::table('stores')->insertGetId(['company_id' => $companyId, 'branch_id' => $branchId, 'code' => 'AR-S', 'type' => 'selling', 'name_ar' => 'مخزن', 'name_en' => 'Store', 'created_at' => $now, 'updated_at' => $now]);
        $methodId = DB::table('payment_methods')->insertGetId(['code' => 'AR-CASH', 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash', 'created_at' => $now, 'updated_at' => $now]);
        $customerId = DB::table('customers')->insertGetId(['public_id' => (string) Str::uuid(), 'phone_normalized' => null, 'phone_display' => null, 'name_ar' => 'عميل آجل', 'name_en' => 'Credit Customer', 'status' => 'active', 'created_by' => $userId, 'created_branch_id' => $branchId, 'created_store_id' => $storeId, 'customer_type' => 'credit', 'credit_limit' => '5000.0000', 'idempotency_key' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now]);
        DB::table('customers')->insert(['public_id' => (string) Str::uuid(), 'phone_normalized' => null, 'phone_display' => null, 'name_ar' => 'عميل نقدي', 'name_en' => 'Cash Customer', 'status' => 'active', 'created_by' => $userId, 'created_branch_id' => $branchId, 'created_store_id' => $storeId, 'customer_type' => 'cash', 'idempotency_key' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now]);
        self::assertSame(2, DB::table('customers')->whereNull('phone_normalized')->count());
        $saleId = DB::table('sales')->insertGetId(['branch_id' => $branchId, 'store_id' => $storeId, 'cashier_id' => $userId, 'customer_id' => $customerId, 'document_number' => 'AR-001', 'status' => 'draft', 'idempotency_key' => (string) Str::uuid(), 'subtotal' => 3400, 'total' => 3400, 'paid_total' => 0, 'outstanding_amount' => 3400, 'payment_status' => 'unpaid', 'payable_total' => 3400, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('sale_payments')->insert(['sale_id' => $saleId, 'payment_method_id' => $methodId, 'method_code' => 'AR-CASH', 'method_type' => 'cash', 'amount' => 2000, 'change_amount' => 0, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);

        $user = User::query()->findOrFail($userId);
        $customer = Customer::query()->findOrFail($customerId);
        $method = PaymentMethod::query()->findOrFail($methodId);
        $settlement = (new ReflectionMethod(RetailSaleAction::class, 'settlement'))->invoke(app(RetailSaleAction::class), Sale::query()->with('customer')->findOrFail($saleId));
        self::assertSame(['2000.00', '1400.0000', 'partial'], $settlement);
        DB::table('sales')->where('id', $saleId)->update(['status' => 'approved', 'paid_total' => 2000, 'outstanding_amount' => 1400, 'payment_status' => 'partial', 'approved_at' => $now]);
        self::assertSame('1400.0000', app(CustomerBalance::class)->for($customer));
        $receipt = app(RecordCustomerReceiptAction::class)->execute($user, $customer, $method, '500', [$saleId => '500'], 'AR-RECEIPT-1');
        self::assertSame('900.0000', app(CustomerBalance::class)->for($customer));
        self::assertSame($receipt->id, app(RecordCustomerReceiptAction::class)->execute($user, $customer, $method, '500', [$saleId => '500'], 'AR-RECEIPT-1')->id);

        DB::table('retail_returns')->insert(['branch_id' => $branchId, 'store_id' => $storeId, 'cashier_id' => $userId, 'customer_id' => $customerId, 'source_sale_id' => $saleId, 'status' => 'completed', 'settlement_type' => 'original_tender', 'reason' => 'AR return', 'settlement_value' => 100, 'idempotency_key' => (string) Str::uuid(), 'completed_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        self::assertSame('800.0000', app(CustomerBalance::class)->for($customer));

        app(PostCustomerAccountAdjustmentAction::class)->execute($user, $customer, '100', 'Debit correction', 'AR-ADJ-1');
        self::assertSame('900.0000', app(CustomerBalance::class)->for($customer));

        $this->expectException(InvalidArgumentException::class);
        app(RecordCustomerReceiptAction::class)->execute($user, $customer, $method, '801', [$saleId => '801'], 'AR-RECEIPT-OVER');
    }

    public function test_credit_customer_may_pay_part_of_a_sale_in_cash(): void
    {
        $cash = new PaymentMethod(['code' => 'CASH', 'type' => 'cash']);
        $allocation = (new ReflectionMethod(RetailSaleAction::class, 'validatedTenderAllocation'))->invoke(
            app(RetailSaleAction::class),
            [['method' => $cash, 'tendered' => '2000.00']],
            '3400.00',
            true,
        );

        self::assertSame('2000.00', $allocation[0]['amount']);
        self::assertSame('2000.00', $allocation[0]['tendered']);
    }
}
