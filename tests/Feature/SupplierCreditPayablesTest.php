<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\ApprovePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\PostSupplierAccountAdjustmentAction;
use App\Modules\Purchasing\Actions\RecordSupplierPaymentAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceChargesAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseInvoiceDistribution;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\SupplierPayment;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use App\Modules\Purchasing\Support\SupplierBalance;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SupplierCreditPayablesTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_supplier_payable_allocations_are_decimal_safe_bounded_and_idempotent(): void
    {
        [$actor, $supplier, $method, $invoice] = $this->fixtures();
        $this->actingAs($actor);
        $action = app(RecordSupplierPaymentAction::class);
        $balance = app(SupplierBalance::class);

        $first = $action->execute($actor, $supplier, $method, '20000', [['purchase_invoice_id' => $invoice->id, 'amount' => '20000']], 'AP-INITIAL-'.$invoice->id);
        self::assertSame('12500.0000', $balance->outstandingForInvoice($invoice->fresh()));
        self::assertSame('partially_paid', $invoice->fresh()->paymentStatus());
        self::assertNull($first->cash_account_id);

        $replay = $action->execute($actor, $supplier, $method, '20000', [['purchase_invoice_id' => $invoice->id, 'amount' => '20000']], 'AP-INITIAL-'.$invoice->id);
        self::assertSame($first->id, $replay->id);
        self::assertSame(1, SupplierPayment::query()->where('idempotency_key', 'AP-INITIAL-'.$invoice->id)->count());

        $action->execute($actor, $supplier, $method, '5000', [['purchase_invoice_id' => $invoice->id, 'amount' => '5000']], 'AP-LATER-'.$invoice->id);
        self::assertSame('7500.0000', $balance->outstandingForInvoice($invoice->fresh()));
        self::assertSame('7500.0000', $balance->for($supplier));

        $this->expectException(InvalidArgumentException::class);
        $action->execute($actor, $supplier, $method, '8000', [['purchase_invoice_id' => $invoice->id, 'amount' => '8000']], 'AP-OVERPAY-'.$invoice->id);
    }

    public function test_supplier_adjustments_change_balance_and_replay_safely(): void
    {
        [$actor, $supplier, , $invoice] = $this->fixtures();
        $this->actingAs($actor);
        $action = app(PostSupplierAccountAdjustmentAction::class);
        $balance = app(SupplierBalance::class);

        $debit = $action->execute($actor, $supplier, 'debit', '500', 'Freight correction', 'AP-DEBIT-'.$invoice->id, $invoice);
        self::assertSame('33000.0000', $balance->for($supplier));
        self::assertSame($debit->id, $action->execute($actor, $supplier, 'debit', '500', 'Freight correction', 'AP-DEBIT-'.$invoice->id, $invoice)->id);

        $action->execute($actor, $supplier, 'credit', '500', 'Supplier credit note', 'AP-CREDIT-'.$invoice->id, $invoice);
        self::assertSame('32500.0000', $balance->for($supplier));

        $reason = SupplierReturnReason::query()->create(['code' => 'AP-RETURN-'.str()->random(6), 'label_ar' => 'مرتجع', 'label_en' => 'Return', 'is_active' => true]);
        PurchaseReturn::query()->create([
            'return_number' => 'AP-RET-'.str()->random(8), 'supplier_id' => $supplier->id, 'purchase_invoice_id' => $invoice->id,
            'store_id' => $invoice->store_id, 'reason_id' => $reason->id, 'return_date' => '2026-09-15', 'status' => 'approved',
            'subtotal' => '2500.0000', 'total_amount' => '2500.0000', 'idempotency_key' => (string) str()->uuid(), 'approved_at' => now(),
        ]);
        self::assertSame('30000.0000', $balance->for($supplier));
    }

    public function test_purchase_charge_treatment_defaults_to_landed_cost_and_rejects_unknown_values(): void
    {
        [$actor, , , $invoice] = $this->fixtures();
        $this->actingAs($actor);

        DB::table('purchase_invoice_charges')->insert([
            'purchase_invoice_id' => $invoice->id,
            'charge_type' => 'transport',
            'amount' => '125.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        self::assertSame('landed_cost', (string) DB::table('purchase_invoice_charges')->where('purchase_invoice_id', $invoice->id)->value('accounting_treatment'));

        DB::table('purchase_invoice_charges')->insert([
            'purchase_invoice_id' => $invoice->id,
            'charge_type' => 'loading',
            'amount' => '25.0000',
            'accounting_treatment' => 'period_expense',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        self::assertSame(1, DB::table('purchase_invoice_charges')->where('purchase_invoice_id', $invoice->id)->where('accounting_treatment', 'period_expense')->count());

        $this->expectException(InvalidArgumentException::class);
        app(SavePurchaseInvoiceChargesAction::class)->execute($invoice->id, [[
            'charge_type' => 'other',
            'amount' => '1.0000',
            'accounting_treatment' => 'unknown',
        ]]);
    }

    public function test_purchase_approval_excludes_period_expense_and_snapshots_supplier_terms(): void
    {
        [$actor, $supplier, $invoice, $line] = $this->approvalFixture(limit: '5000.0000');
        $supplier->update([
            'payment_terms' => 'Net 30',
            'payment_policy' => 'credit',
            'credit_days' => 30,
            'credit_limit' => '5000.0000',
        ]);
        DB::table('purchase_invoice_charges')->insert([
            ['purchase_invoice_id' => $invoice->id, 'charge_type' => 'transport', 'amount' => '100.0000', 'accounting_treatment' => 'landed_cost', 'created_at' => now(), 'updated_at' => now()],
            ['purchase_invoice_id' => $invoice->id, 'charge_type' => 'loading', 'amount' => '75.0000', 'accounting_treatment' => 'period_expense', 'created_at' => now(), 'updated_at' => now()],
        ]);
        self::assertSame(['landed_cost', 'period_expense'], $invoice->fresh('charges')->charges->pluck('accounting_treatment')->sort()->values()->all());

        $this->actingAs($actor);
        $approved = app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id);
        $line = $line->fresh();
        $supplier->update(['payment_terms' => 'Net 90', 'credit_days' => 90, 'credit_limit' => '999999.0000']);

        self::assertSame('100.0000', (string) $line->allocated_charge_amount);
        self::assertSame('11.000000', (string) $line->inventory_unit_cost);
        self::assertSame('Net 30', $approved->payment_terms_snapshot);
        self::assertSame('credit', $approved->payment_policy_snapshot);
        self::assertSame(30, $approved->credit_days_snapshot);
        self::assertSame('5000.0000', (string) $approved->credit_limit_snapshot);
        self::assertSame('2026-10-15', $approved->due_date?->toDateString());
        self::assertNotNull($approved->terms_snapshot_at);
        self::assertSame('Net 30', $approved->fresh()->payment_terms_snapshot);
    }

    public function test_purchase_approval_rejects_supplier_credit_limit_before_posting(): void
    {
        [$actor, $supplier, $invoice] = $this->approvalFixture(limit: '999.0000');
        $this->actingAs($actor);
        self::assertSame('999.0000', (string) $supplier->fresh()->credit_limit);
        self::assertSame('1000.0000', (string) $invoice->fresh()->total_amount);
        self::assertSame('0.0000', app(SupplierBalance::class)->for($supplier));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Supplier credit limit would be exceeded');
        app(ApprovePurchaseInvoiceAction::class)->execute($invoice->id);
    }

    /** @return array{User, Supplier, PaymentMethod, PurchaseInvoice} */
    private function fixtures(): array
    {
        $actor = User::factory()->create(['status' => 'active']);
        $actor->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $store = Store::factory()->warehouse()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $supplier = Supplier::query()->create([
            'code' => 'AP-'.str()->upper(str()->random(8)), 'name_ar' => 'مورد آجل', 'name_en' => 'Credit Supplier',
            'status' => 'active', 'payment_policy' => 'credit', 'credit_days' => 30, 'credit_limit' => '50000.0000',
        ]);
        $method = PaymentMethod::query()->create([
            'code' => 'ap-'.str()->lower(str()->random(8)), 'name_ar' => 'تحويل', 'name_en' => 'Transfer',
            'type' => 'manual', 'requires_evidence' => false, 'offline_eligible' => false, 'status' => 'active',
        ]);
        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'AP-INV-'.str()->upper(str()->random(8)), 'supplier_id' => $supplier->id,
            'store_id' => $store->id, 'invoice_date' => '2026-09-15', 'currency_code' => 'EGP', 'status' => 'approved',
            'subtotal' => '32500.0000', 'tax_amount' => '0.0000', 'discount_amount' => '0.0000', 'total_amount' => '32500.0000',
            'idempotency_key' => (string) str()->uuid(), 'approved_at' => now(), 'approved_by' => $actor->id,
            'created_by' => $actor->id, 'updated_by' => $actor->id,
        ]);

        return [$actor, $supplier, $method, $invoice];
    }

    /** @return array{User, Supplier, PurchaseInvoice, PurchaseInvoiceLine|null} */
    private function approvalFixture(string $limit): array
    {
        $actor = User::factory()->create(['status' => 'active', 'is_super_admin' => true]);
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $source = Store::factory()->warehouse()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $destination = Store::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $supplier = Supplier::query()->create([
            'code' => 'APR-'.str()->upper(str()->random(8)), 'name_ar' => 'مورد اختبار', 'name_en' => 'Approval Supplier',
            'status' => 'active', 'payment_policy' => 'credit', 'credit_days' => 30, 'credit_limit' => $limit,
        ]);
        DB::table('document_sequences')->insert([
            'document_type' => 'stock_transfer',
            'scope_type' => 'branch',
            'scope_id' => $branch->id,
            'scope_key' => 'branch:'.$branch->id,
            'prefix' => 'ST-',
            'padding_length' => 6,
            'next_value' => 1,
            'reset_rule' => 'never',
            'status' => 'active',
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $product = Product::factory()->create(['average_cost' => '10.00', 'sale_price' => '15.00']);
        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'APR-INV-'.str()->upper(str()->random(8)), 'supplier_id' => $supplier->id,
            'store_id' => $source->id, 'invoice_date' => '2026-09-15', 'currency_code' => 'EGP', 'status' => 'submitted',
            'subtotal' => '1000.0000', 'tax_amount' => '0.0000', 'discount_amount' => '0.0000', 'total_amount' => '1000.0000',
            'idempotency_key' => (string) str()->uuid(), 'created_at' => now(), 'updated_at' => now(), 'created_by' => $actor->id,
        ]);
        $line = PurchaseInvoiceLine::query()->create([
            'purchase_invoice_id' => $invoice->id, 'product_id' => $product->id, 'quantity' => '100.000000',
            'quantity_received' => '0.000000', 'unit_cost' => '10.0000', 'discount_value' => '0.0000',
            'discount_amount' => '0.0000', 'tax_rate' => '0.0000', 'tax_amount' => '0.0000', 'subtotal' => '1000.0000',
            'line_total' => '1000.0000', 'base_consumer_price' => '15.000',
        ]);
        PurchaseInvoiceDistribution::query()->create([
            'purchase_invoice_id' => $invoice->id, 'purchase_invoice_line_id' => $line->id,
            'destination_store_id' => $destination->id, 'quantity' => '100.000000', 'price_is_override' => false,
        ]);
        ApprovalRecord::query()->create([
            'source_type' => 'purchase_invoices', 'source_id' => (string) $invoice->id, 'source_version' => '0',
            'requested_action' => 'approve', 'approval_state' => ApprovalState::Pending->value, 'requester_id' => $actor->id,
            'branch_id' => $branch->id, 'store_id' => $source->id, 'requested_at' => now(),
            'idempotency_key' => (string) str()->uuid(), 'pending_key' => hash('sha256', 'approval-'.$invoice->id),
        ]);

        return [$actor, $supplier, $invoice, $line];
    }
}
