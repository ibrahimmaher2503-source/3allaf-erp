<x-layouts::app :title="__('Customers')">
    @php
        $pageTitle = match ($mode) {
            'history' => __('Customer transaction history'),
            'loyalty' => __('Loyalty & points'),
            default => str_starts_with(app()->getLocale(), 'ar') ? 'العملاء وحساباتهم' : 'Customers & accounts',
        };
        $pageDescription = match ($mode) {
            'history' => __('Find a customer, then open the permission-scoped unified transaction history.'),
            'loyalty' => __('Find a customer, then open the immutable loyalty points ledger.'),
            default => str_starts_with(app()->getLocale(), 'ar') ? 'اعرف مشتريات العميل، المدفوع والمديونية الحالية، وافتح حسابه للتفاصيل والتحصيل.' : 'Review customer purchases, payments and current balance; open the account for details and collection.',
        };
    @endphp
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <x-page-header data-guide="customer-list-header" :title="$pageTitle" :description="$pageDescription">
            <x-slot:actions>
                @if ($mode === 'loyalty')
                    @can('company_settings.view')<flux:button href="{{ route('admin.settings.customer-loyalty') }}" variant="primary" icon="cog-6-tooth">{{ __('Customer Policy Settings') }}</flux:button>@endcan
                    @can('dashboard_reports.view')<flux:button href="{{ route('reports.customers') }}" variant="subtle" icon="chart-bar">{{ __('Customer & loyalty reports') }}</flux:button>@endcan
                @else
                    @can('customers.create')<flux:button data-guide="customer-create-action" href="{{ route('customers.create') }}" variant="primary" icon="plus">{{ __('New customer') }}</flux:button>@endcan
                @endif
                @canany(['customers.create', 'customers.import.approve'])<flux:button href="{{ route('customers.import') }}" variant="subtle" icon="arrow-up-tray" wire:navigate>{{ __('Customer Import') }}</flux:button>@endcanany
                @can('customers.view')<flux:button href="{{ route('customers.groups.index') }}" variant="subtle" icon="folder">{{ __('Customer groups') }}</flux:button>@endcan
            </x-slot:actions>
        </x-page-header>

        @if (session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>@endif

        <section data-guide="customer-search" class="internal-toolbar" aria-labelledby="customer-search-heading">
            <form method="GET" action="{{ route('customers.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                <input type="hidden" name="mode" value="{{ $mode }}">
                <div>
                    <label for="customer-search" class="mb-1 block text-sm font-semibold">{{ __('Phone or name') }}</label>
                    <input id="customer-search" name="q" value="{{ $term }}" dir="auto" autocomplete="off" class="block h-11 w-full rounded-xl border border-border bg-surface px-4 text-sm shadow-sm transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="{{ __('Search by normalized phone or Arabic/English name') }}">
                </div>
                <div><label for="customer-status-filter" class="mb-1 block text-sm font-semibold">{{ __('Status') }}</label><select id="customer-status-filter" name="status" class="block h-11 w-full rounded-xl border border-border bg-surface px-3 text-sm"><option value="">{{ __('All statuses') }}</option><option value="active" @selected(request('status')==='active')>{{ __('Active') }}</option><option value="inactive" @selected(request('status')==='inactive')>{{ __('Inactive') }}</option></select></div>
                <div>
                    <label for="customer-group-filter" class="mb-1 block text-sm font-semibold">{{ __('Customer group') }}</label>
                    <select id="customer-group-filter" name="group_id" class="block h-11 w-full rounded-xl border border-border bg-surface px-3 text-sm shadow-sm transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">{{ __('All groups') }}</option>
                        @foreach ($groupOptions as $group)
                            <option value="{{ $group->id }}" @selected($groupId === $group->id)>{{ $group->parent ? '↳ ' : '' }}{{ str_starts_with(app()->getLocale(), 'ar') ? $group->name_ar : $group->name_en }}</option>
                        @endforeach
                    </select>
                </div>
                <flux:button type="submit" variant="primary" icon="magnifying-glass">{{ __('Search') }}</flux:button>
                @if ($term !== '' || $status !== '' || $groupId !== null || $governorateId !== null || $cityId !== null)<flux:button href="{{ route('customers.index', ['mode' => $mode]) }}" variant="ghost">{{ __('Reset') }}</flux:button>@endif
                <details class="sm:col-span-2 lg:col-span-4" @if($governorateId || $cityId) open @endif>
                    <summary class="cursor-pointer text-sm text-text-muted">{{ str_starts_with(app()->getLocale(), 'ar') ? 'فلاتر إضافية: المحافظة والمدينة' : 'Additional filters: governorate & city' }}</summary>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div><label for="customer-governorate-filter" class="mb-1 block text-sm font-semibold">{{ __('Governorate') }}</label><select id="customer-governorate-filter" name="governorate_id" class="block h-11 w-full rounded-xl border border-border bg-surface px-3 text-sm"><option value="">{{ __('All governorates') }}</option>@foreach($governorates as $governorate)<option value="{{ $governorate->id }}" @selected(request()->integer('governorate_id')===$governorate->id)>{{ str_starts_with(app()->getLocale(), 'ar')?$governorate->name_ar:$governorate->name_en }}</option>@endforeach</select></div>
                <div><label for="customer-city-filter" class="mb-1 block text-sm font-semibold">{{ __('City / locality') }}</label><select id="customer-city-filter" name="city_id" class="block h-11 w-full rounded-xl border border-border bg-surface px-3 text-sm"><option value="">{{ __('All cities') }}</option>@foreach($cities as $city)<option value="{{ $city->id }}" @selected(request()->integer('city_id')===$city->id)>{{ str_starts_with(app()->getLocale(), 'ar')?$city->name_ar:$city->name_en }}</option>@endforeach</select></div>
                    </div>
                </details>
            </form>
            <x-tables.filter-chips :filters="[__('Search') => $term, __('Status') => $status, __('Customer group') => $groupId, __('Governorate') => $governorateId, __('City / locality') => $cityId]" :reset-url="route('customers.index', ['mode' => $mode])" />
        </section>

        <x-tables.data-panel data-guide="customer-table" :title="str_starts_with(app()->getLocale(), 'ar') ? 'حسابات العملاء' : 'Customer accounts'" :description="str_starts_with(app()->getLocale(), 'ar') ? 'إجمالي الفواتير المعتمدة والمدفوع منذ بداية التعامل. المديونية تشمل المرتجعات والتسويات؛ والرصيد السالب لصالح العميل.' : 'Lifetime approved invoice totals and payments. Balance includes returns and adjustments; negative balances are customer credit.'">
            <x-slot:actions><flux:badge size="sm" color="zinc">{{ $customers->total() }} {{ __('records') }}</flux:badge></x-slot:actions>
            <table class="data-table responsive-resource-table min-w-full text-start text-sm">
                    <thead><tr><th scope="col">{{ __('Customer') }}</th><th scope="col">{{ __('Customer group') }}</th><th scope="col">{{ __('Phone') }}</th>
                        @if($canViewCustomerAccounts)
                            <th scope="col">{{ str_starts_with(app()->getLocale(), 'ar') ? 'إجمالي المشتريات' : 'Invoice total' }}</th>
                            <th scope="col">{{ str_starts_with(app()->getLocale(), 'ar') ? 'المدفوع' : 'Payments received' }}</th>
                            <th scope="col">{{ str_starts_with(app()->getLocale(), 'ar') ? 'المديونية الحالية' : 'Current balance' }}</th>
                        @endif
                        <th scope="col">{{ __('Status') }}</th><th scope="col" class="text-end">{{ __('Action') }}</th></tr></thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr data-customer-row>
                                <td data-primary><div class="font-semibold text-text-primary">{{ str_starts_with(app()->getLocale(), 'ar') ? $customer->name_ar : $customer->name_en }}</div><div class="mt-1 text-xs text-text-muted" dir="auto">{{ str_starts_with(app()->getLocale(), 'ar') ? $customer->name_en : $customer->name_ar }}</div><span class="mt-1 inline-block font-mono text-xs" dir="ltr">{{ $customer->customer_code }}</span></td>
                                <td data-label="{{ __('Customer group') }}"><div class="font-medium">{{ $customer->group ? (str_starts_with(app()->getLocale(), 'ar') ? $customer->group->name_ar : $customer->group->name_en) : __('No group assigned') }}</div>@if ($customer->group?->parent)<div class="mt-1 text-xs text-text-muted">{{ str_starts_with(app()->getLocale(), 'ar') ? $customer->group->parent->name_ar : $customer->group->parent->name_en }}</div>@endif</td>
                                <td data-label="{{ __('Phone') }}" class="font-mono text-xs" dir="ltr">{{ $customer->phone_display }}</td>
                                @if($canViewCustomerAccounts)
                                    @php $account = $customerAccounts->get($customer->id); @endphp
                                    <td data-label="{{ str_starts_with(app()->getLocale(), 'ar') ? 'إجمالي المشتريات' : 'Invoice total' }}" class="font-mono" dir="ltr">{{ $account['purchased'] }} {{ $currencyCode }}</td>
                                    <td data-label="{{ str_starts_with(app()->getLocale(), 'ar') ? 'المدفوع' : 'Payments received' }}" class="font-mono" dir="ltr">{{ $account['paid'] }} {{ $currencyCode }}</td>
                                    <td data-label="{{ str_starts_with(app()->getLocale(), 'ar') ? 'المديونية الحالية' : 'Current balance' }}"><span class="font-mono font-semibold" dir="ltr">{{ $account['balance'] }} {{ $currencyCode }}</span>
                                        @if(bccomp($account['balance'], '0', 4) < 0)<span class="block text-xs text-text-muted">{{ str_starts_with(app()->getLocale(), 'ar') ? 'رصيد لصالح العميل' : 'Customer credit' }}</span>@endif
                                    </td>
                                @endif
                                <td data-label="{{ __('Status') }}"><x-status.badge :status="$customer->status" /></td>
                                <td data-label="{{ __('Action') }}" class="text-end">
                                    @if ($mode === 'loyalty')
                                        <x-actions.button semantic="history" :label="__('Open loyalty ledger')" :href="route('customers.loyalty', $customer)">{{ __('Open loyalty ledger') }}</x-actions.button>
                                    @elseif ($mode === 'history')
                                        <x-actions.button semantic="history" :label="__('Open transaction history')" :href="route('customers.show', $customer)">{{ __('Open transaction history') }}</x-actions.button>
                                    @else
                                        <x-actions.button semantic="view" :label="str_starts_with(app()->getLocale(), 'ar') ? 'كشف الحساب والتحصيل' : 'Account & collection'" :href="route('customers.show', $customer).'#customer-ar-heading'">{{ str_starts_with(app()->getLocale(), 'ar') ? 'كشف الحساب والتحصيل' : 'Account & collection' }}</x-actions.button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canViewCustomerAccounts ? 8 : 5 }}"><x-state.empty :title="__('No customer profiles found.')" :description="__('Create a profile or broaden the search.')"><x-slot:action>@if ($mode === 'loyalty') @can('company_settings.view')<flux:button href="{{ route('admin.settings.customer-loyalty') }}" variant="subtle">{{ __('Review customer policy') }}</flux:button>@endcan @else @can('customers.create')<flux:button href="{{ route('customers.create') }}" variant="primary" icon="plus">{{ __('Create customer profile') }}</flux:button>@endcan @endif</x-slot:action></x-state.empty></td></tr>
                        @endforelse
                    </tbody>
                </table>
            <x-slot:footer><x-tables.pagination :paginator="$customers" /></x-slot:footer>
        </x-tables.data-panel>
    </div>
</x-layouts::app>
