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
use App\Modules\Customer\Support\CustomerBalance;
use App\Modules\Inventory\Models\InventoryBatch;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Actions\RecordSupplierPaymentAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\SupplierPayment;
use App\Modules\Purchasing\Support\SupplierBalance;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Throwable;

final class FeedStoreOperationsController extends Controller
{
    public function index(Request $request, RecordCustomerReceiptAction $customerReceipts, RecordSupplierPaymentAction $supplierPayments): View
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

        $stores = Store::query()->visibleTo($actor)->where('status', 'active')->with([
            'company',
            'sellingStoreMappings' => fn ($query) => $query->where('status', 'active'),
        ])->orderBy('name_ar')->get();
        $primaryStores = $stores->filter(fn (Store $store): bool => $store->sellingStoreMappings->isNotEmpty());
        $defaultStore = $primaryStores->count() === 1 ? $primaryStores->first() : null;
        $paymentCompanies = Company::query()->whereIn('id', $companyIds)->where('status', 'active')->orderBy('name_ar')->get();
        $requestedCustomerId = $request->hasSession() ? (int) $request->old('customer_id', $request->input('customer_id')) : $request->integer('customer_id');
        $selectedCustomer = $canCustomerReceipts && $requestedCustomerId > 0
            ? Customer::query()->visibleTo($actor)->where('status', 'active')->find($requestedCustomerId)
            : null;
        $selectedStore = $selectedCustomer === null ? null : $stores->firstWhere('id', $request->integer('collection_store_id') ?: $defaultStore?->id ?: $selectedCustomer->created_store_id);
        $customerCurrency = strtoupper((string) ($request->string('currency_code')->value() ?: $selectedStore?->company?->currency_code ?: 'EGP'));
        $sales = $selectedCustomer && $selectedStore
            ? app(CustomerBalance::class)->outstandingInvoices($selectedCustomer, $actor, $selectedStore->company, $customerCurrency)
            : collect();
        $customerAllocations = [];

        $selectedSupplier = $canSupplierPayments && $request->integer('supplier_id') > 0
            ? Supplier::query()->where('status', 'active')->find($request->integer('supplier_id'))
            : null;
        $selectedCompany = $paymentCompanies->firstWhere('id', $request->integer('company_id')) ?? ($selectedSupplier ? $paymentCompanies->first() : null);
        $supplierCurrency = strtoupper((string) ($request->string('currency_code')->value() ?: $selectedCompany?->currency_code ?: 'EGP'));
        $invoices = $selectedSupplier && $selectedCompany
            ? app(SupplierBalance::class)->outstandingInvoices($selectedSupplier, $actor, $selectedCompany, $supplierCurrency)
            : collect();
        $supplierAllocations = [];
        $proposalError = null;

        try {
            if ($request->boolean('suggest_customer') && $selectedCustomer && $selectedStore && $this->isPositiveMoney((string) $request->input('amount'))) {
                $customerAllocations = $customerReceipts->proposeAllocations($actor, $selectedCustomer, $selectedStore, $customerCurrency, (string) $request->input('amount'));
            }
            if ($request->boolean('suggest_supplier') && $selectedSupplier && $selectedCompany && $this->isPositiveMoney((string) $request->input('amount'))) {
                $supplierAllocations = $supplierPayments->proposeAllocations($actor, $selectedSupplier, $selectedCompany, $supplierCurrency, (string) $request->input('amount'));
            }
        } catch (Throwable $exception) {
            $proposalError = \App\Support\UserSafeError::message($exception);
        }

        $request->validate(['customer_search' => ['nullable', 'string', 'max:100']]);
        $customerSearch = trim((string) $request->input('customer_search', ''));
        $customerPhoneSearch = strtr($customerSearch, ['٠'=>'0', '١'=>'1', '٢'=>'2', '٣'=>'3', '٤'=>'4', '٥'=>'5', '٦'=>'6', '٧'=>'7', '٨'=>'8', '٩'=>'9']);
        try {
            $customerPhoneSearch = \App\Modules\Customer\Support\PhoneNormalizer::normalize($customerPhoneSearch);
        } catch (\InvalidArgumentException) {
            $customerPhoneSearch = preg_replace('/[^0-9]/', '', $customerPhoneSearch);
        }
        $customers = $canCustomerReceipts && $customerSearch !== ''
            ? Customer::query()->visibleTo($actor)->where('status', 'active')
                ->where(function ($query) use ($customerSearch, $customerPhoneSearch): void {
                    $like = '%'.addcslashes($customerSearch, '%_\\').'%';
                    $query->where('name_ar', 'like', $like)->orWhere('name_en', 'like', $like);
                    if ($customerPhoneSearch !== '') $query->orWhere('phone_normalized', 'like', '%'.$customerPhoneSearch.'%');
                })->orderBy('name_ar')->limit(20)->get()
            : collect();
        if ($selectedCustomer && ! $customers->contains('id', $selectedCustomer->id)) {
            $customers->prepend($selectedCustomer);
        }
        $suppliers = $canSupplierPayments ? Supplier::query()->where('status', 'active')->orderBy('name_ar')->limit(100)->get() : collect();
        if ($selectedSupplier && ! $suppliers->contains('id', $selectedSupplier->id)) {
            $suppliers->prepend($selectedSupplier);
        }

