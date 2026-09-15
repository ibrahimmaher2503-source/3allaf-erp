@props(['dashboard'])

@php
    $isArabic = str_starts_with(app()->getLocale(), 'ar');
    $maxTrend = max(1, (float) collect($dashboard['trend'])->max('total'));
@endphp

<section class="dashboard-kpi-grid" aria-label="{{ $isArabic ? 'مؤشرات المشتريات' : 'Purchasing indicators' }}">
    <a href="{{ route('purchasing.invoices') }}" class="dashboard-kpi dashboard-kpi--priority">
        <div><p class="dashboard-kpi__label">{{ $isArabic ? 'مشتريات اليوم' : "Today's purchases" }}</p><p class="dashboard-kpi__value"><x-money :amount="$dashboard['today_total']" :currency="$dashboard['currency_code']" /></p><p class="dashboard-kpi__state">{{ number_format($dashboard['today_count']) }} {{ $isArabic ? 'فاتورة معتمدة' : 'approved invoices' }}</p></div><span class="dashboard-kpi__icon"><flux:icon.banknotes class="size-5" /></span>
    </a>
    <a href="{{ route('purchasing.invoices') }}" class="dashboard-kpi">
        <div><p class="dashboard-kpi__label">{{ $isArabic ? 'مشتريات 7 أيام' : 'Seven-day purchases' }}</p><p class="dashboard-kpi__value"><x-money :amount="$dashboard['period_total']" :currency="$dashboard['currency_code']" /></p><p class="dashboard-kpi__state">{{ number_format($dashboard['period_count']) }} {{ $isArabic ? 'فاتورة معتمدة' : 'approved invoices' }}</p></div><span class="dashboard-kpi__icon"><flux:icon.receipt-percent class="size-5" /></span>
    </a>
    <a href="#purchase-orders-queue" class="dashboard-kpi">
        <div><p class="dashboard-kpi__label">{{ $isArabic ? 'أوامر الشراء المفتوحة' : 'Open purchase orders' }}</p><p class="dashboard-kpi__value">{{ number_format($dashboard['open_orders']) }}</p><p class="dashboard-kpi__state">{{ $isArabic ? 'مسودة أو اعتماد أو استلام' : 'Draft, approval, or receiving' }}</p></div><span class="dashboard-kpi__icon"><flux:icon.document-text class="size-5" /></span>
    </a>
    @can('purchase_returns.view')<a href="{{ route('purchasing.returns') }}" class="dashboard-kpi">
        <div><p class="dashboard-kpi__label">{{ $isArabic ? 'مرتجعات تحتاج إجراء' : 'Returns requiring action' }}</p><p class="dashboard-kpi__value">{{ number_format($dashboard['returns_requiring_action']) }}</p><p class="dashboard-kpi__state">{{ $isArabic ? 'مسودة أو قيد الاعتماد' : 'Draft or pending approval' }}</p></div><span class="dashboard-kpi__icon"><flux:icon.arrow-path class="size-5" /></span>
    </a>@endcan
</section>

<div class="grid gap-4 lg:grid-cols-12">
    <section class="dashboard-panel lg:col-span-8">
        <header class="dashboard-panel__header"><div><flux:heading size="lg">{{ $isArabic ? 'اتجاه المشتريات خلال 7 أيام' : 'Seven-day purchasing trend' }}</flux:heading><flux:text size="sm">{{ $isArabic ? 'إجمالي الفواتير المعتمدة يومياً' : 'Daily approved supplier invoice value' }}</flux:text></div><flux:icon.chart-bar class="size-5 text-primary" /></header>
        @if (collect($dashboard['trend'])->sum('total') > 0)
            <div class="dashboard-trend" role="img" aria-label="{{ $isArabic ? 'رسم المشتريات للأيام السبعة' : 'Seven-day purchasing chart' }}">@foreach($dashboard['trend'] as $day)<div class="dashboard-trend__day"><div class="dashboard-trend__bars"><span class="bg-primary" style="height: {{ max(4, ((float) $day['total'] / $maxTrend) * 100) }}%" title="{{ $day['total'] }}"></span></div><span>{{ $day['date']->translatedFormat('D') }}</span></div>@endforeach</div>
        @else
            <div class="dashboard-trend-empty"><flux:icon.chart-bar class="size-6" /><strong>{{ app()->isLocale('ar-EG') ? 'مفيش مشتريات معتمدة خلال الفترة' : ($isArabic ? 'لا توجد مشتريات معتمدة خلال الفترة' : 'No approved purchases in this period') }}</strong><div class="dashboard-trend-empty__dates">@foreach($dashboard['trend'] as $day)<span>{{ $day['date']->translatedFormat('D') }}</span>@endforeach</div></div>
        @endif
    </section>
    <section class="dashboard-panel lg:col-span-4">
        <header class="dashboard-panel__header"><flux:heading size="lg">{{ $isArabic ? 'حالة الاستلام' : 'Receiving status' }}</flux:heading><flux:icon.inbox-arrow-down class="size-5 text-primary" /></header>
        <div class="dashboard-list"><div class="dashboard-list__row"><span>{{ $isArabic ? 'بانتظار الاستلام' : 'Pending receiving' }}</span><strong>{{ $dashboard['receiving']['pending'] }}</strong></div><div class="dashboard-list__row"><span>{{ $isArabic ? 'استلام جزئي' : 'Partially received' }}</span><strong>{{ $dashboard['receiving']['partial'] }}</strong></div><div class="dashboard-list__row"><span>{{ $isArabic ? 'مكتمل أو مغلق' : 'Completed or closed' }}</span><strong>{{ $dashboard['receiving']['completed'] }}</strong></div><div class="dashboard-list__row"><span>{{ $isArabic ? 'اعتمادات معلقة' : 'Pending approvals' }}</span><strong>{{ $dashboard['pending_approvals'] }}</strong></div></div>
    </section>
