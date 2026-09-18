<x-layouts::app :title="__('Sales overview')">
    @php
        $isArabic = str_starts_with(app()->getLocale(), 'ar');
        $maxTrend = max(1, (float) collect($dashboard['trend'])->max('total'));
        $selectedStore = $stores->firstWhere('id', (int) request('store_id'));
        $filterLabels = [
            __('Search') => request('q'),
            __('Status') => request('status'),
            __('Store') => $selectedStore?->code,
            __('From') => request('date_from'),
            __('To') => request('date_to'),
        ];
        $sortUrl = fn (string $key) => route('sales.index', array_merge(request()->except('page'), [
            'sort' => $key,
            'direction' => $sort === $key && $direction === 'asc' ? 'desc' : 'asc',
        ]));
    @endphp

    <x-app.page class="internal-page" :title="$isArabic ? 'نظرة عامة على المبيعات' : 'Sales overview'" :description="$isArabic ? 'مؤشرات المبيعات اليومية والمهام التشغيلية ضمن المواقع المصرح بها.' : 'Daily sales indicators and operational work within your authorized locations.'" :breadcrumbs="$isArabic ? 'المبيعات ونقطة البيع' : 'Sales & POS'">
        <x-slot:actions>
            @can('pos_sales.create')<flux:button href="{{ route('pos') }}" variant="primary" icon="shopping-cart" wire:navigate>{{ $isArabic ? 'فتح نقطة البيع' : 'Open POS' }}</flux:button>@endcan
            <flux:button href="{{ route('sales.invoices') }}" variant="subtle" icon="document-text" wire:navigate>{{ $isArabic ? 'فواتير المبيعات' : 'Sales invoices' }}</flux:button>
            @can('shifts_cash_movements.view')<flux:button href="{{ route('pos.shift') }}" variant="subtle" icon="banknotes" wire:navigate>{{ $isArabic ? 'الورديات والتحصيل' : 'Shifts & collections' }}</flux:button>@endcan
        </x-slot:actions>

        <section class="dashboard-kpi-grid" aria-label="{{ $isArabic ? 'مؤشرات المبيعات' : 'Sales indicators' }}">
            <a href="{{ route('sales.invoices', ['date_from' => today()->toDateString(), 'date_to' => today()->toDateString(), 'store_id' => request('store_id')]) }}" class="dashboard-kpi dashboard-kpi--priority">
                <div><p class="dashboard-kpi__label">{{ $isArabic ? 'مبيعات اليوم' : "Today's sales" }}</p><p class="dashboard-kpi__value"><x-money :amount="$dashboard['today_total']" :currency="$dashboard['currency_code']" /></p><p class="dashboard-kpi__state">{{ $dashboard['today_count'] }} {{ $isArabic ? 'فاتورة معتمدة' : 'approved invoices' }}</p></div><span class="dashboard-kpi__icon"><flux:icon.banknotes class="size-5" /></span>
            </a>
            <a href="{{ route('sales.invoices', ['date_from' => today()->toDateString(), 'date_to' => today()->toDateString()]) }}" class="dashboard-kpi">
                <div><p class="dashboard-kpi__label">{{ $isArabic ? 'متوسط الفاتورة' : 'Average ticket' }}</p><p class="dashboard-kpi__value"><x-money :amount="$dashboard['average_ticket']" :currency="$dashboard['currency_code']" /></p><p class="dashboard-kpi__state">{{ number_format($dashboard['today_count']) }} {{ $isArabic ? 'فاتورة اليوم' : 'invoices today' }}</p></div><span class="dashboard-kpi__icon"><flux:icon.document-text class="size-5" /></span>
            </a>
            @can('shifts_cash_movements.view')<a href="{{ route('pos.shift') }}" class="dashboard-kpi">
                <div><p class="dashboard-kpi__label">{{ $isArabic ? 'الورديات المفتوحة' : 'Open shifts' }}</p><p class="dashboard-kpi__value">{{ number_format($dashboard['open_shifts']) }}</p><p class="dashboard-kpi__state">{{ app()->isLocale('ar-EG') ? 'تحتاج استكمال تشغيلي' : ($isArabic ? 'تحتاج متابعة تشغيلية' : 'Operational shift context') }}</p></div><span class="dashboard-kpi__icon"><flux:icon.clock class="size-5" /></span>
            </a>@endcan
            <a href="{{ route('returns.index') }}" class="dashboard-kpi">
                <div><p class="dashboard-kpi__label">{{ $isArabic ? 'خصومات اليوم' : "Today's discounts" }}</p><p class="dashboard-kpi__value"><x-money :amount="$dashboard['discount_total']" :currency="$dashboard['currency_code']" /></p><p class="dashboard-kpi__state">{{ number_format($dashboard['returns_count']) }} {{ $isArabic ? 'مرتجع اليوم' : 'returns today' }}</p></div><span class="dashboard-kpi__icon"><flux:icon.pause-circle class="size-5" /></span>
            </a>
        </section>

        @if ($dashboard['today_count'] === 0 && $dashboard['approved_count'] > 0)
            <flux:callout variant="info" icon="information-circle">
                {{ $isArabic ? 'بطاقات اليوم تعرض الفواتير بتاريخ الاعتماد، وليس تاريخ إنشاء السجل.' : 'Today’s cards use the approval date, not the record creation date.' }}
                {{ $isArabic ? 'يوجد' : 'There are' }} {{ number_format($dashboard['approved_count']) }} {{ $isArabic ? 'فاتورة معتمدة، وآخر اعتماد كان في' : 'approved invoices; the latest approval was on' }}
                <span dir="ltr">{{ \Illuminate\Support\Carbon::parse($dashboard['latest_approved_at'])->format('Y-m-d') }}</span>.
            </flux:callout>
        @endif

        <div class="sales-overview-grid">
            <section class="dashboard-panel sales-overview-grid__trend">
                <header class="dashboard-panel__header"><div><flux:heading size="lg">{{ $isArabic ? 'اتجاه المبيعات خلال 7 أيام' : 'Seven-day sales trend' }}</flux:heading><flux:text size="sm">{{ $isArabic ? 'القيمة اليومية للفواتير المعتمدة' : 'Daily approved invoice value' }}</flux:text></div><flux:icon.chart-bar class="size-5 text-primary" /></header>
                @if (collect($dashboard['trend'])->sum('count') > 0)
                    <div class="dashboard-trend" role="img" aria-label="{{ $isArabic ? 'رسم مبيعات الأيام السبعة' : 'Seven-day sales chart' }}">
                        @foreach ($dashboard['trend'] as $day)
                            <div class="dashboard-trend__day"><div class="dashboard-trend__bars"><span class="bg-primary" style="height: {{ max(4, ((float) $day['total'] / $maxTrend) * 100) }}%" title="{{ $day['total'] }}"></span></div><span>{{ $day['date']->translatedFormat('D') }}</span></div>
                        @endforeach
                    </div>
                @else
                    <x-state.empty :title="app()->isLocale('ar-EG') ? 'مفيش مبيعات معتمدة خلال الفترة' : ($isArabic ? 'لا توجد مبيعات معتمدة خلال الفترة' : 'No approved sales in this period')" :description="$isArabic ? 'ستظهر الحركة اليومية هنا عند تسجيل أول فاتورة.' : 'Daily activity will appear after the first approved invoice.'" icon="chart-bar" />
                @endif
            </section>
            <section class="dashboard-panel sales-overview-grid__summary">
                <header class="dashboard-panel__header"><flux:heading size="lg">{{ $isArabic ? 'تحصيل اليوم' : "Today's collections" }}</flux:heading><flux:icon.credit-card class="size-5 text-primary" /></header>
                <div class="dashboard-list">
                    @forelse ($dashboard['payment_summary'] as $payment)
                        @php($paymentLabel = $isArabic ? match (strtolower((string) $payment->method_code)) { 'cash' => 'نقدي', 'card' => 'بطاقة', default => $payment->method_code } : $payment->method_code)
                        <div class="dashboard-list__row"><span class="font-medium" dir="auto">{{ $paymentLabel }}</span><x-money :amount="$payment->collected_total" :currency="$dashboard['currency_code']" class="font-semibold" /></div>
                    @empty
                        <x-state.empty :title="app()->isLocale('ar-EG') ? 'مفيش عمليات تحصيل اليوم' : ($isArabic ? 'لا توجد عمليات تحصيل اليوم' : 'No collections today')" icon="credit-card" />
                    @endforelse
                </div>
            </section>
        </div>

        <section class="dashboard-panel">
            <header class="dashboard-panel__header"><div><flux:heading size="lg">{{ $isArabic ? 'المنتجات الأعلى مبيعاً' : 'Top products' }}</flux:heading><flux:text size="sm">{{ $isArabic ? 'آخر سبعة أيام' : 'Last seven days' }}</flux:text></div><flux:icon.trophy class="size-5 text-primary" /></header>
            <div class="dashboard-list">
                @forelse ($dashboard['top_products'] as $product)
                    @php
                        $quantityParts = explode('.', \App\Support\ProductQuantity::format($product->units));
                        $quantityParts[0] = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $quantityParts[0]);
                        $displayQuantity = implode('.', $quantityParts);
                    @endphp
                    <div class="dashboard-list__row"><div class="min-w-0"><p class="truncate font-semibold">{{ $isArabic ? $product->name_ar : $product->name_en }}</p><p class="text-xs text-text-muted" dir="ltr">{{ $product->item_code }}</p></div><div class="text-end"><p class="text-lg font-semibold tabular-nums" dir="ltr">{{ $displayQuantity }}</p><p class="text-xs text-text-muted">{{ $isArabic ? 'الكمية المباعة بالوحدة الأساسية' : 'Sold quantity · base unit' }}</p><x-money :amount="$product->net_total" :currency="$dashboard['currency_code']" class="text-xs text-text-muted" /></div></div>
                @empty
                    <x-state.empty :title="app()->isLocale('ar-EG') ? 'مفيش بيانات منتجات للفترة' : ($isArabic ? 'لا توجد بيانات منتجات للفترة' : 'No product activity for this period')" icon="cube" />
                @endforelse
            </div>
        </section>

        <form method="GET" class="internal-toolbar" aria-label="{{ $isArabic ? 'مرشحات المبيعات' : 'Sales filters' }}">
            <div class="internal-toolbar__grid">
                <flux:input name="q" value="{{ request('q') }}" :label="__('Search')" :placeholder="$isArabic ? 'رقم الفاتورة أو مرجع العملية' : 'Invoice or checkout reference'" />
                <flux:select name="status" :label="__('Status')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach (['draft', 'suspended', 'approved', 'cancelled'] as $status)<flux:select.option value="{{ $status }}" :selected="request('status') === $status">{{ __(ucfirst($status)) }}</flux:select.option>@endforeach</flux:select>
                <flux:select name="store_id" :label="__('Store')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach ($stores as $store)<flux:select.option value="{{ $store->id }}" :selected="(string) request('store_id') === (string) $store->id">{{ $store->code }}</flux:select.option>@endforeach</flux:select>
                <flux:input name="date_from" type="date" value="{{ request('date_from') }}" :label="__('From')" />
                <flux:input name="date_to" type="date" value="{{ request('date_to') }}" :label="__('To')" />
                <div class="flex items-end gap-2"><flux:button type="submit" variant="primary" icon="funnel">{{ __('Filter') }}</flux:button><flux:button href="{{ route('sales.index') }}" variant="ghost">{{ __('Reset') }}</flux:button></div>
            </div>
            <x-tables.filter-chips :filters="$filterLabels" :reset-url="route('sales.index')" />
        </form>

        <x-tables.data-panel :title="$isArabic ? 'أحدث عمليات البيع' : 'Recent sales'" :description="$isArabic ? 'كل حالات البيع ضمن نطاق صلاحياتك.' : 'All sale states within your authorized scope.'">
            <x-slot:actions><flux:badge size="sm" color="zinc">{{ $sales->total() }} {{ __('records') }}</flux:badge></x-slot:actions>
            <table class="data-table responsive-resource-table w-full text-sm">
                <thead><tr><th><a href="{{ $sortUrl('document_number') }}">{{ __('Reference') }}</a></th><th><a href="{{ $sortUrl('status') }}">{{ __('Status') }}</a></th><th>{{ __('Store') }}</th><th>{{ __('Cashier') }}</th><th class="text-end">{{ __('Lines') }}</th><th class="text-end">{{ __('Payments') }}</th><th class="text-end"><a href="{{ $sortUrl('total') }}">{{ __('Total') }}</a></th><th class="text-end">{{ __('Action') }}</th></tr></thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td data-primary><a class="font-semibold text-primary hover:underline" href="{{ route('sales.show', $sale) }}" wire:navigate>{{ $sale->document_number ?: __('Sale #:id', ['id' => $sale->id]) }}</a><div class="text-xs text-text-muted">{{ $isArabic ? 'اعتماد' : 'Approved' }}: {{ ($sale->approved_at ?: $sale->created_at)?->format('Y-m-d H:i') }}</div></td>
                            <td data-label="{{ __('Status') }}"><x-status.badge :status="$sale->status" /></td>
                            <td data-label="{{ __('Store') }}">{{ $isArabic ? $sale->store->name_ar : $sale->store->name_en }}</td>
                            <td data-label="{{ __('Cashier') }}">{{ $sale->cashier->name }}</td>
                            <td data-label="{{ __('Lines') }}" class="text-end tabular-nums">{{ $sale->lines_count }}</td>
                            <td data-label="{{ __('Payments') }}" class="text-end tabular-nums">{{ $sale->payments_count }}</td>
                            <td data-label="{{ __('Total') }}" class="text-end"><x-money :amount="$sale->total" :currency="$sale->currency_code" class="font-semibold" /></td>
                            <td data-label="{{ __('Action') }}" class="text-end"><x-actions.button semantic="view" :label="__('View')" :href="route('sales.show', $sale)" wire:navigate>{{ __('View') }}</x-actions.button></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-state.empty :title="__('No sales match the selected filters.')" :description="__('Try another filter or clear the current filters.')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
            <x-slot:footer><x-tables.pagination :paginator="$sales" /></x-slot:footer>
        </x-tables.data-panel>
    </x-app.page>
</x-layouts::app>
