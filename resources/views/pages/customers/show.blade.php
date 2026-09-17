@php
    $displayName = str_starts_with(app()->getLocale(), 'ar') ? $customer->name_ar : $customer->name_en;
    $storeName = str_starts_with(app()->getLocale(), 'ar') ? $store->name_ar : $store->name_en;
@endphp
<x-layouts::app :title="$displayName">
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold tracking-wide text-cyan-700">{{ __('Customer profile') }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <flux:heading size="xl">{{ $displayName }}</flux:heading>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">{{ __($customer->status) }}</span>
                </div>
                <flux:text class="mt-1" dir="ltr">{{ $customer->phone_display }}</flux:text>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('loyalty.view')
                    <flux:button href="{{ route('customers.loyalty', $customer) }}" variant="primary" icon="star">{{ __('Loyalty ledger') }}</flux:button>
                @endcan
                @can('product_wallet.view')
                    <flux:button href="{{ route('customers.product-wallet', $customer) }}" variant="subtle" icon="wallet">{{ __('Product Wallet') }}</flux:button>
                @endcan
                @can('party_wallet.view')
                    <flux:button href="{{ route('customers.party-wallet', $customer) }}" variant="subtle" icon="briefcase">{{ __('Party Wallet') }}</flux:button>
                @endcan
                <flux:button href="{{ route('customers.index') }}" variant="subtle" icon="arrow-left">{{ __('Back to customers') }}</flux:button>
            </div>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-6">
                @can('customers.edit')
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="customer-identity-heading">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <flux:heading id="customer-identity-heading" size="lg">{{ __('Identity and contact') }}</flux:heading>
                                <flux:text class="mt-1 text-sm">{{ __('Arabic first and last names are required. English names are optional and fall back to Arabic when omitted; changes are audited.') }}</flux:text>
                            </div>
                            <span class="font-mono text-xs text-slate-500" dir="ltr">{{ $customer->customer_code }} · {{ $customer->public_id }}</span>
                        </div>
                        <form method="POST" action="{{ route('customers.update', $customer) }}" class="mt-4 grid gap-4 sm:grid-cols-2" x-data="{ governorate: @js((string) old('governorate_id',$customer->governorate_id)), city: @js((string) old('city_id',$customer->city_id)), cityCounts: @js($cities->countBy('governorate_id')->mapWithKeys(fn($count,$id)=>[(string)$id=>$count])) }">
                            @csrf
                            @method('PUT')
                            <flux:input name="phone" :label="__('Primary phone')" :value="old('phone', $customer->phone_display)" autocomplete="tel" :placeholder="__('e.g. 01012345678 or +20 1012345678')" :description="__('Egyptian numbers accept local, +20, 0020, spaces, and Arabic numerals.')" dir="ltr" />
                            <flux:input name="secondary_phone" :label="__('Secondary phone')" :value="old('secondary_phone', $customer->secondary_phone)" autocomplete="tel" :placeholder="__('Optional Egyptian phone number')" dir="ltr" />
                            <flux:input name="first_name_ar" :label="__('Arabic first name')" :value="old('first_name_ar', $customer->first_name_ar ?: \Illuminate\Support\Str::of($customer->name_ar)->before(' '))" required dir="rtl" />
                            <flux:input name="last_name_ar" :label="__('Arabic last name')" :value="old('last_name_ar', $customer->last_name_ar ?: \Illuminate\Support\Str::of($customer->name_ar)->after(' '))" required dir="rtl" />
                            <flux:input name="first_name_en" :label="__('English first name (optional)')" :value="old('first_name_en', $customer->first_name_en)" dir="ltr" />
                            <flux:input name="last_name_en" :label="__('English last name (optional)')" :value="old('last_name_en', $customer->last_name_en)" dir="ltr" />
                            <flux:select name="customer_group_id" :label="__('Customer group')" :description="__('Optional hierarchical group for customer search and reporting.')">
                                <flux:select.option value="">{{ __('No group assigned') }}</flux:select.option>
                                @foreach ($groupOptions as $group)
                                    <flux:select.option value="{{ $group->id }}" :selected="old('customer_group_id', $customer->customer_group_id) == $group->id">{{ str_starts_with(app()->getLocale(), 'ar') ? $group->hierarchy_path : collect(explode(' / ', $group->hierarchy_path))->map(fn($name) => $groupOptions->first(fn($candidate) => ($candidate->name_ar ?: $candidate->name_en) === $name)?->name_en ?: $name)->implode(' / ') }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            @can('customers.sensitive')
                                <flux:input name="email" :label="__('Email')" :value="old('email', $customer->email)" type="email" autocomplete="email" />
                                <flux:select name="governorate_id" x-model="governorate" x-on:change="city=''" :label="__('Governorate')"><flux:select.option value="">{{ __('Legacy — not recorded') }}</flux:select.option>@foreach($governorates as $g)<flux:select.option value="{{ $g->id }}">{{ str_starts_with(app()->getLocale(), 'ar')?$g->name_ar:$g->name_en }}</flux:select.option>@endforeach</flux:select>
                                <flux:select name="city_id" x-model="city" :label="__('City / locality')"><flux:select.option value="">{{ __('Legacy — not recorded') }}</flux:select.option>@foreach($cities as $city)<flux:select.option value="{{ $city->id }}" x-show="governorate === '{{ $city->governorate_id }}'">{{ str_starts_with(app()->getLocale(), 'ar')?$city->name_ar:$city->name_en }}</flux:select.option>@endforeach</flux:select>
                                <p class="sm:col-span-2 text-sm text-amber-700" x-cloak x-show="governorate && !cityCounts[governorate]">{{ __('No active city/locality exists for this governorate.') }} @can('company_settings.edit')<a class="font-semibold underline" href="{{ route('admin.settings.cities') }}">{{ __('Manage cities') }}</a>@endcan</p>
                                <flux:textarea name="address_ar" :label="__('Arabic address')" :value="old('address_ar', $customer->address_ar)" dir="rtl" />
                                <flux:textarea name="address_en" :label="__('English address')" :value="old('address_en', $customer->address_en)" dir="ltr" />
                            @endcan
                            <div class="sm:col-span-2 flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div>
                        </form>
                    </section>
                @else
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <flux:heading size="lg">{{ __('Identity and contact') }}</flux:heading>
                        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div><dt class="text-xs font-semibold text-slate-500">{{ __('Arabic full name') }}</dt><dd class="mt-1" dir="rtl">{{ $customer->name_ar }}</dd></div>
                            <div><dt class="text-xs font-semibold text-slate-500">{{ __('English full name') }}</dt><dd class="mt-1" dir="ltr">{{ $customer->name_en }}</dd></div>
                            <div><dt class="text-xs font-semibold text-slate-500">{{ __('Primary phone') }}</dt><dd class="mt-1 font-mono" dir="ltr">{{ $customer->phone_display }}</dd></div>
                            <div><dt class="text-xs font-semibold text-slate-500">{{ __('Customer group') }}</dt><dd class="mt-1">{{ $customer->group ? (str_starts_with(app()->getLocale(), 'ar') ? $customer->group->name_ar : $customer->group->name_en) : __('No group assigned') }}</dd></div>
                        </dl>
                    </section>
                @endcan

                @can('pricing_lists.view')
                    <section class="rounded-2xl border border-cyan-200 bg-white p-5 shadow-sm dark:border-cyan-900 dark:bg-zinc-900" aria-labelledby="customer-pricing-heading">
                        <div class="flex flex-wrap items-start justify-between gap-3"><div><flux:heading id="customer-pricing-heading" size="lg">{{ __('سياسة التسعير') }}</flux:heading><flux:text class="mt-1 text-sm">{{ __('يحدد النظام السعر تلقائيًا عند اختيار العميل ووحدة البيع في نقطة البيع.') }}</flux:text></div><div class="rounded-xl bg-cyan-50 px-4 py-2 text-sm dark:bg-cyan-950/30"><span class="text-text-muted">{{ __('نوع السعر') }}</span><div class="font-semibold">{{ $customer->priceList ? (str_starts_with(app()->getLocale(), 'ar') ? $customer->priceList->name_ar : $customer->priceList->name_en) : __('القطاعي الافتراضي') }}</div></div></div>
                        @can('pricing_lists.assign')<form method="POST" action="{{ route('customers.pricing.list',$customer) }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf<div class="min-w-64 flex-1"><flux:select name="price_list_id" :label="__('قائمة السعر المخصصة')"><flux:select.option value="">{{ __('القطاعي الافتراضي') }}</flux:select.option>@foreach($priceLists as $list)<flux:select.option value="{{ $list->id }}" :selected="$customer->price_list_id==$list->id">{{ str_starts_with(app()->getLocale(),'ar')?$list->name_ar:$list->name_en }}</flux:select.option>@endforeach</flux:select></div><flux:button type="submit" variant="primary">{{ __('تغيير نوع السعر') }}</flux:button></form>@endcan
                        <div class="mt-5 overflow-x-auto"><table class="data-table"><thead><tr><th>{{ __('الصنف') }}</th><th>{{ __('وحدة البيع') }}</th><th>{{ __('السعر الخاص') }}</th><th>{{ __('سعر القائمة') }}</th><th>{{ __('الفرق') }}</th><th>{{ __('ساري من') }}</th><th>{{ __('ساري إلى') }}</th><th>{{ __('الحالة') }}</th><th>{{ __('إجراء') }}</th></tr></thead><tbody>@forelse($specialPrices as $special)<tr><td>{{ str_starts_with(app()->getLocale(),'ar')?$special->product?->name_ar:$special->product?->name_en }}</td><td>{{ str_starts_with(app()->getLocale(),'ar')?$special->productUnit?->unit?->name_ar:$special->productUnit?->unit?->name_en }}</td><td dir="ltr"><strong>{{ $special->price }} {{ __('ج.م') }}</strong></td><td dir="ltr">{{ $special->normal_customer_price ?? '—' }}</td><td dir="ltr">{{ $special->normal_customer_price === null ? '—' : bcsub((string) $special->price, (string) $special->normal_customer_price, 4) }}</td><td>{{ $special->effective_from?->format('Y-m-d') }}</td><td>{{ $special->effective_to?->format('Y-m-d')??'—' }}</td><td><x-status.badge :status="$special->status" /></td><td>@if($special->status==='active')@can('pricing_lists.overrides')<form method="POST" action="{{ route('customers.pricing.special.expire',[$customer,$special]) }}" class="flex items-end gap-2">@csrf @method('DELETE')<flux:input name="reason" :label="__('سبب الإنهاء')" required/><flux:button type="submit" variant="danger">{{ __('إنهاء') }}</flux:button></form>@endcan
                        @endif</td></tr>@empty<tr><td colspan="9"><x-state.empty :title="__('لا توجد أسعار خاصة لهذا العميل.')" /></td></tr>@endforelse</tbody></table></div>
                        @can('pricing_lists.overrides')<details class="mt-4 rounded-xl border p-4"><summary class="cursor-pointer font-semibold text-cyan-700">{{ __('إضافة سعر خاص') }}</summary><form method="POST" action="{{ route('customers.pricing.special',$customer) }}" class="mt-4 grid gap-3 md:grid-cols-2">@csrf<flux:select name="product_unit_id" :label="__('الصنف ووحدة البيع')" required><flux:select.option value="">{{ __('اختر') }}</flux:select.option>@foreach($sellingUnits as $unit)<flux:select.option value="{{ $unit->id }}">{{ str_starts_with(app()->getLocale(),'ar')?$unit->product?->name_ar:$unit->product?->name_en }} · {{ str_starts_with(app()->getLocale(),'ar')?$unit->unit?->name_ar:$unit->unit?->name_en }}</flux:select.option>@endforeach</flux:select><flux:input name="price" type="number" step="0.0001" :label="__('السعر الخاص')" required/><flux:input name="effective_from" type="date" :label="__('ساري من')"/><flux:input name="effective_to" type="date" :label="__('ساري إلى')"/><div class="md:col-span-2"><flux:textarea name="reason" :label="__('سبب الاتفاق')" required/></div><div class="md:col-span-2 flex justify-end"><flux:button type="submit" variant="primary">{{ __('حفظ السعر الخاص') }}</flux:button></div></form></details>@endcan
                    </section>
                @endcan

                <section class="rounded-2xl border border-amber-200 bg-white shadow-sm dark:border-amber-900 dark:bg-zinc-900" aria-labelledby="customer-ar-heading">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-amber-100 px-5 py-4 dark:border-amber-900">
                        <div>
                            <flux:heading id="customer-ar-heading" size="lg">{{ __('Accounts Receivable / Customer Account') }}</flux:heading>
                            <flux:text class="mt-1 text-sm">{{ __('Balances are derived from approved sales, payments, returns, receipts, and approved adjustments.') }}</flux:text>
                        </div>
                        @can('customers.edit')
                            <flux:button href="{{ route('feed-store.operations', ['operation' => 'customer_receipt', 'customer_id' => $customer->id, 'collection_store_id' => $store->id, 'currency_code' => $currencyCode]).'#customer-receipt' }}" variant="primary" icon="banknotes">{{ __('Receive Payment / تحصيل مبلغ') }}</flux:button>
                        @endcan
                    </div>
                    <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-5">
                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-950"><div class="text-xs text-text-muted">{{ __('Customer') }}</div><div class="mt-1 font-semibold">{{ $displayName }}</div><div class="text-xs font-mono" dir="ltr">{{ $customer->customer_code }} · {{ $customer->phone_display }}</div></div>
                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-950"><div class="text-xs text-text-muted">{{ __('Credit enabled') }}</div><div class="mt-1 font-semibold">{{ in_array($customer->customer_type, ['credit', 'both'], true) ? __('Yes') : __('No') }}</div><div class="text-xs">{{ __('Credit limit') }}: <span class="font-mono" dir="ltr">{{ $customer->credit_limit ?? '—' }} {{ $currencyCode }}</span></div></div>
                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-950"><div class="text-xs text-text-muted">{{ __('Current outstanding balance') }}</div><div class="mt-1 font-mono font-semibold" dir="ltr">{{ $arOutstanding }} {{ $currencyCode }}</div><div class="text-xs">{{ __('Net customer balance') }}: {{ $currentBalance }}</div></div>
                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-950"><div class="text-xs text-text-muted">{{ __('Available credit') }}</div><div class="mt-1 font-mono font-semibold" dir="ltr">{{ $availableCredit ?? '—' }} {{ $currencyCode }}</div></div>
                        <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-950"><div class="text-xs text-text-muted">{{ __('Unapplied customer credit') }}</div><div class="mt-1 font-mono font-semibold" dir="ltr">{{ $unappliedCredit }} {{ $currencyCode }}</div></div>
                    </div>
                    <div class="overflow-x-auto border-t border-amber-100 dark:border-amber-900">
                        <table class="min-w-full text-start text-sm">
                            <thead class="bg-amber-50/60 text-xs uppercase tracking-wide text-text-muted dark:bg-amber-950/20"><tr><th class="px-5 py-3">{{ __('Invoice') }}</th><th class="px-5 py-3">{{ __('Date') }}</th><th class="px-5 py-3 text-end">{{ __('Original total') }}</th><th class="px-5 py-3 text-end">{{ __('Paid amount') }}</th><th class="px-5 py-3 text-end">{{ __('Outstanding') }}</th><th class="px-5 py-3">{{ __('Payment status') }}</th></tr></thead>
                            <tbody class="divide-y divide-amber-100 dark:divide-amber-900">
                                @forelse($arInvoices as $invoice)
                                    <tr><td class="px-5 py-3 font-mono">{{ $invoice->document_number ?? '#'.$invoice->id }}</td><td class="px-5 py-3">{{ $invoice->approved_at?->format('Y-m-d') }}</td><td class="px-5 py-3 text-end font-mono" dir="ltr">{{ $invoice->payable_total }}</td><td class="px-5 py-3 text-end font-mono" dir="ltr">{{ $invoice->current_paid }}</td><td class="px-5 py-3 text-end font-mono" dir="ltr">{{ $invoice->current_outstanding }}</td><td class="px-5 py-3"><x-status.badge :status="$invoice->current_payment_status" /></td></tr>
                                @empty
                                    <tr><td colspan="6" class="px-5 py-8"><x-state.empty :title="__('No outstanding customer invoices.')" /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                @if($salesAnalysis)
                    @include('pages.customers._sales-analysis')
                @endif

                @if($partyBookings)
                    <section class="rounded-2xl border border-violet-200 bg-white shadow-sm dark:border-violet-900 dark:bg-zinc-900" aria-labelledby="party-history-heading"><div class="border-b border-violet-100 px-5 py-4 dark:border-violet-900"><flux:heading id="party-history-heading" size="lg">{{ str_starts_with(app()->getLocale(), 'ar') ? 'سجل الحفلات والحجوزات' : 'Party booking history' }}</flux:heading><flux:text class="mt-1 text-sm">{{ str_starts_with(app()->getLocale(), 'ar') ? 'تظل معاملات الحفلات ومدفوعاتها منفصلة عن مبيعات التجزئة ومحفظة المنتجات.' : 'Party operations and payments remain separate from retail sales and the Product Wallet.' }}</flux:text></div><div class="responsive-resource-table"><table class="data-table data-table--mobile-summary w-full"><thead><tr><th>{{ __('Booking') }}</th><th>{{ str_starts_with(app()->getLocale(), 'ar') ? 'الموعد والمكان' : 'Schedule & venue' }}</th><th>{{ __('Store') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ str_starts_with(app()->getLocale(), 'ar') ? 'الرصيد' : 'Balance' }}</th></tr></thead><tbody>@forelse($partyBookings as $booking)<tr><td data-label="{{ __('Booking') }}"><a class="font-mono font-semibold text-primary hover:underline" href="{{ route('parties.bookings.show', $booking) }}">{{ $booking->booking_number }}</a></td><td data-label="{{ str_starts_with(app()->getLocale(), 'ar') ? 'الموعد والمكان' : 'Schedule & venue' }}"><div>{{ $booking->party_date?->format('Y-m-d') }} · {{ $booking->starts_at?->format('H:i') }}</div><div class="text-xs text-text-muted">{{ $booking->location }}</div></td><td data-label="{{ __('Store') }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $booking->store?->name_ar : $booking->store?->name_en }}</td><td data-label="{{ __('Status') }}"><x-status.badge :status="$booking->status" /></td><td data-label="{{ str_starts_with(app()->getLocale(), 'ar') ? 'الرصيد' : 'Balance' }}" class="text-end tabular-nums" dir="ltr">{{ number_format((float)($booking->invoice?->balance_due ?? 0), 2) }} {{ $booking->invoice?->currency_code }}</td></tr>@empty<tr><td colspan="5"><x-state.empty :title="str_starts_with(app()->getLocale(), 'ar') ? 'لا يوجد سجل حفلات بعد' : 'No Party history yet'" /></td></tr>@endforelse</tbody></table></div>@if($partyBookings->hasPages())<div class="border-t border-violet-100 px-5 py-4 dark:border-violet-900">{{ $partyBookings->links() }}</div>@endif</section>
                @endif

                @can('customers.sensitive')
                    <section class="grid gap-6 xl:grid-cols-2">
                        @if(false)<div class="hidden rounded-2xl border border-cyan-200 bg-cyan-50/50 p-5 shadow-sm dark:border-cyan-900 dark:bg-cyan-950/20" aria-hidden="true">
                            <flux:heading size="lg">{{ __('Consent history') }}</flux:heading>
                            <div class="mt-4 space-y-3">
                                @forelse ($consents as $consent)
                                    <div class="rounded-xl border border-cyan-100 bg-white/80 p-3 text-sm dark:border-cyan-900 dark:bg-zinc-900/70">
                                        <div class="flex flex-wrap justify-between gap-3"><span class="font-semibold">{{ __('Purpose') }}: {{ $consent->purpose }}</span><span class="font-bold">{{ __('State') }}: {{ __($consent->status) }}</span></div>
                                        <div class="mt-1 text-xs text-slate-500">{{ __('Captured') }}: {{ optional($consent->captured_at)->format('Y-m-d H:i') }} · {{ __('By') }}: {{ $consent->capturer?->name ?? __('Recorded user unavailable') }} · {{ __('Source') }}: {{ match ($consent->source) { 'profile_create' => __('Customer profile creation'), 'profile' => __('Customer profile'), 'pos' => __('Point of sale'), default => $consent->source } }} · {{ __('Wording') }}: {{ $consent->wording_version }}</div>
                                    </div>
                                @empty
                                    <x-state.empty :title="__('No consent history found.')" :description="__('A configured consent event is required for this profile.')" />
                                @endforelse
                            </div>
                            <form method="POST" action="{{ route('customers.consents.store', $customer) }}" class="mt-5 grid gap-3 sm:grid-cols-3">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <flux:input name="purpose" :label="__('Configured consent purpose')" :description="__('Use a purpose listed in customer policy settings.')" required />
                                <flux:select name="status" :label="__('Consent response')" :description="__('Granted, withdrawn, or denied.')" required><flux:select.option value="granted">{{ __('Granted') }}</flux:select.option><flux:select.option value="withdrawn">{{ __('Withdrawn') }}</flux:select.option><flux:select.option value="denied">{{ __('Denied') }}</flux:select.option></flux:select>
                                <div class="flex items-end"><flux:button type="submit" variant="subtle">{{ __('Record consent') }}</flux:button></div>
                            </form>
                        </div>@endif
                        <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm dark:border-amber-900 dark:bg-amber-950/20">
                            <flux:heading size="lg">{{ __('Child profiles') }}</flux:heading>
                            <flux:text class="mt-1 text-sm">{{ __('Purpose-scoped child data is kept separate and is never included in a normal customer export.') }}</flux:text>
                            <div class="mt-4 space-y-3">
                                @forelse ($customer->children as $child)
                                    <div class="rounded-xl border border-amber-100 bg-white/80 p-3 text-sm dark:border-amber-900 dark:bg-zinc-900/70">
                                        <div class="flex flex-wrap items-start justify-between gap-2"><div><div class="font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') || blank($child->name_en) ? $child->name_ar : $child->name_en }}</div>@if (filled($child->name_en))<div class="mt-1 text-xs text-slate-500" dir="ltr">{{ $child->name_en }}</div>@endif</div><x-status.badge :status="$child->status" /></div>
                                        <div class="mt-2 text-xs text-slate-500">{{ $child->birth_date?->format('Y-m-d') ?? __('Birth date not recorded') }}</div>
                                        @if ($child->status === 'active')
                                            <details class="mt-3"><summary class="cursor-pointer text-xs font-semibold text-cyan-700">{{ __('Edit child profile') }}</summary><form method="POST" action="{{ route('customers.children.update', [$customer, $child]) }}" novalidate class="mt-3 grid gap-3 sm:grid-cols-2">@csrf @method('PATCH')<flux:input name="name_ar" :label="__('Arabic name')" :value="$child->name_ar" required dir="rtl" /><flux:input name="name_en" :label="__('English name (optional)')" :value="$child->name_en" dir="ltr" /><flux:input name="birth_date" :label="__('Birth date (optional)')" :value="$child->birth_date?->format('Y-m-d')" type="date" /><div class="flex items-end"><flux:button type="submit" variant="subtle">{{ __('Save child profile') }}</flux:button></div></form><form method="POST" action="{{ route('customers.children.deactivate', [$customer, $child]) }}" class="mt-2"><button type="submit" class="text-xs font-semibold text-rose-700" onclick="return confirm('{{ __('Deactivate this child profile?') }}')">{{ __('Deactivate child profile') }}</button></form></details>
                                        @endif
                                    </div>
                                @empty
                                    <x-state.empty :title="__('No child profile recorded.')" :description="__('Child data remains optional and purpose-scoped.')" />
                                @endforelse
                            </div>
                            <form method="POST" action="{{ route('customers.children.store', $customer) }}" novalidate class="mt-5 grid gap-3 sm:grid-cols-2">
                                @csrf
                                <flux:input name="name_ar" :label="__('Arabic name')" required dir="rtl" />
                                <flux:input name="name_en" :label="__('English name (optional)')" dir="ltr" />
                                <flux:input name="birth_date" :label="__('Birth date (optional)')" type="date" />
                                <div class="sm:col-span-2 flex justify-end"><flux:button type="submit" variant="subtle">{{ __('Add child profile') }}</flux:button></div>
                            </form>
                        </div>
                    </section>
                @endcan
            </div>

            <aside class="space-y-6">
                @can('loyalty.view')
                    <section class="rounded-2xl border border-cyan-200 bg-cyan-50/60 p-5 shadow-sm dark:border-cyan-900 dark:bg-cyan-950/20">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">{{ __('Shared balance') }}</p>
                        <div class="mt-2 text-4xl font-black tabular-nums" dir="ltr">{{ number_format($balance) }}</div>
                        <flux:text class="mt-1 text-sm">{{ __('Points across the customer ledger') }}</flux:text>
                        @if ($dueExpiry > 0)<flux:callout class="mt-4" variant="warning">{{ __(':count points are due to expire.', ['count' => number_format($dueExpiry)]) }}</flux:callout>@endif
                        <flux:button class="mt-4 w-full" href="{{ route('customers.loyalty', $customer) }}" variant="primary">{{ __('Open loyalty') }}</flux:button>
                    </section>
                @endcan
                @can('product_wallet.view')
                    <section class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm dark:border-emerald-900 dark:bg-emerald-950/20">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">{{ __('Product Wallet') }}</p>
                        <div class="mt-2 break-all text-3xl font-black tabular-nums" dir="ltr">{{ $productWalletBalance }}</div>
                        <flux:text class="mt-1 text-sm">{{ __('Retail-only derived balance') }}</flux:text>
                        <flux:button class="mt-4 w-full" href="{{ route('customers.product-wallet', $customer) }}" variant="primary">{{ __('Open Product Wallet') }}</flux:button>
                    </section>
                @endcan
                @can('party_wallet.view')
                    <section class="rounded-2xl border border-violet-200 bg-violet-50/60 p-5 shadow-sm dark:border-violet-900 dark:bg-violet-950/20">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-700">{{ __('Party Wallet') }}</p>
                        <div class="mt-2 break-all text-3xl font-black tabular-nums" dir="ltr">{{ $partyWalletBalance }}</div>
                        <flux:text class="mt-1 text-sm">{{ __('Party-only derived balance') }}</flux:text>
                        <flux:button class="mt-4 w-full" href="{{ route('customers.party-wallet', $customer) }}" variant="primary">{{ __('Open Party Wallet') }}</flux:button>
                    </section>
                @endcan
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Creation source metadata') }}</flux:heading>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="text-xs font-semibold text-slate-500">{{ __('Selling store') }}</dt><dd class="mt-1">{{ $storeName }}</dd></div>
                        <div><dt class="text-xs font-semibold text-slate-500">{{ __('Creation source records') }}</dt><dd class="mt-1">{{ $customer->scopes->count() }}</dd></div>
                        @can('customers.sensitive')
                            <div><dt class="text-xs font-semibold text-slate-500">{{ __('Email') }}</dt><dd class="mt-1 break-all">{{ $customer->email ?? __('Not recorded') }}</dd></div>
                            <div><dt class="text-xs font-semibold text-slate-500">{{ __('Arabic address') }}</dt><dd class="mt-1" dir="rtl">{{ $customer->address_ar ?? __('Not recorded') }}</dd></div>
                        @endcan
                    </dl>
                </section>
                @if(false) @can('customers.merge')
                    <section class="hidden rounded-2xl border border-rose-200 bg-rose-50/50 p-5 shadow-sm dark:border-rose-900 dark:bg-rose-950/20" aria-hidden="true">
                        <flux:heading size="lg">{{ __('Controlled merge') }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ __('Merging is blocked when the duplicate has sales, loyalty, or child history. It is never an automatic duplicate resolution.') }}</flux:text>
                        <form method="POST" action="{{ route('customers.merge', $customer) }}" class="mt-4 space-y-3">
                            @csrf
                            <flux:input name="survivor_id" :label="__('Survivor customer ID')" type="number" min="1" required />
                            <flux:textarea name="reason" :label="__('Reason')" required />
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <flux:button type="submit" variant="danger">{{ __('Submit controlled merge') }}</flux:button>
                        </form>
                    </section>
                @endcan @endif
            </aside>
        </div>
    </div>
</x-layouts::app>
