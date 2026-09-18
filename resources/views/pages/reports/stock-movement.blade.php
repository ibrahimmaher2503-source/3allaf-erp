@php
    $isArabic = str_starts_with(app()->getLocale(), 'ar');
    $t = fn(string $ar, string $en) => $isArabic ? $ar : $en;
    $qty = fn($value) => \App\Support\ProductQuantity::format($value);
    $selectedProduct = $report['product'] ?? null;
@endphp
<x-layouts::app :title="$t('كارت حركة الصنف', 'Stock Movement Card')">
<div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="max-w-3xl"><p class="text-xs font-semibold text-primary">{{ $t('تقارير المخزون', 'Inventory reports') }}</p><flux:heading level="1" size="xl">{{ $t('كارت حركة الصنف', 'Stock Movement Card') }}</flux:heading><flux:text class="mt-1">{{ $t('رصيد أول المدة وكل حركة فعلية مرحلة حتى رصيد آخر المدة.', 'Opening balance and every posted movement through the closing balance.') }}</flux:text></div>
        @if($report)<form method="POST" action="{{ route('reports.export') }}" class="flex flex-wrap gap-2">@csrf<input type="hidden" name="dataset" value="stock_movement">@foreach($report['filters'] as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            @can('dashboard_reports.export_xlsx')<flux:button type="submit" name="format" value="csv" variant="subtle">CSV</flux:button><flux:button type="submit" name="format" value="xlsx" variant="subtle">XLSX</flux:button>@endcan
            @can('dashboard_reports.export_pdf')<flux:button type="submit" name="format" value="pdf" variant="primary">PDF</flux:button>@endcan
        </form>@endif
    </header>
    <nav class="flex flex-wrap gap-2" aria-label="{{ $t('تقارير المخزون', 'Inventory reports') }}"><flux:button href="{{ route('reports.stock-movement') }}" variant="primary">{{ $t('كارت حركة الصنف', 'Stock Movement Card') }}</flux:button>@can('inventory_stock_card.cost_view')<flux:button href="{{ route('reports.inventory-valuation') }}" variant="subtle">{{ $t('تقييم المخزون', 'Inventory Valuation') }}</flux:button>@endcan</nav>
    @if($errors->any())<flux:callout variant="danger" icon="exclamation-triangle"><ul class="list-disc ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></flux:callout>@endif

    <section class="overflow-hidden rounded-2xl border border-border bg-surface shadow-card" aria-labelledby="movement-filters"><header class="border-b border-border bg-surface-muted/40 px-4 py-3"><h2 id="movement-filters" class="font-semibold">{{ $t('حدد الصنف والفترة', 'Choose product and period') }}</h2><p class="mt-1 text-sm text-text-muted">{{ $t('يجب اختيار صنف ومخزن واحد حتى تظل الحركة سريعة ودقيقة.', 'Choose one product and store to keep the ledger fast and exact.') }}</p></header>
        <form method="GET" action="{{ route('reports.stock-movement') }}" class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2"><x-product-line-lookup name="product_id" :value="$selectedProduct?->id ?? request('product_id')" :display="$selectedProduct ? (($isArabic ? $selectedProduct->name_ar : $selectedProduct->name_en).' · '.$selectedProduct->item_code) : ''" :label="$t('الصنف', 'Product')" :required="true" /></div>
            <flux:select name="store_id" :label="$t('المخزن', 'Store')" required>@foreach($stores as $store)<option value="{{ $store->id }}" @selected((int)request('store_id', $report['store']->id ?? 0)===$store->id)>{{ $store->code }} · {{ $isArabic ? $store->name_ar : $store->name_en }}</option>@endforeach</flux:select>
            <flux:input type="date" name="date_from" :label="$t('من تاريخ', 'From date')" value="{{ request('date_from', $report['filters']['date_from'] ?? now('Africa/Cairo')->subDays(30)->toDateString()) }}" required />
            <flux:input type="date" name="date_to" :label="$t('إلى تاريخ', 'To date')" value="{{ request('date_to', $report['filters']['date_to'] ?? now('Africa/Cairo')->toDateString()) }}" required />
            <div class="flex items-end"><flux:button type="submit" variant="primary" icon="magnifying-glass" class="w-full">{{ $t('عرض الحركة', 'Show ledger') }}</flux:button></div>
            <flux:select name="movement_type" :label="$t('نوع الحركة (اختياري)', 'Movement type (optional)')"><option value="">{{ $t('كل الحركات', 'All movements') }}</option>@foreach(\App\Modules\Inventory\Support\InventoryMovementLabel::options() as $code=>$label)<option value="{{ $code }}" @selected(request('movement_type')===$code)>{{ $label }}</option>@endforeach</flux:select>
            <flux:input name="reference" :label="$t('رقم المستند (اختياري)', 'Document number (optional)')" value="{{ request('reference') }}" />
        </form>
    </section>

    @if(!$report)
        <x-state.empty :title="$t('اختر صنفًا لفتح كارت الحركة', 'Select a product to open its movement card')" :description="$t('سيظهر رصيد أول المدة، الوارد، الصادر، والرصيد بعد كل حركة.', 'Opening, incoming, outgoing, and the balance after every movement will appear here.')" />
    @else
        <section class="flex flex-wrap items-center gap-x-8 gap-y-2 border-y border-border py-4 text-sm"><div><span class="text-text-muted">{{ $t('الصنف', 'Product') }}</span><strong class="ms-2">{{ $isArabic ? $report['product']->name_ar : $report['product']->name_en }}</strong><span class="ms-2 font-mono">{{ $report['product']->item_code }}</span></div><div><span class="text-text-muted">{{ $t('المخزن', 'Store') }}</span><strong class="ms-2">{{ $report['store']->code }}</strong></div><div><span class="text-text-muted">{{ $t('الوحدة الأساسية', 'Base unit') }}</span><strong class="ms-2">{{ $report['base_unit'] ?: '—' }}</strong></div></section>
        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <x-reports.kpi :label="$t('رصيد أول المدة', 'Opening balance')" :value="$qty($report['opening']).' '.($report['base_unit'] ?: '')" :description="$t('كل الحركات قبل تاريخ البداية', 'All movements before the start date')" icon="arrow-right-circle" tone="info" />
            <x-reports.kpi :label="$t('إجمالي الوارد', 'Total incoming')" :value="$qty($report['incoming']).' '.($report['base_unit'] ?: '')" :description="$t('حركات الزيادة المرحلة', 'Posted increases')" icon="arrow-down-tray" tone="success" />
            <x-reports.kpi :label="$t('إجمالي الصادر', 'Total outgoing')" :value="$qty($report['outgoing']).' '.($report['base_unit'] ?: '')" :description="$t('حركات النقص المرحلة', 'Posted decreases')" icon="arrow-up-tray" tone="danger" />
            <x-reports.kpi :label="$t('صافي الحركة', 'Net movement')" :value="$qty($report['net']).' '.($report['base_unit'] ?: '')" :description="$t('الوارد ناقص الصادر', 'Incoming less outgoing')" icon="arrows-right-left" tone="warning" />
            <x-reports.kpi :label="$t('رصيد آخر المدة', 'Closing balance')" :value="$qty($report['closing']).' '.($report['base_unit'] ?: '')" :description="$t('أول المدة + الوارد - الصادر', 'Opening + incoming - outgoing')" icon="check-circle" tone="primary" />
        </section>
        <section class="overflow-hidden rounded-2xl border border-border bg-surface shadow-card"><header class="border-b border-border px-4 py-3"><h2 class="font-semibold">{{ $t('دفتر حركة الصنف', 'Product movement ledger') }}</h2><p class="mt-1 text-sm text-text-muted">{{ $t('الترتيب حسب وقت الترحيل ثم رقم الحركة، والكميات الأساسية لا تُجمع مع وحدات مختلفة.', 'Ordered by posting time then movement ID; base quantities never mix unlike units.') }}</p></header>
            <div class="overflow-x-auto"><table class="data-table min-w-[1180px] w-full"><thead><tr><th>{{ $t('التاريخ والوقت', 'Date & time') }}</th><th>{{ $t('الحركة', 'Movement') }}</th><th>{{ $t('المستند', 'Document') }}</th><th>{{ $t('الكمية الأصلية', 'Original quantity') }}</th><th class="text-end">{{ $t('وارد', 'In') }}</th><th class="text-end">{{ $t('صادر', 'Out') }}</th><th class="text-end">{{ $t('الكمية الأساسية', 'Base quantity') }}</th><th class="text-end">{{ $t('الرصيد', 'Balance') }}</th><th>{{ $t('المستخدم', 'Actor') }}</th><th>{{ $t('عرض', 'View') }}</th></tr></thead><tbody>
            @forelse($report['rows'] as $row)@php($direction=bccomp((string)$row->base_quantity,'0',6))<tr><td class="whitespace-nowrap" dir="ltr">{{ \Carbon\CarbonImmutable::parse($row->posted_at,'UTC')->setTimezone('Africa/Cairo')->format('Y-m-d H:i') }}</td><td><x-status.badge :status="$row->movement_type" :label="$row->movement_label" />@if($row->reason)<span class="mt-1 block max-w-52 text-xs text-text-muted">{{ $row->reason }}</span>@endif</td><td><span class="block">{{ $row->source_label }}</span><span class="font-mono text-xs text-text-muted" dir="ltr">{{ $row->document_number ?: '#'.$row->source_id }}</span></td><td dir="ltr">{{ $qty($row->original_quantity) }} {{ $row->original_unit }}</td><td class="text-end font-semibold text-emerald-700" dir="ltr">{{ $direction>0 ? $qty($row->base_quantity) : '—' }}</td><td class="text-end font-semibold text-rose-700" dir="ltr">{{ $direction<0 ? $qty(ltrim((string)$row->base_quantity,'-')) : '—' }}</td><td class="text-end" dir="ltr">{{ $qty($row->base_quantity) }} {{ $row->base_unit }}</td><td class="text-end font-bold" dir="ltr">{{ $qty($row->running_balance) }} {{ $row->base_unit }}</td><td>{{ $row->actor_name ?: '—' }}</td><td>@if($row->source_url)<flux:button size="sm" href="{{ $row->source_url }}">{{ $t('المصدر', 'Source') }}</flux:button>@else—@endif</td></tr>
            @empty<tr><td colspan="10"><x-state.empty :title="$t('لا توجد حركات للصنف خلال الفترة المحددة', 'No movements for this product in the selected period')" :description="$t('يظل رصيد أول وآخر المدة ظاهرًا بالأعلى.', 'Opening and closing balances remain visible above.')" /></td></tr>@endforelse
            </tbody></table></div>@if($report['rows'] instanceof \Illuminate\Pagination\LengthAwarePaginator && $report['rows']->hasPages())<div class="border-t border-border p-4">{{ $report['rows']->links() }}</div>@endif
        </section>
    @endif
</div>
</x-layouts::app>
