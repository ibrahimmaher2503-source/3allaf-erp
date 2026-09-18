<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ str_starts_with(app()->getLocale(),'ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        @include('pages.exports.partials.cairo-pdf-styles')
        @page { margin: 24px; }
        body { font-family: {{ str_starts_with(app()->getLocale(),'ar') ? "'Cairo PDF Arabic'" : "'Cairo PDF Latin'" }}; color: #1f2937; font-size: 9px; direction: {{ str_starts_with(app()->getLocale(),'ar') ? 'rtl' : 'ltr' }}; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 12px; margin: 16px 0 6px; }
        .muted { color: #6b7280; }
        .grid { display: table; width: 100%; table-layout: fixed; }
        .cell { display: table-cell; width: 25%; padding: 5px; border: 1px solid #d1d5db; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px; text-align: {{ str_starts_with(app()->getLocale(),'ar') ? 'right' : 'left' }}; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    @php
        $label = fn (string $key): string => __((string) str($key)->replace('_', ' ')->title());
        $formatValue = fn (mixed $value): string => is_scalar($value)
            ? (is_bool($value) ? ($value ? __('Yes') : __('No')) : (string) $value)
            : (empty($value) ? __('All authorized scope') : json_encode($value, JSON_UNESCAPED_UNICODE));
    @endphp
    <h1>3allaf | علاف · {{ $report['title'] ?? __('Dashboard & KPI reports') }}</h1>
    <div class="muted">{{ __('Generated') }} <bdi>{{ now()->toIso8601String() }}</bdi> · {{ __('Period') }} <bdi>{{ $report['filters']['as_of_date'] ?? $report['filters']['date_from'] ?? '—' }}@if(isset($report['filters']['date_to'])) – {{ $report['filters']['date_to'] }}@endif</bdi></div>

    <h2>{{ __('Filters and scope') }}</h2>
    <table><tbody>@foreach($report['filters'] as $key => $value)<tr><th>{{ $label($key) }}</th><td><bdi>{{ $formatValue($value) }}</bdi></td></tr>@endforeach</tbody></table>

    <h2>{{ __('Key performance indicators') }}</h2>
    <div class="grid">@foreach($report['kpis'] as $key => $value)<div class="cell"><strong>{{ $label($key) }}</strong><br><bdi>{{ is_numeric($value) ? number_format((float) $value, 2) : $value }}</bdi></div>@endforeach</div>

    <h2>{{ __('Source reconciliation') }}</h2>
    <table><thead><tr><th>{{ __('Source') }}</th><th>{{ __('Value') }}</th></tr></thead><tbody>@foreach($report['sources'] as $key => $value)<tr><td>{{ $label($key) }}</td><td><bdi>{{ is_numeric($value) ? number_format((float) $value, 2) : $formatValue($value) }}</bdi></td></tr>@endforeach</tbody></table>

    @if(($report['sources']['payment_method_summary'] ?? []) !== [])<h2>{{ __('Payment method summary') }}</h2><table><thead><tr><th>{{ __('Payment method') }}</th><th>{{ __('Collected') }}</th></tr></thead><tbody>@foreach($report['sources']['payment_method_summary'] as $payment)<tr><td>{{ $payment['method_code'] }}</td><td><bdi>{{ number_format((float) $payment['amount'], 2) }}</bdi></td></tr>@endforeach</tbody></table>@endif

    @foreach($report['detail_sections'] ?? [] as $section)
        <h2>{{ $section['title'] }} · {{ __('Bounded detail') }}</h2>
        <table>
            <thead><tr>@foreach($section['columns'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
            <tbody>@forelse($section['rows'] as $row)<tr>@foreach(array_keys($section['columns']) as $key)<td>{{ is_numeric($row[$key] ?? null) ? number_format((float) $row[$key], 2) : ($row[$key] ?? '—') }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($section['columns']) }}">{{ __('No matching source rows.') }}</td></tr>@endforelse</tbody>
        </table>
    @endforeach
</body>
</html>
