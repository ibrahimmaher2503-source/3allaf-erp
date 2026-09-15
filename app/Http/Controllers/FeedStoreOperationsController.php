<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\CashControl\Actions\RecordExpenseAction;
use App\Modules\CashControl\Models\CashAccount;
use App\Modules\CashControl\Models\CashTransaction;
use App\Modules\CashControl\Models\Expense;
use App\Modules\CashControl\Models\ExpenseCategory;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Customer\Actions\RecordCustomerReceiptAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerReceipt;
use App\Modules\Inventory\Models\InventoryBatch;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\RecordSupplierPaymentAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\SupplierPayment;
use App\Modules\Retail\Models\Sale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Throwable;

final class FeedStoreOperationsController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        Gate::forUser($actor)->authorize('access-feed-store-operations');
        $canCustomerReceipts = Gate::forUser($actor)->allows('customers.edit');
        $canSupplierPayments = Gate::forUser($actor)->allows('purchase_invoices.approve');
        $canCashControl = Gate::forUser($actor)->allows('shifts_cash_movements.approve');
        $canBatchMaintenance = Gate::forUser($actor)->allows('inventory_stock_card.edit');

        $storeIds = Store::query()->visibleTo($actor)->pluck('id');
        $companyIds = Store::query()->whereIn('id', $storeIds)->pluck('company_id')->unique();
        $visibleCustomerIds = Customer::query()->visibleTo($actor)->select('customers.id');

        $sales = $canCustomerReceipts ? Sale::query()
            ->visibleTo($actor)
            ->where('status', 'approved')
            ->withSum('payments as direct_payments_total', 'amount')
            ->withSum(['receiptAllocations as receipt_allocations_total' => fn ($query) => $query->whereHas('receipt', fn ($receipt) => $receipt->where('status', 'approved'))], 'amount')
            ->withSum(['retailReturns as returns_total' => fn ($query) => $query->where('status', 'completed')], 'settlement_value')
            ->latest('approved_at')
            ->limit(100)
            ->get()
            ->map(function (Sale $sale): Sale {
                $outstanding = bcsub(bcsub(bcsub((string) $sale->payable_total, (string) ($sale->direct_payments_total ?? 0), 4), (string) ($sale->receipt_allocations_total ?? 0), 4), (string) ($sale->returns_total ?? 0), 4);
                $sale->setAttribute('current_outstanding', bccomp($outstanding, '0', 4) > 0 ? $outstanding : '0.0000');

                return $sale;
            })
            ->filter(fn (Sale $sale): bool => bccomp((string) $sale->current_outstanding, '0', 4) > 0) : collect();

        $invoices = $canSupplierPayments ? PurchaseInvoice::query()
            ->whereIn('store_id', $storeIds)
            ->where('status', 'approved')
            ->withSum(['supplierPaymentAllocations as payments_total' => fn ($query) => $query->whereHas('payment', fn ($payment) => $payment->where('status', 'approved'))], 'amount')
            ->withSum(['supplierReturns as returns_total' => fn ($query) => $query->where('status', 'approved')], 'total_amount')
            ->withSum(['supplierAccountAdjustments as debits_total' => fn ($query) => $query->where('status', 'approved')->where('direction', 'debit')], 'amount')
            ->withSum(['supplierAccountAdjustments as credits_total' => fn ($query) => $query->where('status', 'approved')->where('direction', 'credit')], 'amount')
            ->latest('approved_at')
            ->limit(100)
            ->get()
            ->map(function (PurchaseInvoice $invoice): PurchaseInvoice {
                $outstanding = bcsub(bcadd(bcsub((string) $invoice->total_amount, (string) ($invoice->returns_total ?? 0), 4), (string) ($invoice->debits_total ?? 0), 4), bcadd((string) ($invoice->payments_total ?? 0), (string) ($invoice->credits_total ?? 0), 4), 4);
                $invoice->setAttribute('current_outstanding', bccomp($outstanding, '0', 4) > 0 ? $outstanding : '0.0000');

                return $invoice;
            })
            ->filter(fn (PurchaseInvoice $invoice): bool => bccomp((string) $invoice->current_outstanding, '0', 4) > 0) : collect();

        return view('feed-store.operations', [
            'customers' => $canCustomerReceipts ? Customer::query()->visibleTo($actor)->where('status', 'active')->orderBy('name_ar')->limit(100)->get() : collect(),
            'sales' => $sales,
            'suppliers' => $canSupplierPayments ? Supplier::query()->where('status', 'active')->orderBy('name_ar')->limit(100)->get() : collect(),
            'invoices' => $invoices,
            'methods' => PaymentMethod::query()->where('status', 'active')->orderBy('name_ar')->get(),
            'accounts' => CashAccount::query()->whereIn('company_id', $companyIds)->where('status', 'active')->orderBy('name_ar')->get(),
            'companies' => $canCashControl ? Company::query()->whereIn('id', $companyIds)->where('status', 'active')->orderBy('name_ar')->get() : collect(),
            'categories' => $canCashControl ? ExpenseCategory::query()->whereIn('company_id', $companyIds)->where('active', true)->orderBy('name_ar')->get() : collect(),
            'products' => $canBatchMaintenance ? Product::query()->where('status', 'active')->where('track_batches', true)->orderBy('name_ar')->limit(100)->get() : collect(),
            'receipts' => $canCustomerReceipts ? CustomerReceipt::query()->with('customer')->whereIn('customer_id', $visibleCustomerIds)->latest()->limit(25)->get() : collect(),
            'payments' => $canSupplierPayments ? SupplierPayment::query()->with('supplier')->whereHas('allocations.purchaseInvoice', fn ($query) => $query->whereIn('store_id', $storeIds))->latest()->limit(25)->get() : collect(),
            'expenses' => $canCashControl ? Expense::query()->with('category')->whereIn('company_id', $companyIds)->latest('expense_date')->limit(25)->get() : collect(),
            'transactions' => $canCashControl ? CashTransaction::query()->with('cashAccount')->whereIn('cash_account_id', CashAccount::query()->whereIn('company_id', $companyIds)->select('id'))->latest('transaction_date')->limit(25)->get() : collect(),
            'batches' => $canBatchMaintenance ? InventoryBatch::query()->with('product')->whereHas('product', fn ($query) => $query->where('status', 'active')->where('track_batches', true))->latest()->limit(25)->get() : collect(),
        ]);
    }

    public function customerReceipt(Request $request, RecordCustomerReceiptAction $action): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->validate($this->paymentRules('customer_id', 'sale_id'));
        try {
            $customer = Customer::query()->visibleTo($actor)->findOrFail($data['customer_id']);
            $sale = Sale::query()->visibleTo($actor)->findOrFail($data['sale_id']);
            $action->execute($actor, $customer, PaymentMethod::query()->findOrFail($data['payment_method_id']), $data['amount'], [$sale->id => $data['amount']], $data['idempotency_key'], $data['date'], $data['reference'] ?? null, $data['notes'] ?? null, $data['evidence_reference'] ?? null, $data['cash_account_id'] ?? null);
            return back()->with('success', __('Customer receipt recorded successfully.'));
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['operation' => \App\Support\UserSafeError::message($exception)]);
        }
    }

    public function supplierPayment(Request $request, RecordSupplierPaymentAction $action): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->validate($this->paymentRules('supplier_id', 'purchase_invoice_id'));
        try {
            $visibleStores = Store::query()->visibleTo($actor)->select('id');
            $invoice = PurchaseInvoice::query()->whereIn('store_id', $visibleStores)->findOrFail($data['purchase_invoice_id']);
            $supplier = Supplier::query()->findOrFail($data['supplier_id']);
            $action->execute($actor, $supplier, PaymentMethod::query()->findOrFail($data['payment_method_id']), $data['amount'], [['purchase_invoice_id' => $invoice->id, 'amount' => $data['amount']]], $data['idempotency_key'], $data['date'], cashAccountId: $data['cash_account_id'] ?? null, reference: $data['reference'] ?? null, evidenceReference: $data['evidence_reference'] ?? null, notes: $data['notes'] ?? null);
            return back()->with('success', __('Supplier payment recorded successfully.'));
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['operation' => \App\Support\UserSafeError::message($exception)]);
        }
    }

    public function expense(Request $request, RecordExpenseAction $action): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->validate(['company_id'=>'required|integer','expense_category_id'=>'required|integer','amount'=>'required|decimal:0,4|gt:0','date'=>'required|date','description'=>'required|string|max:1000','payment_method_id'=>'nullable|integer','cash_account_id'=>'nullable|integer','reference'=>'nullable|string|max:190','idempotency_key'=>'required|uuid']);
        try {
            $companyIds = Store::query()->visibleTo($actor)->pluck('company_id')->unique();
            $company = Company::query()->whereIn('id', $companyIds)->findOrFail($data['company_id']);
            $category = ExpenseCategory::query()->where('company_id', $company->id)->findOrFail($data['expense_category_id']);
            $action->execute($actor, $company, $category, $data['amount'], $data['description'], $data['idempotency_key'], $data['date'], filled($data['payment_method_id'] ?? null) ? PaymentMethod::query()->findOrFail($data['payment_method_id']) : null, filled($data['cash_account_id'] ?? null) ? CashAccount::query()->findOrFail($data['cash_account_id']) : null, $data['reference'] ?? null);
            return back()->with('success', __('Expense recorded successfully.'));
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['operation' => \App\Support\UserSafeError::message($exception)]);
        }
    }

    public function cashAccount(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        Gate::forUser($actor)->authorize('shifts_cash_movements.approve');
        $data = $request->validate(['company_id'=>'required|integer','code'=>['required','string','max:50',Rule::unique('cash_accounts')->where('company_id',$request->integer('company_id'))],'name_ar'=>'required|string|max:255','name_en'=>'nullable|string|max:255','type'=>'required|in:cash,bank,wallet','currency_code'=>'required|alpha:ascii|size:3']);
        $company = Company::query()->whereIn('id', Store::query()->visibleTo($actor)->select('company_id'))->findOrFail($data['company_id']);
        DB::transaction(function () use ($actor, $data): void {
            $account = CashAccount::query()->create($data + ['currency_code' => strtoupper($data['currency_code']), 'status' => 'active']);
            app(RecordAuditEvent::class)->execute('cash_control', 'cash_account_created', $account, after: $account->only(['company_id', 'code', 'type', 'currency_code']), metadata: ['actor_id' => $actor->id]);
        });
        return back()->with('success', __('Cash account created successfully.'));
    }

    public function batch(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        Gate::forUser($actor)->authorize('inventory_stock_card.edit');
        $data = $request->validate(['product_id'=>'required|integer','batch_number'=>['required','string','max:100',Rule::unique('inventory_batches')->where('product_id',$request->integer('product_id'))],'production_date'=>'nullable|date','expiry_date'=>'nullable|date|after_or_equal:production_date']);
        $product = Product::query()->where('status', 'active')->where('track_batches', true)->findOrFail($data['product_id']);
        DB::transaction(function () use ($actor, $data): void {
            $batch = InventoryBatch::query()->create($data + ['status' => 'active']);
            app(RecordAuditEvent::class)->execute('inventory', 'inventory_batch_created', $batch, after: $batch->only(['product_id', 'batch_number', 'production_date', 'expiry_date']), metadata: ['actor_id' => $actor->id]);
        });
        return back()->with('success', __('Inventory batch created successfully.'));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        return $actor;
    }

    /** @return array<string, mixed> */
    private function paymentRules(string $party, string $document): array
    {
        return [$party => 'required|integer', $document => 'required|integer', 'payment_method_id' => 'required|integer', 'cash_account_id' => 'nullable|integer', 'amount' => 'required|decimal:0,4|gt:0', 'date' => 'required|date', 'reference' => 'nullable|string|max:190', 'evidence_reference' => 'nullable|string|max:190', 'notes' => 'nullable|string|max:1000', 'idempotency_key' => 'required|uuid'];
    }
}
