<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\CashControl\Actions\RecordExpenseAction;
use App\Modules\CashControl\Actions\RecordCashTransactionAction;
use App\Modules\CashControl\Models\CashAccount;
use App\Modules\CashControl\Models\ExpenseCategory;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Customer\Actions\PostCustomerAccountAdjustmentAction;
use App\Modules\Customer\Actions\RecordCustomerReceiptAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\UserStoreScope;
use App\Modules\Purchasing\Actions\PostSupplierAccountAdjustmentAction;
use App\Modules\Purchasing\Actions\RecordSupplierPaymentAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Retail\Models\Sale;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use InvalidArgumentException;

final class FeedStoreFinancialScopeTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_financial_actions_reject_records_outside_the_actor_store_scope(): void
    {
        $actor = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        [, $storeA] = $this->companyAndStore();
        [$companyB, $storeB] = $this->companyAndStore();
        UserStoreScope::query()->create(['user_id' => $actor->id, 'store_id' => $storeA->id, 'status' => 'active']);
        $this->grant($actor, ['customers.edit', 'customers.sensitive', 'purchase_invoices.approve', 'shifts_cash_movements.approve']);

        $method = PaymentMethod::query()->create(['code' => 'scope-'.str()->random(8), 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash', 'status' => 'active']);
        $customer = Customer::query()->create(['name_ar' => 'عميل خارج النطاق', 'name_en' => 'Out-of-scope customer', 'status' => 'active', 'created_by' => $actor->id, 'created_branch_id' => $storeB->branch_id, 'created_store_id' => $storeB->id, 'idempotency_key' => (string) str()->uuid()]);
        $sale = Sale::query()->create(['branch_id' => $storeB->branch_id, 'store_id' => $storeB->id, 'cashier_id' => $actor->id, 'customer_id' => $customer->id, 'status' => 'approved', 'idempotency_key' => (string) str()->uuid(), 'subtotal' => 100, 'total' => 100, 'paid_total' => 0, 'outstanding_amount' => 100, 'payment_status' => 'unpaid', 'payable_total' => 100, 'approved_at' => now()]);
        $supplier = Supplier::query()->create(['code' => 'scope-'.str()->random(8), 'name_ar' => 'مورد', 'name_en' => 'Supplier', 'status' => 'active']);
        $invoice = PurchaseInvoice::query()->create(['invoice_number' => 'scope-'.str()->random(8), 'supplier_id' => $supplier->id, 'store_id' => $storeB->id, 'invoice_date' => now()->toDateString(), 'currency_code' => 'EGP', 'status' => 'approved', 'subtotal' => 100, 'total_amount' => 100, 'idempotency_key' => (string) str()->uuid(), 'approved_at' => now(), 'approved_by' => $actor->id, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $category = ExpenseCategory::query()->create(['company_id' => $companyB->id, 'code' => 'scope-'.str()->random(8), 'name_ar' => 'مصروف', 'name_en' => 'Expense', 'active' => true]);
        $account = CashAccount::query()->create(['company_id' => $companyB->id, 'code' => 'scope-'.str()->random(8), 'name_ar' => 'خزينة', 'name_en' => 'Treasury', 'type' => 'cash', 'currency_code' => 'EGP', 'status' => 'active']);

        $this->assertScopeRejected(fn () => app(RecordCustomerReceiptAction::class)->execute($actor, $customer, $method, '100', [$sale->id => '100'], 'scope-receipt'));
        $this->assertScopeRejected(fn () => app(PostCustomerAccountAdjustmentAction::class)->execute($actor, $customer, '10', 'Scope check', 'scope-customer-adjustment'));
        $this->assertScopeRejected(fn () => app(RecordSupplierPaymentAction::class)->execute($actor, $supplier, $method, '100', [['purchase_invoice_id' => $invoice->id, 'amount' => '100']], 'scope-payment'));
        $this->assertScopeRejected(fn () => app(PostSupplierAccountAdjustmentAction::class)->execute($actor, $supplier, 'debit', '10', 'Scope check', 'scope-supplier-adjustment', $invoice));
        $this->assertScopeRejected(fn () => app(RecordExpenseAction::class)->execute($actor, $companyB, $category, '10', 'Scope check', 'scope-expense'));
        $this->assertScopeRejected(fn () => app(RecordCashTransactionAction::class)->execute($actor, $account, '10', 'scope_check', 'Scope check', 'scope-cash'));
    }

    /** @return array{Company, Store} */
    private function companyAndStore(): array
    {
        $company = Company::factory()->create(['currency_code' => 'EGP']);
        $branch = Branch::factory()->create(['company_id' => $company->id]);

        return [$company, Store::factory()->warehouse()->create(['company_id' => $company->id, 'branch_id' => $branch->id])];
    }

    /** @param list<string> $codes */
    private function grant(User $actor, array $codes): void
    {
        $role = Role::query()->create(['code' => 'scope-'.str()->random(8), 'name_ar' => 'مراجع نطاق', 'name_en' => 'Scope reviewer', 'status' => 'active']);
        foreach ($codes as $code) {
            $permission = Permission::query()->firstOrCreate(['code' => $code], ['module' => 'feed_store', 'action' => 'approve', 'sensitivity' => 'normal', 'status' => 'active']);
            $role->permissions()->attach($permission);
        }
        $actor->roles()->attach($role);
    }

    private function assertScopeRejected(callable $action): void
    {
        try {
            $action();
            self::fail('The financial action accepted a record outside the actor scope.');
        } catch (ModelNotFoundException|InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
    }
}
