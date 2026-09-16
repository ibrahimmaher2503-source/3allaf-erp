<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Support\CustomerBalance;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

final class RetailReturnArSplitTest extends TestCase
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
        parent::tearDown();
    }

    public function test_unpaid_partial_and_full_returns_split_ar_from_actual_refund(): void
    {
        $now = now();
        $user = DB::table('users')->insertGetId(['name' => 'Return Test', 'email' => 'return@test.invalid', 'password' => 'x', 'is_super_admin' => true, 'created_at' => $now, 'updated_at' => $now]);
        $company = DB::table('companies')->insertGetId(['code' => 'RET-T', 'name_ar' => 'اختبار', 'name_en' => 'Return', 'currency_code' => 'EGP', 'created_at' => $now, 'updated_at' => $now]);
        $branch = DB::table('branches')->insertGetId(['company_id' => $company, 'code' => 'RET-B', 'name_ar' => 'فرع', 'name_en' => 'Branch', 'created_at' => $now, 'updated_at' => $now]);
        $store = DB::table('stores')->insertGetId(['company_id' => $company, 'branch_id' => $branch, 'code' => 'RET-S', 'type' => 'selling', 'name_ar' => 'مخزن', 'name_en' => 'Store', 'created_at' => $now, 'updated_at' => $now]);
        $customer = DB::table('customers')->insertGetId(['public_id' => (string) Str::uuid(), 'name_ar' => 'عميل', 'name_en' => 'Customer', 'status' => 'active', 'created_by' => $user, 'created_branch_id' => $branch, 'created_store_id' => $store, 'customer_type' => 'credit', 'idempotency_key' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now]);
        $method = DB::table('payment_methods')->insertGetId(['code' => 'RET-CASH', 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $sale = DB::table('sales')->insertGetId(['branch_id' => $branch, 'store_id' => $store, 'cashier_id' => $user, 'customer_id' => $customer, 'document_number' => 'RET-001', 'status' => 'approved', 'idempotency_key' => (string) Str::uuid(), 'subtotal' => 1000, 'total' => 1000, 'paid_total' => 400, 'outstanding_amount' => 600, 'payment_status' => 'partial', 'payable_total' => 1000, 'currency_code' => 'EGP', 'approved_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('sale_payments')->insert(['sale_id' => $sale, 'payment_method_id' => $method, 'method_code' => 'RET-CASH', 'method_type' => 'cash', 'amount' => 400, 'change_amount' => 0, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $user, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('retail_returns')->insert(['branch_id' => $branch, 'store_id' => $store, 'cashier_id' => $user, 'customer_id' => $customer, 'source_sale_id' => $sale, 'status' => 'completed', 'settlement_type' => 'cash_refund', 'reason' => 'test', 'settlement_value' => 600, 'ar_reduction_value' => 600, 'actual_refund_value' => 0, 'idempotency_key' => (string) Str::uuid(), 'completed_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        self::assertSame('0.0000', app(CustomerBalance::class)->for(Customer::query()->findOrFail($customer)));
    }
}
