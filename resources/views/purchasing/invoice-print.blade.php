<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ str_starts_with(app()->getLocale(), 'ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Purchase Invoice') }} {{ $invoice->invoice_number ?: __('Draft') }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 2rem; }
        .toolbar { display: flex; justify-content: space-between; margin-bottom: 2rem; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin: 1.5rem 0; }
        .muted { color: #6b7280; font-size: .85rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        th, td { border-bottom: 1px solid #d1d5db; padding: .6rem; text-align: start; }
        .totals { margin-inline-start: auto; max-width: 22rem; margin-top: 1.5rem; }
        .totals div { display: flex; justify-content: space-between; padding: .35rem 0; }
        .total { border-top: 2px solid #111827; font-weight: 700; }
        @media print { .toolbar { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <strong>{{ __('Purchase Invoice') }}</strong>
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
    </div>
    <h1>{{ $invoice->invoice_number ?: __('Draft invoice') }}</h1>
    <div class="grid">
        <div><div class="muted">{{ __('Supplier') }}</div>{{ str_starts_with(app()->getLocale(), 'ar') ? ($invoice->supplier?->name_ar ?: $invoice->supplier?->name_en) : ($invoice->supplier?->name_en ?: $invoice->supplier?->name_ar) }} ({{ $invoice->supplier?->code }})</div>
        <div><div class="muted">{{ __('Receiving store') }}</div>{{ str_starts_with(app()->getLocale(), 'ar') ? ($invoice->store?->name_ar ?: $invoice->store?->name_en) : ($invoice->store?->name_en ?: $invoice->store?->name_ar) }} ({{ $invoice->store?->code }})</div>
        <div><div class="muted">{{ __('Status') }}</div>{{ \App\Modules\Platform\Support\UiLabel::status($invoice->status) }}<br>{{ $invoice->invoice_date?->format('Y-m-d') }}</div>
    </div>
    <table>
        <thead><tr><th>#</th><th>{{ __('Product') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Unit cost') }}</th><th>{{ __('Discount') }}</th><th>{{ __('Tax') }}</th><th>{{ __('Total') }}</th></tr></thead>
        <tbody>
        @foreach ($invoice->lines as $index => $line)
            <tr><td>{{ $index + 1 }}</td><td>{{ $line->product?->item_code }} — {{ str_starts_with(app()->getLocale(), 'ar') ? ($line->product?->name_ar ?: $line->product?->name_en) : ($line->product?->name_en ?: $line->product?->name_ar) }}</td><td class="tabular-nums"><x-product-quantity :value="$line->quantity" /></td><td class="tabular-nums">{{ number_format((float) $line->unit_cost, 2) }} {{ $invoice->currency_code }}</td><td class="tabular-nums">{{ number_format((float) $line->discount_amount, 2) }} {{ $invoice->currency_code }}</td><td class="tabular-nums">{{ number_format((float) $line->tax_amount, 2) }} {{ $invoice->currency_code }}</td><td class="tabular-nums">{{ number_format((float) $line->line_total, 2) }} {{ $invoice->currency_code }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <div class="totals">
        <div><span>{{ __('Subtotal') }}</span><span class="tabular-nums">{{ number_format((float) $invoice->subtotal, 2) }} {{ $invoice->currency_code }}</span></div>
        <div><span>{{ __('Discount') }}</span><span class="tabular-nums">{{ number_format((float) $invoice->discount_amount, 2) }} {{ $invoice->currency_code }}</span></div>
        <div><span>{{ __('Tax') }}</span><span class="tabular-nums">{{ number_format((float) $invoice->tax_amount, 2) }} {{ $invoice->currency_code }}</span></div>
        <div class="total"><span>{{ __('Total') }}</span><span class="tabular-nums">{{ number_format((float) $invoice->total_amount, 2) }} {{ $invoice->currency_code }}</span></div>
    </div>
    @if ($invoice->notes)<p><span class="muted">{{ __('Notes') }}:</span> {{ $invoice->notes }}</p>@endif
</body>
</html>
