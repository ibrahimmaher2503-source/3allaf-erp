<x-layouts::app :title="__('Sales Invoices')">
    @php
        $isArabic = str_starts_with(app()->getLocale(), 'ar');
        $selectedStore = $stores->firstWhere('id', (int) request('store_id'));
    @endphp
    <x-app.page class="internal-page" :title="__('Sales Invoices')" :description="__('Approved, numbered retail invoices with immutable financial snapshots.')" :breadcrumbs="$isArabic ? 'المبيعات ونقطة البيع / فواتير المبيعات' : 'Sales & POS / Sales invoices'">
        <x-slot:actions>
            @can('pos_sales.create')<flux:button href="{{ route('pos') }}" variant="primary" icon="shopping-cart" wire:navigate>{{ $isArabic ? 'فتح نقطة البيع' : 'Open POS' }}</flux:button>@endcan
            <flux:button href="{{ route('sales.index') }}" variant="subtle" icon="presentation-chart-line" wire:navigate>{{ $isArabic ? 'نظرة عامة' : 'Sales overview' }}</flux:button>
        </x-slot:actions>

        <form method="GET" class="internal-toolbar" aria-label="{{ $isArabic ? 'مرشحات فواتير المبيعات' : 'Sales invoice filters' }}">
            <div class="internal-toolbar__grid">
                <flux:input name="q" value="{{ request('q') }}" :label="__('Invoice number')" :placeholder="app()->isLocale('ar-EG') ? 'ادوّر برقم الفاتورة' : ($isArabic ? 'ابحث برقم الفاتورة' : 'Search invoice number')" />
                @if($stores->count() > 1)<flux:select name="store_id" :label="__('Store')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach ($stores as $store)<flux:select.option value="{{ $store->id }}" :selected="(string) request('store_id') === (string) $store->id">{{ $store->code }}</flux:select.option>@endforeach</flux:select>@endif
                <flux:input name="date_from" type="date" value="{{ request('date_from') }}" :label="__('From')" />
                <flux:input name="date_to" type="date" value="{{ request('date_to') }}" :label="__('To')" />
                <div class="flex items-end gap-2 xl:col-span-2"><flux:button type="submit" variant="primary" icon="funnel">{{ __('Filter') }}</flux:button><flux:button href="{{ route('sales.invoices') }}" variant="ghost">{{ __('Reset') }}</flux:button></div>
            </div>
            <x-tables.filter-chips :filters="[__('Invoice number') => request('q'), __('Store') => $selectedStore?->code, __('From') => request('date_from'), __('To') => request('date_to')]" :reset-url="route('sales.invoices')" />
        </form>

        <x-tables.data-panel :title="__('Sales Invoices')" :description="$isArabic ? 'الفواتير المعتمدة في منفذ البيع الرئيسي.' : 'Approved invoices for the main selling outlet.'">
            <x-slot:actions><flux:badge size="sm" color="zinc">{{ $sales->total() }} {{ __('records') }}</flux:badge></x-slot:actions>
            <table class="data-table responsive-resource-table w-full text-sm">
                <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Store') }}</th><th>{{ __('Cashier') }}</th><th>{{ __('Approved') }}</th><th class="text-end">{{ __('Payments') }}</th><th class="text-end">{{ __('Final total') }}</th><th class="text-end">{{ __('Action') }}</th></tr></thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td data-primary><a class="font-semibold text-primary hover:underline" href="{{ route('sales.show', $sale) }}" wire:navigate>{{ $sale->document_number }}</a><p class="mt-1 text-xs text-text-muted">{{ $sale->approved_at?->format('Y-m-d H:i') }}</p></td>
                            <td data-label="{{ __('Store') }}">{{ $isArabic ? $sale->store->name_ar : $sale->store->name_en }}<p class="text-xs text-text-muted" dir="ltr">{{ $sale->store->code }}</p></td>
                            <td data-label="{{ __('Cashier') }}">{{ $sale->cashier->name }}</td>
                            <td data-label="{{ __('Approved') }}"><x-status.badge status="approved" /></td>
                            <td data-label="{{ __('Payments') }}" class="text-end tabular-nums">{{ $sale->payments_count }}</td>
                            <td data-label="{{ __('Final total') }}" class="text-end"><x-money :amount="$sale->total" :currency="$sale->currency_code" class="font-semibold" /></td>
                            <td data-label="{{ __('Action') }}" class="text-end"><x-actions.button semantic="view" :label="__('View')" href="{{ route('sales.show', $sale) }}" size="sm" variant="subtle" icon="eye" wire:navigate>{{ __('View') }}</x-actions.button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-state.empty :title="__('No approved invoices match the selected filters.')" :description="__('Try another filter or clear the current filters.')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
            <x-slot:footer><x-tables.pagination :paginator="$sales" /></x-slot:footer>
        </x-tables.data-panel>
    </x-app.page>
</x-layouts::app>
