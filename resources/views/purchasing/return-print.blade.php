<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ str_starts_with(app()->getLocale(), 'ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $policy->printTitle() }} {{ $return->return_number ?: '#'.$return->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 2rem; }
        .toolbar { display: flex; justify-content: space-between; gap: 1rem; margin-bottom: 2rem; }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin: 1.5rem 0; }
        .muted { color: #6b7280; font-size: .85rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        th, td { border-bottom: 1px solid #d1d5db; padding: .6rem; text-align: start; }
        .numeric { text-align: end; font-variant-numeric: tabular-nums; direction: ltr; }
        .no-print { display: block; }
        @media (max-width: 720px) { body { margin: 1rem; } .grid { grid-template-columns: 1fr; } }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <a href="{{ route('purchasing.returns.show', $return) }}">{{ __('Back to detail') }}</a>
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
    </div>
    <h1>{{ $policy->printTitle() }}</h1>
    <p class="muted">{{ $return->return_number ?: '#'.$return->id }} · {{ \App\Modules\Platform\Support\UiLabel::status($return->status) }}</p>
    <div class="grid">
        <div><strong>{{ __('Supplier') }}</strong><br>{{ str_starts_with(app()->getLocale(), 'ar') ? ($return->supplier?->name_ar ?: $return->supplier?->name_en) : ($return->supplier?->name_en ?: $return->supplier?->name_ar) }}</div>
        <div><strong>{{ __('Store') }}</strong><br>{{ str_starts_with(app()->getLocale(), 'ar') ? ($return->store?->name_ar ?: $return->store?->name_en) : ($return->store?->name_en ?: $return->store?->name_ar) }}</div>
        <div><strong>{{ __('Original invoice') }}</strong><br>{{ $return->purchaseInvoice?->invoice_number ?: '—' }}</div>
        <div><strong>{{ __('Reason') }}</strong><br>{{ $return->reason?->code }} — {{ str_starts_with(app()->getLocale(), 'ar') ? ($return->reason?->label_ar ?: $return->reason?->label_en) : ($return->reason?->label_en ?: $return->reason?->label_ar) }}</div>
        <div><strong>{{ __('Created by') }}</strong><br>{{ $return->creator?->name ?: '—' }}</div>
        <div><strong>{{ __('Approved by') }}</strong><br>{{ $return->approver?->name ?: '—' }}</div>
    </div>
    <table>
        <thead><tr><th>{{ __('Product') }}</th><th class="numeric">{{ __('Quantity') }}</th><th class="numeric">{{ __('Original unit cost') }}</th><th class="numeric">{{ __('Total cost') }}</th></tr></thead>
        <tbody>
            @foreach($return->lines as $line)
                <tr><td>{{ (str_starts_with(app()->getLocale(), 'ar') ? ($line->product?->name_ar ?: $line->product?->name_en) : ($line->product?->name_en ?: $line->product?->name_ar)) ?: '#'.$line->product_id }}</td><td class="numeric"><x-product-quantity :value="$line->quantity" /></td><td class="numeric">{{ number_format((float) $line->unit_cost, 2) }}</td><td class="numeric">{{ number_format((float) $line->total_cost, 2) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <p class="numeric"><strong>{{ __('Total cost') }}:</strong> {{ number_format((float) $return->total_amount, 2) }}</p>
</body>
</html>
