<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\CashControl\Actions\RecordCashTransactionAction;
use App\Modules\CashControl\Actions\RecordExpenseAction;
use App\Modules\CashControl\Models\CashAccount;
use App\Modules\CashControl\Models\Expense;
use App\Modules\CashControl\Models\ExpenseCategory;
use App\Modules\CashControl\Support\CashAccountBalance;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Customer\Actions\RecordCustomerReceiptAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\RecordSupplierPaymentAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\SupplierPayment;
use App\Modules\Retail\Models\Sale;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use InvalidArgumentException;
use LogicException;

final class ExpensesGeneralCashAccountsTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_cash_sale_receipt_supplier_payment_and_expense_reconcile_to_signed_sum(): void
    {
        [$actor, $company, $store, $account, $method] = $this->fixtures();
        $this->actingAs($actor);
        app(RecordCashTransactionAction::class)->execute($actor, $account, '850', 'cash_sale', 'Cash sale', 'CASH-SALE-850');

        $customer = Customer::query()->create([
            'name_ar' => 'عميل نقدي', 'name_en' => 'Cash Customer', 'status' => 'active', 'created_by' => $actor->id,
            'updated_by' => $actor->id, 'created_branch_id' => $store->branch_id, 'created_store_id' => $store->id,
            'idempotency_key' => (string) str()->uuid(),
        ]);
        $sale = Sale::query()->create([
            'branch_id' => $store->branch_id, 'store_id' => $store->id, 'cashier_id' => $actor->id, 'customer_id' => $customer->id,
            'status' => 'approved', 'idempotency_key' => (string) str()->uuid(), 'subtotal' => 500, 'total' => 500,
            'paid_total' => 0, 'outstanding_amount' => 500, 'payment_status' => 'unpaid', 'change_total' => 0,
            'cash_rounding_amount' => 0, 'payable_total' => 500, 'currency_code' => 'EGP', 'approved_at' => now(),
        ]);
        app(RecordCustomerReceiptAction::class)->execute($actor, $customer, $method, '500', [$sale->id => '500'], 'CUSTOMER-CASH-500', cashAccountId: $account->id);

        $supplier = Supplier::query()->create(['code' => 'CASH-SUP-'.str()->random(6), 'name_ar' => 'مورد نقدي', 'name_en' => 'Cash Supplier', 'status' => 'active']);
        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'CASH-INV-'.str()->random(6), 'supplier_id' => $supplier->id, 'store_id' => $store->id,
            'invoice_date' => now()->toDateString(), 'currency_code' => 'EGP', 'status' => 'approved', 'subtotal' => 5000,
            'total_amount' => 5000, 'idempotency_key' => (string) str()->uuid(), 'approved_at' => now(), 'approved_by' => $actor->id,
            'created_by' => $actor->id, 'updated_by' => $actor->id,
        ]);
        app(RecordSupplierPaymentAction::class)->execute($actor, $supplier, $method, '5000', [['purchase_invoice_id' => $invoice->id, 'amount' => '5000']], 'SUPPLIER-CASH-5000', cashAccountId: $account->id);

        $category = ExpenseCategory::query()->create(['company_id' => $company->id, 'code' => 'OTHER', 'name_ar' => 'مصروفات أخرى', 'name_en' => 'Other expenses', 'active' => true]);
        app(RecordExpenseAction::class)->execute($actor, $company, $category, '300', 'Scale maintenance', 'EXPENSE-CASH-300', paymentMethod: $method, cashAccount: $account);

        self::assertSame('-3950.0000', app(CashAccountBalance::class)->for($account));
        self::assertSame(4, $account->transactions()->count());
        self::assertSame(['-5000.0000', '-300.0000', '500.0000', '850.0000'], $account->transactions()->orderBy('amount')->pluck('amount')->all());
    }

    public function test_cash_transactions_replay_reverse_once_and_cannot_be_mutated(): void
    {
        [$actor, , , $account] = $this->fixtures();
        $this->actingAs($actor);
        $action = app(RecordCashTransactionAction::class);
        $original = $action->execute($actor, $account, '1000', 'opening', 'Opening treasury', 'CASH-OPEN-1000');
        self::assertSame($original->id, $action->execute($actor, $account, '1000', 'opening', 'Opening treasury', 'CASH-OPEN-1000')->id);
        $reversal = $action->execute($actor, $account, '-1000', 'reversal', 'Opening correction', 'CASH-OPEN-REV', reversalOf: $original);
        self::assertSame($original->id, $reversal->reversal_of_id);
        self::assertSame('0.0000', app(CashAccountBalance::class)->for($account));

        try {
            $action->execute($actor, $account, '-1000', 'reversal', 'Duplicate correction', 'CASH-OPEN-REV-2', reversalOf: $original);
            self::fail('A second reversal must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame(2, $account->transactions()->count());
        }

        $this->expectException(LogicException::class);
        $original->update(['description' => 'Changed']);
    }

    public function test_cash_supplier_payment_and_expense_require_an_account_without_partial_documents(): void
    {
        [$actor, $company, $store, $account, $method] = $this->fixtures();
        $this->actingAs($actor);
        $supplier = Supplier::query()->create(['code' => 'REQ-SUP-'.str()->random(6), 'name_ar' => 'مورد', 'name_en' => 'Supplier', 'status' => 'active']);
        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'REQ-INV-'.str()->random(6), 'supplier_id' => $supplier->id, 'store_id' => $store->id,
            'invoice_date' => now()->toDateString(), 'currency_code' => 'EGP', 'status' => 'approved', 'subtotal' => 100,
            'total_amount' => 100, 'idempotency_key' => (string) str()->uuid(), 'approved_at' => now(), 'approved_by' => $actor->id,
            'created_by' => $actor->id, 'updated_by' => $actor->id,
        ]);
        $category = ExpenseCategory::query()->create(['company_id' => $company->id, 'code' => 'REQ', 'name_ar' => 'مصروف', 'name_en' => 'Expense', 'active' => true]);

        try {
            app(RecordSupplierPaymentAction::class)->execute($actor, $supplier, $method, '10', [['purchase_invoice_id' => $invoice->id, 'amount' => '10']], 'REQ-PAY-1');
            self::fail('Cash supplier payment without account must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, SupplierPayment::query()->count());
        }
        try {
            app(RecordExpenseAction::class)->execute($actor, $company, $category, '10', 'Cash expense', 'REQ-EXP-1', paymentMethod: $method);
            self::fail('Cash expense without account must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, Expense::query()->count());
        }
        self::assertSame(0, $account->transactions()->count());
    }

    /** @return array{User, Company, Store, CashAccount, PaymentMethod} */
    private function fixtures(): array
    {
        $actor = User::factory()->create(['status' => 'active']);
        $actor->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();
        $company = Company::factory()->create(['currency_code' => 'EGP']);
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->warehouse()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $account = CashAccount::query()->create(['company_id' => $company->id, 'code' => 'MAIN-'.str()->random(5), 'name_ar' => 'خزينة المحل', 'name_en' => 'Main treasury', 'type' => 'cash', 'currency_code' => 'EGP', 'status' => 'active']);
        $method = PaymentMethod::query()->create(['code' => 'cash-'.str()->random(6), 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash', 'requires_evidence' => false, 'offline_eligible' => false, 'status' => 'active']);

        return [$actor, $company, $store, $account, $method];
    }
}
