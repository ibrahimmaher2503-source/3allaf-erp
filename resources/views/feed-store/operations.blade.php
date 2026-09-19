<x-layouts::app :title="__('Feed store operations')">
<x-app.page :title="__('Feed store operations')" :description="__('Record credit settlements, expenses, treasury accounts, and inventory batches.')" :breadcrumbs="__('Operations')" max-width="7xl" class="space-y-6">
    @if(session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif
    @if($errors->any())<flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>@endif
    @if($proposalError)<flux:callout variant="danger" icon="exclamation-triangle">{{ $proposalError }}</flux:callout>@endif

    @php
        $isArabic = str_starts_with(app()->getLocale(), 'ar');
        $localizedName = fn ($record) => $record ? ($isArabic ? ($record->name_ar ?: $record->name_en) : ($record->name_en ?: $record->name_ar)) : '—';
        $customerValues = old('form_context') === 'customer' ? old('allocations', []) : $customerAllocations;
        $supplierValues = old('form_context') === 'supplier' ? old('allocations', []) : $supplierAllocations;
    @endphp

    <nav aria-label="{{ __('Operations') }}" class="sticky top-2 z-20 -mx-1 overflow-x-auto rounded-xl border border-border bg-surface/95 p-1 shadow-sm backdrop-blur">
        <div class="flex min-w-max gap-1">
            @can('customers.edit')<a href="#customer-receipt" class="rounded-lg px-3 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950/40">{{ __('Customer receipt') }}</a>@endcan
            @can('purchase_invoices.approve')<a href="#supplier-payment" class="rounded-lg px-3 py-2 text-sm font-medium text-sky-700 transition hover:bg-sky-50 dark:text-sky-300 dark:hover:bg-sky-950/40">{{ __('Supplier payment') }}</a>@endcan
            @can('shifts_cash_movements.approve')
                <a href="#expense" class="rounded-lg px-3 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-amber-950/40">{{ __('Expense') }}</a>
                <a href="#cash-account" class="rounded-lg px-3 py-2 text-sm font-medium text-violet-700 transition hover:bg-violet-50 dark:text-violet-300 dark:hover:bg-violet-950/40">{{ __('General treasury account') }}</a>
            @endcan
            @can('inventory_stock_card.edit')<a href="#inventory-batch" class="rounded-lg px-3 py-2 text-sm font-medium text-cyan-700 transition hover:bg-cyan-50 dark:text-cyan-300 dark:hover:bg-cyan-950/40">{{ __('Inventory batch') }}</a>@endcan
        </div>
    </nav>

    <div class="grid gap-5 lg:grid-cols-2">
        @can('customers.edit')
        <flux:card id="customer-receipt" class="scroll-mt-24 overflow-hidden border-emerald-200/70 bg-surface p-0 shadow-sm dark:border-emerald-900/60">
            <div class="border-b border-emerald-200/70 bg-emerald-50/70 p-5 dark:border-emerald-900/60 dark:bg-emerald-950/20">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm"><flux:icon name="banknotes" class="size-5" /></span>
                    <div><flux:heading size="lg">{{ __('Customer receipt') }}</flux:heading><flux:text>{{ __('Apply one receipt to several approved invoices, or leave it as customer credit.') }}</flux:text></div>
                </div>
            </div>
                <form method="GET" action="{{ route('feed-store.operations') }}#customer-receipt" class="m-5 rounded-xl border border-border bg-surface-muted/30 p-4">
                    <label for="receipt-customer-search" class="block text-sm font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? 'ابحث عن العميل بالاسم أو رقم الهاتف' : 'Find a customer by name or phone' }}</label>
                    <div class="mt-2 flex flex-wrap gap-2"><input id="receipt-customer-search" name="customer_search" value="{{ $customerSearch }}" maxlength="100" type="search" dir="auto" class="min-h-11 min-w-0 flex-1 rounded-xl border border-border bg-surface px-3" placeholder="{{ str_starts_with(app()->getLocale(), 'ar') ? 'مثال: أحمد أو 01012345678' : 'Name or phone number' }}"><flux:button type="submit" variant="subtle" icon="magnifying-glass">{{ __('Search') }}</flux:button></div>
                    <p class="mt-2 text-xs text-text-muted">{{ str_starts_with(app()->getLocale(), 'ar') ? 'ابحث أولًا ثم اختر العميل وأدخل المبلغ. تُعرض أول 20 نتيجة مطابقة؛ اكتب اسمًا أو رقمًا أدق لتضييق النتائج.' : 'Search first, select the customer, then enter the amount. The first 20 matches are shown; refine your search if needed.' }}</p>
                    @if($customerSearch !== '' && $customers->isEmpty())<p class="mt-2 text-sm" role="status">{{ str_starts_with(app()->getLocale(), 'ar') ? 'لا يوجد عميل مطابق داخل نطاقك. جرّب جزءًا من الاسم أو رقم الهاتف.' : 'No matching authorized customer. Try part of the name or phone.' }}</p>@endif
                    @error('customer_search')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </form>
            <form method="POST" action="{{ route('feed-store.customer-receipts.store') }}" class="grid gap-4 p-5 sm:grid-cols-2" x-data="{ customerId: @js((string) old('customer_id', request('customer_id', ''))), storeId: @js((string) old('collection_store_id', request('collection_store_id', $defaultStore?->id))), amount: @js((string) old('amount', request('amount', ''))), allocations: @js(collect($customerValues)->map(fn($value) => (string) $value)->all()), total() { return Object.values(this.allocations).reduce((sum, value) => sum + (Number(value) || 0), 0) } }">@csrf
                <input type="hidden" name="form_context" value="customer">
                <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                <flux:select name="customer_id" x-model="customerId" :label="__('Customer')" required><option value="">{{ __('Select customer') }}</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string) old('customer_id', request('customer_id')) === (string) $customer->id)>{{ $localizedName($customer) }} · {{ $customer->phone_display }} · {{ __(['cash' => 'Cash customer', 'credit' => 'Credit customer', 'both' => 'Cash and credit customer'][$customer->customer_type] ?? 'Not specified') }}</option>@endforeach</flux:select>
                @if($stores->count() === 1)<input type="hidden" name="collection_store_id" x-model="storeId" value="{{ $stores->first()->id }}">@else<flux:select name="collection_store_id" x-model="storeId" :label="__('Collection store')" required><option value="">{{ __('Select store') }}</option>@foreach($stores as $store)<option value="{{ $store->id }}" @selected((string) old('collection_store_id', request('collection_store_id', $defaultStore?->id ?: $selectedCustomer?->created_store_id)) === (string) $store->id)>{{ $localizedName($store) }}{{ $defaultStore?->is($store) ? ' · '.__('Primary') : '' }}</option>@endforeach</flux:select>@endif
                <input type="hidden" name="currency_code" value="{{ old('currency_code', request('currency_code', $selectedStore?->company?->currency_code ?: 'EGP')) }}">
                <flux:input name="amount" x-model="amount" type="number" min="0.0001" step="0.0001" :label="__('Amount')" required />
                <flux:input name="date" type="date" :value="old('date', request('date', now()->toDateString()))" :label="__('Date')" required />
                <flux:select name="payment_method_id" :label="__('Payment method')" required><option value="">{{ __('Select payment method') }}</option>@foreach($methods as $method)<option value="{{ $method->id }}" @selected((string) old('payment_method_id', request('payment_method_id')) === (string) $method->id)>{{ $localizedName($method) }}</option>@endforeach</flux:select>
                <div class="grid gap-1">
                    <flux:select name="cash_account_id" :label="__('Cash account')"><option value="">{{ __('Required for cash receipts') }}</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected((string) old('cash_account_id', request('cash_account_id')) === (string) $account->id)>{{ $localizedName($account) }}</option>@endforeach</flux:select>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Cash account help') }}</p>
                </div>
                <div class="sm:col-span-2 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-700">
                        <span class="text-sm font-medium">{{ __('Approved invoices to allocate (optional)') }}</span>
                        <flux:button type="submit" size="xs" variant="subtle" name="suggest_customer" value="1" formmethod="GET" formaction="{{ route('feed-store.operations') }}" formnovalidate x-bind:disabled="!customerId || !storeId || !(Number(amount) > 0)">{{ __('Suggest oldest first') }}</flux:button>
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
                <div class="sm:col-span-2 grid gap-2 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-900 sm:grid-cols-2"><span>{{ __('Allocation total') }}: <strong dir="ltr" class="inline-block tabular-nums" x-text="total().toFixed(4)"></strong></span><span>{{ __('Unapplied customer credit') }}: <strong dir="ltr" class="inline-block tabular-nums" x-text="Math.max((Number(amount) || 0) - total(), 0).toFixed(4)"></strong></span></div>
                <details class="sm:col-span-2 rounded-lg border border-border bg-surface-muted/30 px-3 py-2">
                    <summary class="cursor-pointer text-sm font-medium">{{ __('Additional details') }}</summary>
                    <div class="grid gap-4 pb-2 pt-4 sm:grid-cols-2">
                        <flux:input name="reference" :value="old('reference', request('reference'))" :label="__('Reference')" />
                        <flux:input name="evidence_reference" :value="old('evidence_reference', request('evidence_reference'))" :label="__('Payment evidence reference')" />
                        <flux:input name="notes" :value="old('notes', request('notes'))" :label="__('Notes')" />
                    </div>
                </details>
                <div class="sm:col-span-2 text-end"><flux:button type="submit" variant="primary">{{ __('Record receipt') }}</flux:button></div>
            </form>
        </flux:card>
        @endcan

        @can('purchase_invoices.approve')
        <flux:card id="supplier-payment" class="scroll-mt-24 overflow-hidden border-sky-200/70 bg-surface p-0 shadow-sm dark:border-sky-900/60">
            <div class="border-b border-sky-200/70 bg-sky-50/70 p-5 dark:border-sky-900/60 dark:bg-sky-950/20">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-sky-600 text-white shadow-sm"><flux:icon name="truck" class="size-5" /></span>
                    <div><flux:heading size="lg">{{ __('Supplier payment') }}</flux:heading><flux:text>{{ __('Allocate one payment across approved purchase invoices. Supplier advances are not supported.') }}</flux:text></div>
                </div>
            </div>
            <form method="POST" action="{{ route('feed-store.supplier-payments.store') }}" class="grid gap-4 p-5 sm:grid-cols-2" x-data="{ amount: @js((string) old('amount', request('amount', ''))), allocations: @js(collect($supplierValues)->map(fn($value) => (string) $value)->all()), total() { return Object.values(this.allocations).reduce((sum, value) => sum + (Number(value) || 0), 0) } }">@csrf
                <input type="hidden" name="form_context" value="supplier">
                <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                <flux:select name="supplier_id" :label="__('Supplier')" required><option value="">{{ __('Select supplier') }}</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) old('supplier_id', request('supplier_id')) === (string) $supplier->id)>{{ $localizedName($supplier) }}</option>@endforeach</flux:select>
                @if($paymentCompanies->count() === 1)
                    <input type="hidden" name="company_id" value="{{ $paymentCompanies->first()->id }}">
                @else
                    <label class="grid gap-1 text-sm font-medium">
                        <span>{{ __('Company / owner') }}</span>
                        <select name="company_id" required class="h-10 rounded-lg border border-zinc-200 bg-white px-3 dark:border-white/10 dark:bg-zinc-800">
                            <option value="">{{ __('Select company') }}</option>
                            @foreach($paymentCompanies as $company)<option value="{{ $company->id }}" @selected((string) old('company_id', request('company_id', $selectedCompany?->id)) === (string) $company->id)>{{ $localizedName($company) }}</option>@endforeach
                        </select>
                    </label>
                @endif
                <input type="hidden" name="currency_code" value="{{ old('currency_code', request('currency_code', $selectedCompany?->currency_code ?: 'EGP')) }}">
                <flux:input name="amount" x-model="amount" type="number" min="0.0001" step="0.0001" :label="__('Amount')" required />
                <flux:input name="date" type="date" :value="old('date', request('date', now()->toDateString()))" :label="__('Date')" required />
                <flux:select name="payment_method_id" :label="__('Payment method')" required><option value="">{{ __('Select payment method') }}</option>@foreach($methods as $method)<option value="{{ $method->id }}" @selected((string) old('payment_method_id', request('payment_method_id')) === (string) $method->id)>{{ $localizedName($method) }}</option>@endforeach</flux:select>
                <div class="grid gap-1">
                    <flux:select name="cash_account_id" :label="__('Cash account')"><option value="">{{ __('Required for cash payments') }}</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected((string) old('cash_account_id', request('cash_account_id')) === (string) $account->id)>{{ $localizedName($account) }}</option>@endforeach</flux:select>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Cash account help') }}</p>
                </div>
                <div class="sm:col-span-2 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-700"><span class="text-sm font-medium">{{ __('Approved purchase invoices') }}</span><flux:button type="submit" size="xs" variant="subtle" name="suggest_supplier" value="1" formmethod="GET" formaction="{{ route('feed-store.operations') }}" formnovalidate>{{ __('Suggest oldest first') }}</flux:button></div>
                    @forelse($invoices as $invoice)
                        <label class="flex items-center gap-3 border-b border-zinc-100 px-3 py-2 text-sm last:border-0 dark:border-zinc-800"><input type="number" name="allocations[{{ $invoice->id }}]" x-model="allocations[@js((string) $invoice->id)]" min="0" max="{{ $invoice->current_outstanding }}" step="0.0001" class="order-2 w-32 rounded-md border-zinc-300 text-sm" placeholder="0.0000"><span class="flex-1">{{ $invoice->invoice_number }} · {{ $invoice->due_date?->format('Y-m-d') ?: $invoice->invoice_date?->format('Y-m-d') }} · {{ $invoice->current_outstanding }} {{ $invoice->currency_code }}</span></label>
                    @empty
                        <p class="p-3 text-sm text-zinc-500">{{ $selectedSupplier ? __('No approved outstanding supplier invoices.') : __('Select a supplier to load outstanding invoices.') }}</p>
                    @endforelse
                </div>
                <div class="sm:col-span-2 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-900">{{ __('Allocation total must equal payment amount') }}: <strong dir="ltr" class="inline-block tabular-nums" x-text="total().toFixed(4)"></strong> / <strong dir="ltr" class="inline-block tabular-nums" x-text="(Number(amount) || 0).toFixed(4)"></strong></div>
                <details class="sm:col-span-2 rounded-lg border border-border bg-surface-muted/30 px-3 py-2">
                    <summary class="cursor-pointer text-sm font-medium">{{ __('Additional details') }}</summary>
                    <div class="grid gap-4 pb-2 pt-4 sm:grid-cols-2">
                        <flux:input name="reference" :value="old('reference', request('reference'))" :label="__('Reference')" />
                        <flux:input name="evidence_reference" :value="old('evidence_reference', request('evidence_reference'))" :label="__('Payment evidence reference')" />
                        <flux:input name="notes" :value="old('notes', request('notes'))" :label="__('Notes')" />
                    </div>
                </details>
                <div class="sm:col-span-2 text-end"><flux:button type="submit" variant="primary">{{ __('Record payment') }}</flux:button></div>
            </form>
        </flux:card>
        @endcan

        @can('shifts_cash_movements.approve')
        <flux:card id="expense" class="scroll-mt-24 overflow-hidden border-amber-200/70 bg-surface p-0 shadow-sm dark:border-amber-900/60">
            <div class="border-b border-amber-200/70 bg-amber-50/70 p-5 dark:border-amber-900/60 dark:bg-amber-950/20">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white shadow-sm"><flux:icon name="receipt-percent" class="size-5" /></span>
                    <div><flux:heading size="lg">{{ __('Expense') }}</flux:heading><flux:text>{{ __('Approved expenses post an append-only treasury transaction when an account is selected.') }}</flux:text></div>
                </div>
            </div>
            <form method="POST" action="{{ route('feed-store.expenses.store') }}" class="grid gap-4 p-5 sm:grid-cols-2">@csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                @if($companies->count() === 1)
                    <input type="hidden" name="company_id" value="{{ $companies->first()->id }}">
                @else
                    <flux:select name="company_id" :label="__('Company / owner')" required>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $localizedName($company) }}</option>@endforeach</flux:select>
                @endif
                <flux:select name="expense_category_id" :label="__('Expense category')" required><option value="">{{ __('Select category') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $localizedName($category) }}</option>@endforeach</flux:select>
                <flux:input name="amount" type="number" min="0.0001" step="0.0001" :label="__('Amount')" required />
                <flux:input name="date" type="date" :value="now()->toDateString()" :label="__('Date')" required />
                <flux:select name="payment_method_id" :label="__('Payment method')"><option value="">{{ __('Not specified') }}</option>@foreach($methods as $method)<option value="{{ $method->id }}">{{ $localizedName($method) }}</option>@endforeach</flux:select>
                <flux:select name="cash_account_id" :label="__('Cash account')"><option value="">{{ __('Required for cash expenses') }}</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $localizedName($account) }}</option>@endforeach</flux:select>
                <flux:input name="description" class="sm:col-span-2" :label="__('Description')" required />
                <details class="sm:col-span-2 rounded-lg border border-border bg-surface-muted/30 px-3 py-2">
                    <summary class="cursor-pointer text-sm font-medium">{{ __('Additional details') }}</summary>
                    <div class="pb-2 pt-4"><flux:input name="reference" :label="__('Reference')" /></div>
                </details>
                <div class="sm:col-span-2 text-end"><flux:button type="submit" variant="primary">{{ __('Record expense') }}</flux:button></div>
            </form>
        </flux:card>

        <flux:card id="cash-account" class="scroll-mt-24 overflow-hidden border-violet-200/70 bg-surface p-0 shadow-sm dark:border-violet-900/60">
            <div class="border-b border-violet-200/70 bg-violet-50/70 p-5 dark:border-violet-900/60 dark:bg-violet-950/20">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-violet-600 text-white shadow-sm"><flux:icon name="wallet" class="size-5" /></span>
                    <div><flux:heading size="lg">{{ __('General treasury account') }}</flux:heading><flux:text>{{ __('Create a separate store treasury, bank, or wallet account.') }}</flux:text></div>
                </div>
            </div>
            <form method="POST" action="{{ route('feed-store.cash-accounts.store') }}" class="grid gap-4 p-5 sm:grid-cols-2">@csrf
                @if($companies->count() === 1)
                    <input type="hidden" name="company_id" value="{{ $companies->first()->id }}">
                @else
                    <flux:select name="company_id" :label="__('Company / owner')" required>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $localizedName($company) }}</option>@endforeach</flux:select>
                @endif
                <flux:input name="code" :label="__('Code')" required />
                <flux:input name="name_ar" :label="__('Arabic name')" required />
                <flux:input name="name_en" :label="__('English name')" />
                <flux:select name="type" :label="__('Type')" required><option value="cash">{{ __('Cash') }}</option><option value="bank">{{ __('Bank') }}</option><option value="wallet">{{ __('Electronic wallet') }}</option></flux:select>
                <input type="hidden" name="currency_code" value="{{ $companies->first()?->currency_code ?: 'EGP' }}">
                <div class="sm:col-span-2 text-end"><flux:button type="submit" variant="primary">{{ __('Create account') }}</flux:button></div>
            </form>
        </flux:card>
        @endcan

        @can('inventory_stock_card.edit')
        <flux:card id="inventory-batch" class="scroll-mt-24 overflow-hidden border-cyan-200/70 bg-surface p-0 shadow-sm dark:border-cyan-900/60 lg:col-span-2">
            <div class="border-b border-cyan-200/70 bg-cyan-50/70 p-5 dark:border-cyan-900/60 dark:bg-cyan-950/20">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-cyan-600 text-white shadow-sm"><flux:icon name="archive-box" class="size-5" /></span>
                    <div><flux:heading size="lg">{{ __('Inventory batch') }}</flux:heading><flux:text>{{ __('Batches carry identity and dates only; their stock is derived from stock movements.') }}</flux:text></div>
                </div>
            </div>
            <form method="POST" action="{{ route('feed-store.batches.store') }}" class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-5">@csrf
                <flux:select name="product_id" :label="__('Tracked product')" required><option value="">{{ __('Select product') }}</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->item_code }} · {{ $localizedName($product) }}</option>@endforeach</flux:select>
                <flux:input name="batch_number" :label="__('Batch number')" required />
                <flux:input name="production_date" type="date" :label="__('Production date')" />
                <flux:input name="expiry_date" type="date" :label="__('Expiry date')" />
                <div class="flex items-end"><flux:button type="submit" variant="primary" class="w-full">{{ __('Create batch') }}</flux:button></div>
            </form>
        </flux:card>
        @endcan
    </div>

    <div class="grid gap-5 xl:grid-cols-2">
        @if(auth()->user()->can('customers.edit') || auth()->user()->can('purchase_invoices.approve'))
            <flux:card class="overflow-hidden border border-border bg-surface p-0 shadow-sm">
                <div class="flex items-center gap-3 border-b border-border bg-surface-muted/40 p-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300"><flux:icon name="arrow-down-left" class="size-4" /></span>
                    <flux:heading>{{ __('Recent settlements') }}</flux:heading>
                </div>
                <div class="max-h-96 overflow-y-auto divide-y divide-zinc-200 px-4 text-sm dark:divide-zinc-800">
                    @can('customers.edit')
                        @forelse($receipts as $receipt)
                            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 py-3"><span>{{ $localizedName($receipt->customer) }} · {{ $receipt->reference }}</span><strong dir="ltr" class="shrink-0 tabular-nums">{{ $receipt->amount }} {{ $receipt->currency_code }}</strong></div>
                        @empty
                            <p class="py-5 text-center text-zinc-500">{{ __('No customer receipts yet.') }}</p>
                        @endforelse
                    @endcan
                    @can('purchase_invoices.approve')
                        @forelse($payments as $payment)
                            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 py-3"><span>{{ $localizedName($payment->supplier) }} · {{ $payment->reference }}</span><strong dir="ltr" class="shrink-0 tabular-nums text-rose-600">-{{ $payment->amount }} {{ $payment->currency_code }}</strong></div>
                        @empty
                            <p class="py-5 text-center text-zinc-500">{{ __('No supplier payments yet.') }}</p>
                        @endforelse
                    @endcan
                </div>
            </flux:card>
        @endif

        @if(auth()->user()->can('shifts_cash_movements.approve') || auth()->user()->can('inventory_stock_card.edit'))
            <flux:card class="overflow-hidden border border-border bg-surface p-0 shadow-sm">
                <div class="flex items-center gap-3 border-b border-border bg-surface-muted/40 p-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300"><flux:icon name="arrows-right-left" class="size-4" /></span>
                    <flux:heading>{{ __('Recent treasury and inventory activity') }}</flux:heading>
                </div>
                <div class="max-h-96 overflow-y-auto divide-y divide-zinc-200 px-4 text-sm dark:divide-zinc-800">
                    @can('shifts_cash_movements.approve')
                        @forelse($transactions as $transaction)
                            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 py-3"><span>{{ $localizedName($transaction->cashAccount) }} · {{ $transaction->description }}</span><strong dir="ltr" class="shrink-0 tabular-nums">{{ $transaction->amount }} EGP</strong></div>
                        @empty
                            <p class="py-5 text-center text-zinc-500">{{ __('No treasury transactions yet.') }}</p>
                        @endforelse
                        @foreach($expenses as $expense)
                            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 py-3"><span>{{ $localizedName($expense->category) }} · {{ $expense->description }}</span><strong dir="ltr" class="shrink-0 tabular-nums text-rose-600">-{{ $expense->amount }} EGP</strong></div>
                        @endforeach
                    @endcan
                    @can('inventory_stock_card.edit')
                        @forelse($batches as $batch)
                            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 py-3"><span>{{ $localizedName($batch->product) }}</span><strong dir="ltr">{{ $batch->batch_number }}</strong></div>
                        @empty
                            <p class="py-5 text-center text-zinc-500">{{ __('No inventory batches yet.') }}</p>
                        @endforelse
                    @endcan
                </div>
            </flux:card>
        @endif
    </div>
</x-app.page>
</x-layouts::app>
