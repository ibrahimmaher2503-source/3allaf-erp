<x-app.page :title="__('Feed store operations')" :description="__('Record credit settlements, expenses, treasury accounts, and inventory batches.')" :breadcrumbs="__('Operations')" max-width="7xl" class="space-y-5">
    @if(session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif
    @if($errors->any())<flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>@endif
    @if($proposalError)<flux:callout variant="danger" icon="exclamation-triangle">{{ $proposalError }}</flux:callout>@endif

    @php
        $customerValues = old('form_context') === 'customer' ? old('allocations', []) : $customerAllocations;
        $supplierValues = old('form_context') === 'supplier' ? old('allocations', []) : $supplierAllocations;
    @endphp

    <div class="grid gap-4 lg:grid-cols-2">
        @can('customers.edit')
        <flux:card id="customer-receipt" class="space-y-4">
            <div><flux:heading size="lg">{{ __('Customer receipt') }}</flux:heading><flux:text>{{ __('Apply one receipt to several approved invoices, or leave it as customer credit.') }}</flux:text></div>
            <form method="POST" action="{{ route('feed-store.customer-receipts.store') }}" class="grid gap-3 sm:grid-cols-2" x-data="{ amount: @js((string) old('amount', request('amount', ''))), allocations: @js(collect($customerValues)->map(fn($value) => (string) $value)->all()), total() { return Object.values(this.allocations).reduce((sum, value) => sum + (Number(value) || 0), 0) } }">@csrf
                <input type="hidden" name="form_context" value="customer">
                <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                <flux:select name="customer_id" :label="__('Customer')" required><option value="">{{ __('Select customer') }}</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string) old('customer_id', request('customer_id')) === (string) $customer->id)>{{ $customer->name_ar }} · {{ $customer->customer_type ?: '—' }}</option>@endforeach</flux:select>
                <flux:select name="collection_store_id" :label="__('Collection store')" required><option value="">{{ __('Select store') }}</option>@foreach($stores as $store)<option value="{{ $store->id }}" @selected((string) old('collection_store_id', request('collection_store_id', $selectedCustomer?->created_store_id)) === (string) $store->id)>{{ $store->name_ar }}</option>@endforeach</flux:select>
                <flux:input name="currency_code" :value="old('currency_code', request('currency_code', 'EGP'))" maxlength="3" :label="__('Currency')" required />
                <flux:input name="amount" x-model="amount" type="number" min="0.0001" step="0.0001" :label="__('Amount')" required />
                <flux:input name="date" type="date" :value="old('date', request('date', now()->toDateString()))" :label="__('Date')" required />
                <flux:select name="payment_method_id" :label="__('Payment method')" required><option value="">{{ __('Select payment method') }}</option>@foreach($methods as $method)<option value="{{ $method->id }}" @selected((string) old('payment_method_id', request('payment_method_id')) === (string) $method->id)>{{ $method->name_ar }}</option>@endforeach</flux:select>
                <flux:select name="cash_account_id" :label="__('Cash account')"><option value="">{{ __('Required for cash receipts') }}</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected((string) old('cash_account_id', request('cash_account_id')) === (string) $account->id)>{{ $account->name_ar }} · {{ $account->currency_code }}</option>@endforeach</flux:select>
                <div class="sm:col-span-2 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-700">
                        <span class="text-sm font-medium">{{ __('Approved invoices to allocate (optional)') }}</span>
                        <flux:button type="submit" size="xs" variant="subtle" name="suggest_customer" value="1" formmethod="GET" formaction="{{ route('feed-store.operations') }}">{{ __('Suggest oldest first') }}</flux:button>
                    </div>
                    @forelse($sales as $sale)
                        <label class="flex items-center gap-3 border-b border-zinc-100 px-3 py-2 text-sm last:border-0 dark:border-zinc-800">
                            <input type="number" name="allocations[{{ $sale->id }}]" x-model="allocations[@js((string) $sale->id)]" min="0" max="{{ $sale->current_outstanding }}" step="0.0001" class="order-2 w-32 rounded-md border-zinc-300 text-sm" placeholder="0.0000">
                            <span class="flex-1">{{ $sale->document_number }} · {{ $sale->approved_at?->format('Y-m-d') }} · {{ $sale->current_outstanding }} {{ $sale->currency_code }}</span>
                        </label>
                    @empty
                        <p class="p-3 text-sm text-zinc-500">{{ $selectedCustomer ? __('No approved outstanding customer invoices.') : __('Select a customer to load outstanding invoices.') }}</p>
                    @endforelse
                </div>
                <div class="sm:col-span-2 grid gap-2 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-900 sm:grid-cols-2"><span>{{ __('Allocation total') }}: <strong x-text="total().toFixed(4)"></strong></span><span>{{ __('Unapplied customer credit') }}: <strong x-text="Math.max((Number(amount) || 0) - total(), 0).toFixed(4)"></strong></span></div>
                <flux:input name="reference" :value="old('reference', request('reference'))" :label="__('Reference')" />
                <flux:input name="evidence_reference" :value="old('evidence_reference', request('evidence_reference'))" :label="__('Payment evidence reference')" />
                <flux:input name="notes" :value="old('notes', request('notes'))" :label="__('Notes')" />
                <div class="sm:col-span-2 text-end"><flux:button type="submit" variant="primary">{{ __('Record receipt') }}</flux:button></div>
            </form>
        </flux:card>
        @endcan

        @can('purchase_invoices.approve')
        <flux:card id="supplier-payment" class="space-y-4">
            <div><flux:heading size="lg">{{ __('Supplier payment') }}</flux:heading><flux:text>{{ __('Allocate one payment across approved purchase invoices. Supplier advances are not supported.') }}</flux:text></div>
            <form method="POST" action="{{ route('feed-store.supplier-payments.store') }}" class="grid gap-3 sm:grid-cols-2" x-data="{ amount: @js((string) old('amount', request('amount', ''))), allocations: @js(collect($supplierValues)->map(fn($value) => (string) $value)->all()), total() { return Object.values(this.allocations).reduce((sum, value) => sum + (Number(value) || 0), 0) } }">@csrf
                <input type="hidden" name="form_context" value="supplier">
                <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                <flux:select name="supplier_id" :label="__('Supplier')" required><option value="">{{ __('Select supplier') }}</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) old('supplier_id', request('supplier_id')) === (string) $supplier->id)>{{ $supplier->name_ar }}</option>@endforeach</flux:select>
                <label class="grid gap-1 text-sm font-medium">
                    <span>{{ __('Company / owner') }}</span>
                    <select name="company_id" required class="h-10 rounded-lg border border-zinc-200 bg-white px-3 dark:border-white/10 dark:bg-zinc-800">
                        <option value="">{{ __('Select company') }}</option>
                        @foreach($paymentCompanies as $company)<option value="{{ $company->id }}" @selected((string) old('company_id', request('company_id', $selectedCompany?->id)) === (string) $company->id)>{{ $company->name_ar }}</option>@endforeach
                    </select>
                </label>
                <flux:input name="currency_code" :value="old('currency_code', request('currency_code', $selectedCompany?->currency_code ?: 'EGP'))" maxlength="3" :label="__('Currency')" required />
                <flux:input name="amount" x-model="amount" type="number" min="0.0001" step="0.0001" :label="__('Amount')" required />
                <flux:input name="date" type="date" :value="old('date', request('date', now()->toDateString()))" :label="__('Date')" required />
                <flux:select name="payment_method_id" :label="__('Payment method')" required><option value="">{{ __('Select payment method') }}</option>@foreach($methods as $method)<option value="{{ $method->id }}" @selected((string) old('payment_method_id', request('payment_method_id')) === (string) $method->id)>{{ $method->name_ar }}</option>@endforeach</flux:select>
                <flux:select name="cash_account_id" :label="__('Cash account')"><option value="">{{ __('Required for cash payments') }}</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected((string) old('cash_account_id', request('cash_account_id')) === (string) $account->id)>{{ $account->name_ar }} · {{ $account->currency_code }}</option>@endforeach</flux:select>
                <div class="sm:col-span-2 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-700"><span class="text-sm font-medium">{{ __('Approved purchase invoices') }}</span><flux:button type="submit" size="xs" variant="subtle" name="suggest_supplier" value="1" formmethod="GET" formaction="{{ route('feed-store.operations') }}">{{ __('Suggest oldest first') }}</flux:button></div>
                    @forelse($invoices as $invoice)
                        <label class="flex items-center gap-3 border-b border-zinc-100 px-3 py-2 text-sm last:border-0 dark:border-zinc-800"><input type="number" name="allocations[{{ $invoice->id }}]" x-model="allocations[@js((string) $invoice->id)]" min="0" max="{{ $invoice->current_outstanding }}" step="0.0001" class="order-2 w-32 rounded-md border-zinc-300 text-sm" placeholder="0.0000"><span class="flex-1">{{ $invoice->invoice_number }} · {{ $invoice->due_date?->format('Y-m-d') ?: $invoice->invoice_date?->format('Y-m-d') }} · {{ $invoice->current_outstanding }} {{ $invoice->currency_code }}</span></label>
                    @empty
                        <p class="p-3 text-sm text-zinc-500">{{ $selectedSupplier ? __('No approved outstanding supplier invoices.') : __('Select a supplier to load outstanding invoices.') }}</p>
                    @endforelse
                </div>
                <div class="sm:col-span-2 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-900">{{ __('Allocation total must equal payment amount') }}: <strong x-text="total().toFixed(4)"></strong> / <strong x-text="(Number(amount) || 0).toFixed(4)"></strong></div>
                <flux:input name="reference" :value="old('reference', request('reference'))" :label="__('Reference')" />
                <flux:input name="evidence_reference" :value="old('evidence_reference', request('evidence_reference'))" :label="__('Payment evidence reference')" />
                <flux:input name="notes" :value="old('notes', request('notes'))" :label="__('Notes')" />
                <div class="sm:col-span-2 text-end"><flux:button type="submit" variant="primary">{{ __('Record payment') }}</flux:button></div>
            </form>
        </flux:card>
        @endcan

        @can('shifts_cash_movements.approve')
        <flux:card class="space-y-4">
            <div><flux:heading size="lg">{{ __('Expense') }}</flux:heading><flux:text>{{ __('Approved expenses post an append-only treasury transaction when an account is selected.') }}</flux:text></div>
            <form method="POST" action="{{ route('feed-store.expenses.store') }}" class="grid gap-3 sm:grid-cols-2">@csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                <flux:select name="company_id" :label="__('Company / owner')" required>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->name_ar }}</option>@endforeach</flux:select>
                <flux:select name="expense_category_id" :label="__('Expense category')" required><option value="">{{ __('Select category') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name_ar }}</option>@endforeach</flux:select>
                <flux:input name="amount" type="number" min="0.0001" step="0.0001" :label="__('Amount')" required />
                <flux:input name="date" type="date" :value="now()->toDateString()" :label="__('Date')" required />
                <flux:select name="payment_method_id" :label="__('Payment method')"><option value="">{{ __('Not specified') }}</option>@foreach($methods as $method)<option value="{{ $method->id }}">{{ $method->name_ar }}</option>@endforeach</flux:select>
                <flux:select name="cash_account_id" :label="__('Cash account')"><option value="">{{ __('Required for cash expenses') }}</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->name_ar }} · {{ $account->currency_code }}</option>@endforeach</flux:select>
                <flux:input name="description" class="sm:col-span-2" :label="__('Description')" required />
                <flux:input name="reference" :label="__('Reference')" />
                <div class="text-end"><flux:button type="submit" variant="primary">{{ __('Record expense') }}</flux:button></div>
            </form>
        </flux:card>

        <flux:card class="space-y-4">
            <div><flux:heading size="lg">{{ __('General treasury account') }}</flux:heading><flux:text>{{ __('Create a separate store treasury, bank, or wallet account.') }}</flux:text></div>
            <form method="POST" action="{{ route('feed-store.cash-accounts.store') }}" class="grid gap-3 sm:grid-cols-2">@csrf
                <flux:select name="company_id" :label="__('Company / owner')" required>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->name_ar }}</option>@endforeach</flux:select>
                <flux:input name="code" :label="__('Code')" required />
                <flux:input name="name_ar" :label="__('Arabic name')" required />
                <flux:input name="name_en" :label="__('English name')" />
                <flux:select name="type" :label="__('Type')" required><option value="cash">{{ __('Cash') }}</option><option value="bank">{{ __('Bank') }}</option><option value="wallet">{{ __('Electronic wallet') }}</option></flux:select>
                <flux:input name="currency_code" value="EGP" maxlength="3" :label="__('Currency code')" required />
                <div class="sm:col-span-2 text-end"><flux:button type="submit" variant="primary">{{ __('Create account') }}</flux:button></div>
            </form>
        </flux:card>
        @endcan

        @can('inventory_stock_card.edit')
        <flux:card class="space-y-4 lg:col-span-2">
            <div><flux:heading size="lg">{{ __('Inventory batch') }}</flux:heading><flux:text>{{ __('Batches carry identity and dates only; their stock is derived from stock movements.') }}</flux:text></div>
            <form method="POST" action="{{ route('feed-store.batches.store') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">@csrf
                <flux:select name="product_id" :label="__('Tracked product')" required><option value="">{{ __('Select product') }}</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->item_code }} · {{ $product->name_ar }}</option>@endforeach</flux:select>
                <flux:input name="batch_number" :label="__('Batch number')" required />
                <flux:input name="production_date" type="date" :label="__('Production date')" />
                <flux:input name="expiry_date" type="date" :label="__('Expiry date')" />
                <div class="flex items-end"><flux:button type="submit" variant="primary" class="w-full">{{ __('Create batch') }}</flux:button></div>
            </form>
        </flux:card>
        @endcan
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        @if(auth()->user()->can('customers.edit') || auth()->user()->can('purchase_invoices.approve'))
            <flux:card>
                <flux:heading>{{ __('Recent settlements') }}</flux:heading>
                <div class="mt-3 divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                    @can('customers.edit')
                        @forelse($receipts as $receipt)
                            <div class="flex justify-between py-2"><span>{{ $receipt->customer?->name_ar }} · {{ $receipt->reference }}</span><strong>{{ $receipt->amount }} EGP</strong></div>
                        @empty
                            <p class="py-5 text-center text-zinc-500">{{ __('No customer receipts yet.') }}</p>
                        @endforelse
                    @endcan
                    @can('purchase_invoices.approve')
                        @forelse($payments as $payment)
                            <div class="flex justify-between py-2"><span>{{ $payment->supplier?->name_ar }} · {{ $payment->reference }}</span><strong class="text-rose-600">-{{ $payment->amount }} {{ $payment->currency_code }}</strong></div>
                        @empty
                            <p class="py-5 text-center text-zinc-500">{{ __('No supplier payments yet.') }}</p>
                        @endforelse
                    @endcan
                </div>
            </flux:card>
        @endif

        @if(auth()->user()->can('shifts_cash_movements.approve') || auth()->user()->can('inventory_stock_card.edit'))
            <flux:card>
                <flux:heading>{{ __('Recent treasury and inventory activity') }}</flux:heading>
                <div class="mt-3 divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                    @can('shifts_cash_movements.approve')
                        @forelse($transactions as $transaction)
                            <div class="flex justify-between py-2"><span>{{ $transaction->cashAccount?->name_ar }} · {{ $transaction->description }}</span><strong>{{ $transaction->amount }} EGP</strong></div>
                        @empty
                            <p class="py-5 text-center text-zinc-500">{{ __('No treasury transactions yet.') }}</p>
                        @endforelse
                        @foreach($expenses as $expense)
                            <div class="flex justify-between py-2"><span>{{ $expense->category?->name_ar }} · {{ $expense->description }}</span><strong class="text-rose-600">-{{ $expense->amount }} EGP</strong></div>
                        @endforeach
                    @endcan
                    @can('inventory_stock_card.edit')
                        @forelse($batches as $batch)
                            <div class="flex justify-between py-2"><span>{{ $batch->product?->name_ar }}</span><strong dir="ltr">{{ $batch->batch_number }}</strong></div>
                        @empty
                            <p class="py-5 text-center text-zinc-500">{{ __('No inventory batches yet.') }}</p>
                        @endforelse
                    @endcan
                </div>
            </flux:card>
        @endif
    </div>
</x-app.page>