</div>

<div class="grid gap-4 xl:grid-cols-2">
    <section class="dashboard-panel"><header class="dashboard-panel__header"><flux:heading size="lg">{{ $isArabic ? 'أعلى الموردين خلال 7 أيام' : 'Top suppliers · 7 days' }}</flux:heading><flux:icon.building-storefront class="size-5 text-primary" /></header><div class="dashboard-list">@forelse($dashboard['top_suppliers'] as $supplier)<a href="{{ route('catalog.suppliers') }}" class="dashboard-list__row"><span class="min-w-0"><strong class="block truncate">{{ $isArabic ? $supplier->name_ar : ($supplier->name_en ?: $supplier->name_ar) }}</strong><small class="font-mono text-text-muted">{{ $supplier->code }} · {{ $supplier->invoice_count }} {{ $isArabic ? 'فاتورة' : 'invoices' }}</small></span><x-money :amount="$supplier->purchase_total" :currency="$dashboard['currency_code']" class="font-semibold" /></a>@empty<x-state.empty :title="app()->isLocale('ar-EG') ? 'مفيش مشتريات موردين خلال الفترة' : ($isArabic ? 'لا توجد مشتريات موردين خلال الفترة' : 'No supplier purchases in this period')" icon="building-storefront" />@endforelse</div></section>
    <section class="dashboard-panel"><header class="dashboard-panel__header"><flux:heading size="lg">{{ $isArabic ? 'المنتجات الأعلى شراءً' : 'Top purchased products' }}</flux:heading><flux:icon.cube class="size-5 text-primary" /></header><div class="dashboard-list">@forelse($dashboard['top_products'] as $product)<div class="dashboard-list__row"><span class="min-w-0"><strong class="block truncate">{{ $isArabic ? $product->name_ar : ($product->name_en ?: $product->name_ar) }}</strong><small class="font-mono text-text-muted">{{ $product->item_code }}</small></span><span class="text-end"><strong class="block tabular-nums">{{ $product->units }} {{ $isArabic ? 'وحدة' : 'units' }}</strong><x-money :amount="$product->purchase_total" :currency="$dashboard['currency_code']" class="text-xs text-text-muted" /></span></div>@empty<x-state.empty :title="app()->isLocale('ar-EG') ? 'مفيش بنود شراء خلال الفترة' : ($isArabic ? 'لا توجد بنود شراء خلال الفترة' : 'No purchased products in this period')" icon="cube" />@endforelse</div></section>
</div>

<section class="dashboard-panel">
    <header class="dashboard-panel__header"><div><flux:heading size="lg">{{ $isArabic ? 'آخر النشاطات الشرائية' : 'Recent purchasing activity' }}</flux:heading><flux:text size="sm">{{ $isArabic ? 'آخر الفواتير والأوامر والمرتجعات ضمن نطاقك' : 'Latest invoices, orders, and returns in your scope' }}</flux:text></div><flux:icon.clock class="size-5 text-primary" /></header>
    <div class="grid gap-3 lg:grid-cols-3">
        @foreach ([['key' => 'recent_invoices', 'route' => 'purchasing.invoices', 'ar' => 'الفواتير', 'en' => 'Invoices', 'number' => 'invoice_number', 'date' => 'invoice_date'], ['key' => 'recent_orders', 'route' => 'purchasing.orders', 'ar' => 'أوامر الشراء', 'en' => 'Purchase orders', 'number' => 'po_number', 'date' => 'order_date'], ['key' => 'recent_returns', 'route' => 'purchasing.returns', 'ar' => 'المرتجعات', 'en' => 'Returns', 'number' => 'return_number', 'date' => 'return_date']] as $list)
            <div class="rounded-xl border border-border p-3"><a href="{{ route($list['route']) }}" class="mb-2 flex min-h-11 items-center justify-between font-semibold text-primary"><span>{{ $isArabic ? $list['ar'] : $list['en'] }}</span><flux:icon.arrow-up-right class="size-4 rtl:-scale-x-100" /></a><div class="dashboard-list">
                @forelse ($dashboard[$list['key']] as $record)<div class="dashboard-list__row"><span class="min-w-0"><strong class="block truncate">{{ $record->{$list['number']} ?: '#'.$record->id }}</strong><small class="block truncate text-text-muted">{{ $isArabic ? ($record->supplier?->name_ar ?: $record->supplier?->name_en) : ($record->supplier?->name_en ?: $record->supplier?->name_ar) }}</small></span><span class="shrink-0 text-xs tabular-nums text-text-muted">{{ $record->{$list['date']}?->format('Y-m-d') }}</span></div>@empty<p class="py-3 text-sm text-text-muted">{{ app()->isLocale('ar-EG') ? 'مفيش سجلات حديثة' : ($isArabic ? 'لا توجد سجلات حديثة' : 'No recent records') }}</p>@endforelse
            </div></div>
        @endforeach
    </div>
</section>
