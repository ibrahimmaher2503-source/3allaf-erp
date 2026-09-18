@php
    $reportLabels = [
        'dashboard' => __('Dashboard & KPI reports'),
        'sales' => __('Sales reports'),
        'customers' => __('Customer & loyalty reports'),
        'cash' => __('Cash & shift reports'),
        'purchasing' => __('Purchasing reports'),
        'inventory' => __('Inventory reports'),
        'sales_summary' => __('Sales Summary'),
        'sales_by_product' => __('Sales by Product'),
        'products_barcodes' => __('Products and barcodes'),
        'customers_groups' => __('Customers and groups'),
        'suppliers_groups' => __('Suppliers and groups'),
        'purchase_orders' => __('Purchase orders'),
        'purchase_invoices_receiving' => __('Purchase invoices and receiving'),
        'opening_inventory' => __('Opening inventory'),
        'inventory_movements' => __('Inventory movements'),
    ];
    $filterLabels = [
        'date_from' => __('From Date'), 'date_to' => __('To Date'), 'branch_id' => __('Branch'),
        'store_id' => __('Store'), 'user_id' => __('User / cashier'), 'supplier_id' => __('Supplier'),
        'customer_id' => __('Customer'), 'customer_group_id' => __('Customer Group'), 'product_id' => __('Product'),
        'product_unit_id' => __('Selling Unit'), 'category_id' => __('Category'), 'payment_method_id' => __('Payment method'),
        'document_status' => __('Document status'), 'party_status' => __('Party status'), 'product_type' => __('Product type'),
        'product_status' => __('Product status'), 'brand_id' => __('Brand'), 'age_label_id' => __('Age'),
        'character_id' => __('Character'), 'colour_id' => __('Colour'), 'gender_id' => __('Gender'),
        'search' => __('Search'), 'sort' => __('Sort'), 'direction' => __('Direction'),
    ];
    $filterValue = function (string $key, mixed $value): string {
        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }
        if (str_ends_with($key, '_id')) {
            return '#'.(string) $value;
        }
        if (in_array($key, ['document_status', 'party_status', 'product_type', 'product_status', 'direction'], true)) {
            return __((string) str((string) $value)->replace('_', ' ')->title());
        }

        return (string) $value;
    };
    $visibleJobs = $jobs->getCollection()->whereNotIn('report_key', ['parties', 'assets']);
@endphp

<x-layouts::app :title="__('Export center')">
    <x-app.page :title="__('Export job center')" :description="__('Private, scoped, bounded PDF and Excel artifacts expire automatically and are downloadable only by their requester.')" :eyebrow="__('Reports & exports')" max-width="7xl">
        <x-slot:actions><flux:button href="{{ route('reports.index') }}" variant="subtle" icon="arrow-left" wire:navigate>{{ __('Back to reports') }}</flux:button></x-slot:actions>

        <flux:callout variant="info" icon="shield-check">{{ __('Exports are permission-aware, formula-safe, queued for background generation, and audited on request, generation, and download.') }}</flux:callout>

        <section class="overflow-hidden rounded-2xl border border-border bg-surface shadow-card" aria-labelledby="export-jobs-heading">
            <header class="border-b border-border bg-surface-muted/40 px-4 py-3 sm:px-5"><h2 id="export-jobs-heading" class="font-semibold text-text-primary">{{ __('Your export requests') }}</h2><p class="mt-1 text-sm text-text-muted">{{ __('Ready files can be viewed or downloaded until their expiry time.') }}</p></header>
            <div class="responsive-resource-table">
                <table class="data-table data-table--mobile-summary min-w-[920px] w-full text-sm">
                    <thead><tr><th>{{ __('Job') }}</th><th>{{ __('Report') }}</th><th>{{ __('Status') }}</th><th>{{ __('Requested filters') }}</th><th>{{ __('Requested') }}</th><th>{{ __('Generated time') }}</th><th>{{ __('Rows') }}</th><th>{{ __('Action') }}</th></tr></thead>
                    <tbody>
                    @forelse($visibleJobs as $job)
                        @php($visibleFilters = collect($job->filters)->except(['locale', 'dataset', 'format'])->reject(fn ($value) => blank($value)))
                        <tr>
                            <td class="font-mono text-xs" dir="ltr">{{ $job->public_id }}</td>
                            <td><span class="font-semibold text-text-primary">{{ $reportLabels[$job->report_key] ?? __((string) str($job->report_key)->replace('_', ' ')->title()) }}</span><span class="mt-1 block text-xs text-text-muted" dir="ltr">{{ strtoupper($job->format) }}</span></td>
                            <td><x-status.badge :status="$job->status" />@if($job->error_message)<details class="mt-2"><summary class="cursor-pointer text-xs font-semibold text-red-700">{{ __('View error') }}</summary><p class="mt-1 max-w-sm text-xs text-red-700">{{ $job->error_message }}</p></details>@endif</td>
                            <td class="max-w-md"><div class="flex flex-wrap gap-1.5">@forelse($visibleFilters as $key => $value)<span class="rounded-full bg-surface-muted px-2.5 py-1 text-xs text-text-muted">{{ $filterLabels[$key] ?? __((string) str($key)->replace('_', ' ')->title()) }}: <bdi>{{ $filterValue($key, $value) }}</bdi></span>@empty<span class="text-xs text-text-muted">{{ __('All authorized scope') }}</span>@endforelse</div></td>
                            <td class="whitespace-nowrap tabular-nums" dir="ltr">{{ $job->created_at?->setTimezone('Africa/Cairo')->format('Y-m-d H:i') ?: '—' }}</td>
                            <td class="whitespace-nowrap tabular-nums" dir="ltr">{{ $job->completed_at?->setTimezone('Africa/Cairo')->format('Y-m-d H:i') ?: '—' }}</td>
                            <td class="tabular-nums" dir="ltr">{{ number_format($job->row_count) }}</td>
                            <td>@if($job->status === 'ready' && $job->expires_at?->isFuture())<div class="flex flex-wrap gap-2"><x-actions.button semantic="view" :label="__('View')" size="sm" variant="subtle" href="{{ route('exports.view', $job) }}" target="_blank">{{ __('View') }}</x-actions.button><x-actions.button semantic="download" :label="__('Download')" size="sm" variant="primary" href="{{ route('exports.download', $job) }}">{{ __('Download') }}</x-actions.button></div>@else<flux:text class="text-sm">{{ $job->status === 'ready' ? __('Expired') : __('Not ready yet') }}</flux:text>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-state.empty :title="__('No export jobs yet.')" :description="__('Run an export from a reconciled report.')" icon="document-arrow-down" /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($jobs->hasPages())<div class="border-t border-border p-4">{{ $jobs->links() }}</div>@endif
        </section>
    </x-app.page>
</x-layouts::app>