        return view('feed-store.operations', [
            'customers' => $customers,
            'customerSearch' => $customerSearch,
            'stores' => $canCustomerReceipts ? $stores : collect(),
            'sales' => $sales,
            'selectedCustomer' => $selectedCustomer,
            'selectedStore' => $selectedStore,
            'defaultStore' => $defaultStore,
            'selectedSupplier' => $selectedSupplier,
            'selectedCompany' => $selectedCompany,
            'suppliers' => $suppliers,
            'invoices' => $invoices,
            'paymentCompanies' => $paymentCompanies,
            'customerAllocations' => $customerAllocations,
            'supplierAllocations' => $supplierAllocations,
            'proposalError' => $proposalError,
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
        $data = $request->validate([
            'customer_id' => 'required|integer',
            'collection_store_id' => 'required|integer',
            'currency_code' => 'required|alpha:ascii|size:3',
            'allocations' => 'nullable|array',
            'allocations.*' => 'nullable|decimal:0,4|gt:0',
            'payment_method_id' => 'required|integer',
            'cash_account_id' => 'nullable|integer',
            'amount' => 'required|decimal:0,4|gt:0',
            'date' => 'required|date',
            'reference' => 'nullable|string|max:190',
            'evidence_reference' => 'nullable|string|max:190',
            'notes' => 'nullable|string|max:1000',
            'idempotency_key' => 'required|uuid',
        ]);
        try {
            $customer = Customer::query()->visibleTo($actor)->findOrFail($data['customer_id']);
            $collectionStore = Store::query()->visibleTo($actor)->where('status', 'active')->findOrFail($data['collection_store_id']);
            $allocations = collect($data['allocations'] ?? [])->filter(fn ($value): bool => filled($value) && bccomp((string) $value, '0', 4) > 0)->all();
            $action->execute($actor, $customer, PaymentMethod::query()->findOrFail($data['payment_method_id']), $data['amount'], $allocations, $data['idempotency_key'], $data['date'], $data['reference'] ?? null, $data['notes'] ?? null, $data['evidence_reference'] ?? null, $data['cash_account_id'] ?? null, $collectionStore, strtoupper($data['currency_code']));

            return back()->with('success', __('Customer receipt recorded successfully.'));
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['operation' => \App\Support\UserSafeError::message($exception)]);
        }
    }

    public function supplierPayment(Request $request, RecordSupplierPaymentAction $action): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->validate([
            'supplier_id' => 'required|integer',
            'company_id' => 'nullable|integer',
            'allocations' => 'nullable|array',
            'allocations.*' => 'nullable|decimal:0,4|gt:0',
            'purchase_invoice_id' => 'nullable|required_without:allocations|integer',
            ...$this->paymentRules(),
        ]);
        try {
            $visibleStores = Store::query()->visibleTo($actor)->select('id');
            $supplier = Supplier::query()->findOrFail($data['supplier_id']);
            $requested = collect($data['allocations'] ?? [])->filter(fn ($value): bool => filled($value) && bccomp((string) $value, '0', 4) > 0);
            if ($requested->isEmpty() && filled($data['purchase_invoice_id'] ?? null)) {
                $requested = collect([(int) $data['purchase_invoice_id'] => $data['amount']]);
            }
            $invoiceIds = $requested->keys()->map(fn ($id): int => (int) $id);
            $invoices = PurchaseInvoice::query()->whereIn('store_id', $visibleStores)->whereIn('id', $invoiceIds)->get()->keyBy('id');
            if ($invoices->count() !== $invoiceIds->count()) {
                abort(404);
            }
            if (filled($data['company_id'] ?? null)) {
                $companyId = (int) $data['company_id'];
                abort_unless($invoices->every(fn (PurchaseInvoice $invoice): bool => (int) $invoice->store()->value('company_id') === $companyId), 404);
            }
            $allocations = $requested->map(fn ($value, $id): array => ['purchase_invoice_id' => (int) $id, 'amount' => (string) $value])->values()->all();
            $action->execute($actor, $supplier, PaymentMethod::query()->findOrFail($data['payment_method_id']), $data['amount'], $allocations, $data['idempotency_key'], $data['date'], currencyCode: strtoupper((string) ($data['currency_code'] ?? 'EGP')), cashAccountId: $data['cash_account_id'] ?? null, reference: $data['reference'] ?? null, evidenceReference: $data['evidence_reference'] ?? null, notes: $data['notes'] ?? null);
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
    private function paymentRules(): array
    {
        return ['payment_method_id' => 'required|integer', 'cash_account_id' => 'nullable|integer', 'currency_code' => 'nullable|alpha:ascii|size:3', 'amount' => 'required|decimal:0,4|gt:0', 'date' => 'required|date', 'reference' => 'nullable|string|max:190', 'evidence_reference' => 'nullable|string|max:190', 'notes' => 'nullable|string|max:1000', 'idempotency_key' => 'required|uuid'];
    }

    private function isPositiveMoney(string $value): bool
    {
        return preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', trim($value)) === 1
            && bccomp($value, '0', 4) > 0;
    }
}
