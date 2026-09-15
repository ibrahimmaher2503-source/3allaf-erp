@php
    $isArabic = str_starts_with(app()->getLocale(), 'ar');
    $isCustomerWallet = $customer !== null;
    $walletLabel = $wallet === 'party' ? __('Party Wallet') : __('Product Wallet');
    $settlementRoute = $customer !== null ? ($wallet === 'party' ? route('customers.party-wallet.settle', $customer) : route('customers.product-wallet.settle', $customer)) : null;
    $adjustmentRoute = $customer !== null ? ($wallet === 'party' ? route('customers.party-wallet.adjustments.store', $customer) : route('customers.product-wallet.adjustments.store', $customer)) : null;
@endphp

<x-layouts::app :title="$title">
    <div class="mx-auto w-full max-w-7xl min-w-0 space-y-6 p-4 sm:p-6">
        <div class="flex min-w-0 flex-wrap items-start justify-between gap-4" data-guide="{{ $guidePrefix }}-header">
            <div class="min-w-0">
                <p class="text-xs font-semibold tracking-wide text-cyan-700">{{ $isCustomerWallet ? __('Customer wallet') : __('Wallet ledger') }}</p>
                <h1 class="mt-2 break-words text-2xl font-semibold tracking-tight text-slate-950">{{ $title }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ $description }}</p>
                @if ($customer !== null)
                    <p class="mt-2 text-sm text-slate-500">{{ __('Customer') }}: <span class="font-semibold text-slate-900">{{ str_starts_with(app()->getLocale(), 'ar') ? $customer->name_ar : $customer->name_en }}</span> · <span dir="ltr" class="font-mono">{{ $customer->phone_display }}</span></p>
                @endif
            </div>
            <div class="flex max-w-full flex-wrap gap-2">
                @if ($otherPermission && auth()->user()?->can($otherPermission))
                    <a href="{{ $isCustomerWallet && $customer !== null ? route($otherCustomerRoute, $customer) : route($otherRoute) }}" class="inline-flex items-center rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-2 text-sm font-semibold text-cyan-800 shadow-sm hover:border-cyan-300">{{ $otherLabel }}</a>
                @endif
                @if ($customer !== null)
                    <a href="{{ route('customers.show', $customer) }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:border-cyan-300">{{ __('Customer profile') }}</a>
                @endif
                @can('company_settings.view')
                    <a href="{{ route('admin.settings.customer-loyalty') }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:border-cyan-300">{{ __('Wallet policy settings') }}</a>
                @endcan
            </div>
        </div>

        @if ($policyError && $wallet === 'product')
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm" role="status" data-guide="product-wallet-not-configured">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700">{{ str_starts_with(app()->getLocale(), 'ar') ? 'محفظة المنتجات' : 'Product Wallet' }}</p>
                        <h2 class="mt-2 text-lg font-semibold text-amber-950">{{ str_starts_with(app()->getLocale(), 'ar') ? 'محفظة المنتجات غير مُعدة بعد' : 'Product Wallet is not configured yet' }}</h2>
                        <p class="mt-2 text-sm leading-6 text-amber-900">{{ str_starts_with(app()->getLocale(), 'ar') ? 'تحتفظ محفظة المنتجات بأرصدة المنتجات الخاصة بالعملاء في دفتر مستقل مرتبط بالمصدر.' : 'Product Wallet holds customer product credits in a separate source-linked ledger.' }}</p>
                        <p class="mt-2 text-sm leading-6 text-amber-900">{{ str_starts_with(app()->getLocale(), 'ar') ? 'قبل الاستخدام: يجب إعداد سياسة محفظة المنتجات والعملة على مستوى الشركة. ويتطلب كل قيد مصدر بيع تجزئة معتمد.' : 'Before use, an authorized owner must configure the Product Wallet policy and company currency. Each entry also requires an approved retail source.' }}</p>
                        <p class="mt-2 text-xs leading-5 text-amber-800">{{ $policyError }}</p>
                    </div>
                    @can('company_settings.view')
                        <a href="{{ route('admin.settings.customer-loyalty') }}" class="inline-flex shrink-0 items-center rounded-xl border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-900 shadow-sm hover:border-amber-400">{{ str_starts_with(app()->getLocale(), 'ar') ? 'إعداد سياسة المحفظة' : 'Configure wallet policy' }}</a>
                    @else
                        <span class="inline-flex shrink-0 items-center rounded-xl border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-900">{{ str_starts_with(app()->getLocale(), 'ar') ? 'يلزم مستخدم مخوّل للإعداد' : 'An authorized settings user is required' }}</span>
                    @endcan
                </div>
            </section>
        @elseif ($policyError)
            <section class="rounded-2xl border border-rose-200 bg-rose-50 p-5 shadow-sm" role="alert" data-guide="{{ $guidePrefix }}-policy-error">
                <h2 class="text-lg font-semibold text-rose-950">{{ __('Wallet policy is not configured') }}</h2>
                <p class="mt-2 text-sm leading-6 text-rose-900">{{ $policyError }}</p>
                <p class="mt-2 text-xs text-rose-800">{{ __('Update wallet policy settings before posting a balance change.') }}</p>
            </section>
        @endif

        <section class="grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-4" data-guide="{{ $guidePrefix }}-summary">
            <div class="rounded-2xl border border-cyan-200 bg-cyan-50/70 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-700">{{ __('Derived balance') }}</p>
                <p class="mt-2 break-all text-3xl font-black tabular-nums text-slate-950" dir="ltr">{{ $balance }} {{ $currencyCode ?? '' }}</p>
                <p class="mt-1 text-xs text-slate-600">{{ __('Calculated from recorded wallet entries') }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Visible entries') }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $entries->total() }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">{{ __('State') }}</p>
                <p class="mt-2 text-lg font-semibold text-amber-900">{{ $policyError ? __('Action required') : __('Up to date') }}</p>
            </div>
        </section>

        @if ($customer !== null && ! $policyError && $canSettle)
            <section class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm" data-guide="{{ $guidePrefix }}-settlement">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-emerald-950">{{ __('Post source-linked settlement') }}</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-emerald-900">{{ $wallet === 'product' ? __('Product Wallet accepts an approved retail sale source.') : __('Party Wallet accepts an approved party invoice or payment source.') }}</p>
                    </div>
                    <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-emerald-800">{{ __('Transaction protected') }}</span>
                </div>
                <form method="POST" action="{{ $settlementRoute }}" class="mt-5 grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <flux:select name="direction" :label="__('Direction')" required>
                        <flux:select.option value="credit">{{ __('Credit') }}</flux:select.option>
                        <flux:select.option value="debit">{{ __('Debit') }}</flux:select.option>
                    </flux:select>
                    <flux:input name="amount" :label="__('Amount')" required inputmode="decimal" dir="ltr" placeholder="0.0000" />
                    <flux:input name="source_type" :label="__('Source type')" value="{{ $wallet === 'product' ? 'sale' : 'party_invoice' }}" required dir="ltr" />
                    <flux:input name="source_id" :label="__('Source ID')" required dir="ltr" />
                    <flux:input name="source_line_id" :label="__('Source line ID')" dir="ltr" />
                    <flux:input name="reference" :label="__('Reference')" />
                    <flux:input name="reason" :label="__('Reason')" />
                    <div class="flex items-end"><flux:button class="w-full" type="submit" variant="primary">{{ __('Post settlement') }}</flux:button></div>
                </form>
            </section>
        @endif

        @if ($customer !== null && ! $policyError && $canAdjust)
            @php($superAdminDirectAction = auth()->user()?->canBypassApproval())
            <section class="rounded-2xl border border-violet-200 bg-violet-50/50 p-5 shadow-sm" data-guide="{{ $guidePrefix }}-adjustment">
                <div>
                    <h2 class="text-lg font-semibold text-violet-950">{{ $superAdminDirectAction ? __('Apply sensitive adjustment') : __('Request sensitive adjustment') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-violet-900">{{ $superAdminDirectAction ? __('Super Admin adjustments are applied immediately and recorded in the approval history.') : __('A correction requires a separate approval before it changes the balance.') }}</p>
                </div>
                <form method="POST" action="{{ $adjustmentRoute }}" class="mt-5 grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <flux:select name="operation" :label="__('Operation')" required>
                        <flux:select.option value="adjustment">{{ __('Adjustment') }}</flux:select.option>
                        <flux:select.option value="correction">{{ __('Correction') }}</flux:select.option>
                    </flux:select>
                    <flux:input name="amount" :label="__('Signed amount')" required inputmode="decimal" dir="ltr" placeholder="-0.0000" />
                    <flux:input name="source_type" :label="__('Source type')" value="{{ $wallet === 'product' ? 'manual_adjustment' : 'party_payment' }}" required dir="ltr" />
                    <flux:input name="source_id" :label="__('Source ID')" required dir="ltr" />
                    <flux:input name="target_ledger_id" :label="__('Correction target ID')" type="number" min="1" dir="ltr" />
                    <flux:input name="source_reference" :label="__('Source reference')" />
                    <flux:input name="reason" :label="__('Reason')" required />
                    <div class="flex items-end"><flux:button class="w-full" type="submit" variant="primary">{{ $superAdminDirectAction ? __('Apply immediately') : __('Submit for approval') }}</flux:button></div>
                </form>
            </section>
        @endif

        @if ($pendingAdjustments->isNotEmpty())
            <section class="rounded-2xl border border-violet-200 bg-white p-5 shadow-sm" data-guide="{{ $guidePrefix }}-approvals">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div><h2 class="text-lg font-semibold text-slate-950">{{ __('Pending adjustments') }}</h2><p class="mt-1 text-sm text-slate-600">{{ __('Approval remains separate from the requester and never edits a ledger row.') }}</p></div>
                </div>
                <x-tables.table-shell class="mt-5" label="{{ __('Pending wallet adjustments') }}">
                    <table class="data-table min-w-[46rem] text-sm">
                        <caption class="sr-only">{{ __('Pending wallet adjustments') }}</caption>
                        <thead><tr><th scope="col">{{ __('Operation') }}</th><th scope="col" class="text-end">{{ __('Amount') }}</th><th scope="col">{{ __('Reason') }}</th><th scope="col" class="text-end">{{ __('Decision') }}</th></tr></thead>
                        <tbody>
                            @foreach ($pendingAdjustments as $pending)
                                <tr><td>{{ __(str_replace('_', ' ', ucfirst((string) ($pending->requested_action ?? 'adjustment')))) }}</td><td class="text-end"><x-money :amount="$pending->limit_context['amount'] ?? null" /></td><td class="max-w-xs">{{ $pending->reason_text }}</td><td class="text-end"><div class="flex flex-wrap justify-end gap-2"><form method="POST" action="{{ $approveRoute($pending->id) }}">@csrf<x-actions.button semantic="approve" :label="__('Approve')" type="submit" variant="primary" size="sm">{{ __('Approve') }}</x-actions.button></form><form method="POST" action="{{ $rejectRoute($pending->id) }}">@csrf<flux:input name="decision_note" :label="__('Rejection reason')" class="sr-only" value="{{ __('Rejected from wallet screen.') }}" /><x-actions.button semantic="reject" :label="__('Reject')" type="submit" variant="subtle" size="sm">{{ __('Reject') }}</x-actions.button></form></div></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-tables.table-shell>
            </section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-guide="{{ $guidePrefix }}-ledger">
            <div class="flex min-w-0 flex-wrap items-end justify-between gap-3">
                <div><h2 class="text-lg font-semibold text-slate-950">{{ __('Wallet history') }}</h2><p class="mt-1 text-sm text-slate-600">{{ __('Review wallet entries, sources, amounts, and balances.') }}</p></div>
                @if ($exportRoute)<a href="{{ $exportRoute }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:border-cyan-300">{{ __('Export visible statement') }}</a>@endif
            </div>
            @if ($entries->isEmpty())
                <div class="mt-5" data-guide="{{ $guidePrefix }}-empty">
                    <x-state.empty :title="__('No wallet entries yet')" :description="__('Wallet entries will appear after an approved source is recorded.')">
                        <x-slot:action>
                            @if ($wallet === 'product')
                                @can('company_settings.view')
                                    <flux:button href="{{ route('admin.settings.customer-loyalty') }}" variant="subtle">{{ __('Review wallet policy') }}</flux:button>
                                @endcan
                            @endif
                        </x-slot:action>
                    </x-state.empty>
                </div>
            @else
                <x-tables.table-shell class="mt-5" label="{{ $walletLabel }} {{ __('ledger history') }}">
                    <table class="data-table min-w-[66rem] text-sm">
                        <caption class="sr-only">{{ $walletLabel }} {{ __('history') }}</caption>
                        <thead><tr><th scope="col">{{ __('Customer') }}</th><th scope="col">{{ __('Entry') }}</th><th scope="col">{{ __('Source') }}</th><th scope="col" class="text-end">{{ __('Amount') }}</th><th scope="col" class="text-end">{{ __('Before') }}</th><th scope="col" class="text-end">{{ __('After') }}</th><th scope="col">{{ __('Created') }}</th></tr></thead>
                        <tbody>
                            @foreach ($entries as $entry)
                                <tr><td>{{ $entry->customer ? (str_starts_with(app()->getLocale(), 'ar') ? $entry->customer->name_ar : $entry->customer->name_en) : $entry->customer_id }}</td><td><span class="font-semibold">{{ __(str_replace('_', ' ', ucfirst((string) $entry->entry_type))) }}</span><div class="mt-1 font-mono text-xs text-text-muted">{{ $entry->public_id }}</div></td><td><span class="break-all text-xs">{{ __(str_replace('_', ' ', ucfirst((string) $entry->source_type))) }}</span><div class="font-mono text-xs text-text-muted">{{ $entry->source_id }}</div></td><td class="text-end"><x-money :amount="$entry->amount" :currency="$entry->currency_code" /></td><td class="text-end"><x-money :amount="$entry->balance_before" :currency="$entry->currency_code" /></td><td class="text-end"><x-money :amount="$entry->balance_after" :currency="$entry->currency_code" /></td><td class="text-xs text-text-muted">{{ $entry->created_at?->toDateTimeString() }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-tables.table-shell>
                <div class="mt-4">{{ $entries->links() }}</div>
            @endif
        </section>

    </div>
</x-layouts::app>
