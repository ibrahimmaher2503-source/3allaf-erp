<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\CashControl\Models\CashAccount;
use App\Modules\CashControl\Models\CashTransaction;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Customer\Actions\RecordCustomerReceiptAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Support\CustomerBalance;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\RecordSupplierPaymentAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\SupplierPayment;
use App\Modules\Purchasing\Support\SupplierBalance;
use App\Modules\Retail\Models\Sale;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use InvalidArgumentException;
use Livewire\Livewire;

final class P08CreditSettlementTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_customer_fifo_proposal_posts_exact_example_and_manual_allocation_remains_editable(): void
    {
        [$actor, $company, $store, $bank] = $this->context();
        $customer = Customer::query()->create([
            'name_ar' => 'عميل آجل', 'name_en' => 'Credit customer', 'status' => 'active', 'customer_type' => 'credit', 'credit_limit' => '10000.0000',
            'created_by' => $actor->id, 'created_branch_id' => $store->branch_id, 'created_store_id' => $store->id, 'idempotency_key' => (string) str()->uuid(),
        ]);
        $sales = collect([1000, 1500, 2000])->map(fn (int $total, int $index): Sale => $this->sale($actor, $customer, $store, $total, 'INV-00'.($index + 1), '2026-09-0'.($index + 1)));
        $action = app(RecordCustomerReceiptAction::class);

        $proposal = $action->proposeAllocations($actor, $customer, $store, 'EGP', '2200');
        self::assertSame([$sales[0]->id => '1000.0000', $sales[1]->id => '1200.0000'], $proposal);
        $action->execute($actor, $customer, $bank, '2200', $proposal, 'P08-AR-FIFO');

        $balance = app(CustomerBalance::class);
        self::assertSame(['0.0000', 'paid'], [$balance->outstandingForSale($sales[0]), $balance->paymentStatusForSale($sales[0])]);
        self::assertSame(['300.0000', 'partial'], [$balance->outstandingForSale($sales[1]), $balance->paymentStatusForSale($sales[1])]);
        self::assertSame(['2000.0000', 'unpaid'], [$balance->outstandingForSale($sales[2]), $balance->paymentStatusForSale($sales[2])]);

        $manual = $action->execute($actor, $customer, $bank, '100', [$sales[2]->id => '100'], 'P08-AR-MANUAL');
        self::assertSame([$sales[2]->id], $manual->allocations->pluck('sale_id')->all());
        self::assertSame('2200.0000', $balance->for($customer, $company->currency_code));
    }

    public function test_supplier_fifo_example_replays_with_one_cash_movement(): void
    {
        [$actor, $company, $store, , $cash, $account] = $this->context();
        $supplier = Supplier::query()->create(['code' => 'P08-SUP', 'name_ar' => 'مورد آجل', 'name_en' => 'Credit supplier', 'status' => 'active']);
        $invoices = collect([10000, 15000, 7000])->map(fn (int $total, int $index): PurchaseInvoice => $this->invoice($actor, $supplier, $store, $total, 'PINV-00'.($index + 1), '2026-09-0'.($index + 1)));
        $action = app(RecordSupplierPaymentAction::class);

        $proposal = $action->proposeAllocations($actor, $supplier, $company, 'EGP', '18000');
        self::assertSame([$invoices[0]->id => '10000.0000', $invoices[1]->id => '8000.0000'], $proposal);
        $allocations = collect($proposal)->map(fn (string $amount, int $id): array => ['purchase_invoice_id' => $id, 'amount' => $amount])->values()->all();
        $payment = $action->execute($actor, $supplier, $cash, '18000', $allocations, 'P08-AP-FIFO', cashAccountId: $account->id);
        self::assertSame($payment->id, $action->execute($actor, $supplier, $cash, '18000', $allocations, 'P08-AP-FIFO', cashAccountId: $account->id)->id);

        $balance = app(SupplierBalance::class);
        self::assertSame(['0.0000', 'paid'], [$balance->outstandingForInvoice($invoices[0]), $balance->paymentStatusForInvoice($invoices[0])]);
        self::assertSame(['7000.0000', 'partially_paid'], [$balance->outstandingForInvoice($invoices[1]), $balance->paymentStatusForInvoice($invoices[1])]);
        self::assertSame(['7000.0000', 'unpaid'], [$balance->outstandingForInvoice($invoices[2]), $balance->paymentStatusForInvoice($invoices[2])]);
        self::assertSame(1, CashTransaction::query()->where('source_type', SupplierPayment::class)->where('source_id', $payment->id)->count());
        self::assertSame('-18000.0000', (string) CashTransaction::query()->where('source_type', SupplierPayment::class)->where('source_id', $payment->id)->value('amount'));
    }

    public function test_supplier_payment_rejects_cross_company_allocations_before_writing(): void
    {
        [$actor, , $firstStore, $bank] = $this->context();
        [, , $secondStore] = $this->context($actor);
        $supplier = Supplier::query()->create(['code' => 'P08-CROSS', 'name_ar' => 'مورد', 'name_en' => 'Supplier', 'status' => 'active']);
        $first = $this->invoice($actor, $supplier, $firstStore, 100, 'P08-A', '2026-09-01');
        $second = $this->invoice($actor, $supplier, $secondStore, 100, 'P08-B', '2026-09-02');

        try {
            app(RecordSupplierPaymentAction::class)->execute($actor, $supplier, $bank, '200', [
                ['purchase_invoice_id' => $first->id, 'amount' => '100'],
                ['purchase_invoice_id' => $second->id, 'amount' => '100'],
            ], 'P08-CROSS-COMPANY');
            self::fail('Cross-company supplier allocations were accepted.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('one company', $exception->getMessage());
        }

        self::assertSame(0, SupplierPayment::query()->where('idempotency_key', 'P08-CROSS-COMPANY')->count());
    }

    public function test_customer_profile_exposes_authoritative_receivable_and_collection_action(): void
    {
        [$actor, , $store] = $this->context();
        $customer = Customer::query()->create([
            'name_ar' => 'عميل الملف', 'name_en' => 'Profile customer', 'phone_display' => '01012345678', 'status' => 'active', 'customer_type' => 'credit', 'credit_limit' => '5000.0000',
            'created_by' => $actor->id, 'created_branch_id' => $store->branch_id, 'created_store_id' => $store->id, 'idempotency_key' => (string) str()->uuid(),
        ]);
        $this->sale($actor, $customer, $store, 1000, 'PROFILE-AR-1', '2026-09-01');

        $this->actingAs($actor)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Accounts Receivable / Customer Account')
            ->assertSee('PROFILE-AR-1')
            ->assertSee('Receive Payment / تحصيل مبلغ')
            ->assertSee(route('feed-store.operations', ['operation' => 'customer_receipt', 'customer_id' => $customer->id, 'collection_store_id' => $store->id, 'currency_code' => 'EGP']));
    }

    public function test_supplier_profile_exposes_authoritative_payable_and_payment_action(): void
    {
        [$actor, , $store] = $this->context();
        $supplier = Supplier::query()->create(['code' => 'PROFILE-SUP', 'name_ar' => 'مورد الملف', 'name_en' => 'Profile supplier', 'phone' => '01098765432', 'status' => 'active']);
        $this->invoice($actor, $supplier, $store, 2500, 'PROFILE-AP-1', '2026-09-01');

        $this->actingAs($actor);
        Livewire::test('catalog::suppliers')
            ->call('openSupplierDetailModal', $supplier->id)
            ->set('detailTab', 'payables')
            ->assertSee('Accounts Payable / Supplier Account')
            ->assertSee('PROFILE-AP-1')
            ->assertSee('Pay Supplier / سداد مورد');
    }

    /** @return array{User, Company, Store, PaymentMethod, PaymentMethod, CashAccount} */
    private function context(?User $actor = null): array
    {
        $actor ??= User::factory()->create(['status' => 'active', 'is_super_admin' => true, 'email_verified_at' => now()]);
        $company = Company::factory()->create(['currency_code' => 'EGP']);
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'type' => 'selling']);
        $bank = PaymentMethod::query()->create(['code' => 'bank-'.str()->random(8), 'name_ar' => 'تحويل', 'name_en' => 'Bank', 'type' => 'manual', 'status' => 'active']);
        $cash = PaymentMethod::query()->create(['code' => 'cash-'.str()->random(8), 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash', 'status' => 'active']);
        $account = CashAccount::query()->create(['company_id' => $company->id, 'code' => 'cash-'.str()->random(8), 'name_ar' => 'خزينة', 'name_en' => 'Cash', 'type' => 'cash', 'currency_code' => 'EGP', 'status' => 'active']);

        return [$actor, $company, $store, $bank, $cash, $account];
    }

    private function sale(User $actor, Customer $customer, Store $store, int $total, string $number, string $approvedAt): Sale
    {
        return Sale::query()->create([
            'branch_id' => $store->branch_id, 'store_id' => $store->id, 'cashier_id' => $actor->id, 'customer_id' => $customer->id,
            'document_number' => $number, 'status' => 'approved', 'idempotency_key' => (string) str()->uuid(), 'subtotal' => $total,
            'total' => $total, 'paid_total' => 0, 'outstanding_amount' => $total, 'payment_status' => 'unpaid', 'payable_total' => $total,
            'currency_code' => 'EGP', 'approved_at' => $approvedAt.' 10:00:00',
        ]);
    }

    private function invoice(User $actor, Supplier $supplier, Store $store, int $total, string $number, string $date): PurchaseInvoice
    {
        return PurchaseInvoice::query()->create([
            'invoice_number' => $number, 'supplier_id' => $supplier->id, 'store_id' => $store->id, 'invoice_date' => $date,
            'due_date' => $date, 'currency_code' => 'EGP', 'status' => 'approved', 'subtotal' => $total, 'total_amount' => $total,
            'idempotency_key' => (string) str()->uuid(), 'approved_at' => $date.' 10:00:00', 'approved_by' => $actor->id,
            'created_by' => $actor->id, 'updated_by' => $actor->id,
        ]);
    }
}
