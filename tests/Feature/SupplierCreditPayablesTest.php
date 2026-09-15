<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\PostSupplierAccountAdjustmentAction;
use App\Modules\Purchasing\Actions\RecordSupplierPaymentAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\SupplierPayment;
use App\Modules\Purchasing\Models\SupplierReturnReason;
use App\Modules\Purchasing\Support\SupplierBalance;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
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
}
