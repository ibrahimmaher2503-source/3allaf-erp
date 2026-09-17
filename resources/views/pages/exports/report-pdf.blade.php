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
        th, td { border: 1px solid #d1d5db; padding: 4px; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>TOY &amp; JOY — Dashboard &amp; KPI report</h1>
    <div class="muted">Generated {{ now()->toIso8601String() }} · Date {{ $report['filters']['date_from'] }} to {{ $report['filters']['date_to'] }} · Modules {{ implode(', ', $report['modules']) }}</div>

    <h2>Filters and scope</h2>
    <table><tbody>@foreach($report['filters'] as $key => $value)<tr><th>{{ $key }}</th><td>{{ is_scalar($value) ? $value : (empty($value) ? 'All authorized' : json_encode($value, JSON_UNESCAPED_UNICODE)) }}</td></tr>@endforeach</tbody></table>

    <h2>KPIs</h2>
    <div class="grid">@foreach($report['kpis'] as $key => $value)<div class="cell"><strong>{{ $key }}</strong><br>{{ is_numeric($value) ? number_format((float) $value, 2) : $value }}</div>@endforeach</div>

    <h2>Source reconciliation</h2>
    <table><thead><tr><th>Source</th><th>Value</th></tr></thead><tbody>@foreach($report['sources'] as $key => $value)<tr><td>{{ $key }}</td><td>{{ is_numeric($value) ? number_format((float) $value, 2) : (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value) }}</td></tr>@endforeach</tbody></table>

    @if(($report['sources']['payment_method_summary'] ?? []) !== [])<h2>Payment method summary</h2><table><thead><tr><th>Payment method</th><th>Collected</th></tr></thead><tbody>@foreach($report['sources']['payment_method_summary'] as $payment)<tr><td>{{ $payment['method_code'] }}</td><td>{{ number_format((float) $payment['amount'], 2) }}</td></tr>@endforeach</tbody></table>@endif

    @foreach($report['detail_sections'] ?? [] as $section)
        <h2>{{ $section['title'] }} (bounded detail)</h2>
        <table>
            <thead><tr>@foreach($section['columns'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
            <tbody>@forelse($section['rows'] as $row)<tr>@foreach(array_keys($section['columns']) as $key)<td>{{ is_numeric($row[$key] ?? null) ? number_format((float) $row[$key], 2) : ($row[$key] ?? '—') }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($section['columns']) }}">No matching source rows.</td></tr>@endforelse</tbody>
        </table>
    @endforeach
</body>
</html>
