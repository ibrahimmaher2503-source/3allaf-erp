@props(['active'])

<nav class="flex gap-2 overflow-x-auto rounded-xl border border-border bg-surface p-2" aria-label="{{ __('Sales report navigation') }}">
    <a href="{{ route('reports.index') }}" class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-lg border border-border px-3 text-sm font-semibold text-text-muted transition hover:border-primary/40 hover:text-text-primary">
        <flux:icon name="squares-2x2" class="size-4" />{{ __('All reports') }}
    </a>
    <a href="{{ route('reports.sales-summary') }}" @class(['inline-flex min-h-10 shrink-0 items-center gap-2 rounded-lg border px-3 text-sm font-semibold transition', 'border-primary bg-primary text-white' => $active === 'summary', 'border-border text-text-muted hover:border-primary/40 hover:text-text-primary' => $active !== 'summary']) aria-current="{{ $active === 'summary' ? 'page' : 'false' }}">
        <flux:icon name="presentation-chart-line" class="size-4" />{{ __('Sales Summary') }}
    </a>
    <a href="{{ route('reports.sales-by-product') }}" @class(['inline-flex min-h-10 shrink-0 items-center gap-2 rounded-lg border px-3 text-sm font-semibold transition', 'border-primary bg-primary text-white' => $active === 'product', 'border-border text-text-muted hover:border-primary/40 hover:text-text-primary' => $active !== 'product']) aria-current="{{ $active === 'product' ? 'page' : 'false' }}">
        <flux:icon name="cube" class="size-4" />{{ __('Sales by Product') }}
    </a>
    @if(Gate::allows('dashboard_reports.export_xlsx') || Gate::allows('dashboard_reports.export_pdf'))
        <a href="{{ route('exports.index') }}" class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-lg border border-border px-3 text-sm font-semibold text-text-muted transition hover:border-primary/40 hover:text-text-primary">
            <flux:icon name="archive-box-arrow-down" class="size-4" />{{ __('Export center') }}
        </a>
    @endif
</nav>
