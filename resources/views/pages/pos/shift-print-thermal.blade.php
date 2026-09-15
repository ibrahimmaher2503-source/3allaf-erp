<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ str_starts_with(app()->getLocale(), 'ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Shift closing receipt') }} {{ $shift->closing_document_number }}</title>
    <style>
        @page { size: 80mm auto; margin: 4mm; }
        * { box-sizing: border-box; }
        body { width: 72mm; margin: 0 auto; color: #111827; font-family: Arial, sans-serif; font-size: 11px; }
        h1, h2, p { margin: 0; }
        h1 { font-size: 15px; }
        h2 { font-size: 12px; margin-top: 10px; }
        .center { text-align: center; }
        .row { display: flex; justify-content: space-between; gap: 8px; margin: 4px 0; }
        .label { color: #4b5563; }
        .value { font-weight: 700; text-align: end; }
        .rule { border-top: 1px dashed #374151; margin: 9px 0; }
        .small { color: #4b5563; font-size: 9px; }
        .notice { border: 1px solid #9ca3af; padding: 6px; margin-top: 8px; }
        @media print { .no-print { display: none !important; } body { width: auto; } }
    </style>
</head>
<body>
    @php
        $storeName = str_starts_with(app()->getLocale(), 'ar') ? ($shift->store?->name_ar ?: $shift->store_name_ar_snapshot) : ($shift->store?->name_en ?: $shift->store_name_en_snapshot);
        $drawerName = str_starts_with(app()->getLocale(), 'ar') ? ($shift->cashDrawer?->name_ar ?: $shift->cash_drawer_name_ar_snapshot) : ($shift->cashDrawer?->name_en ?: $shift->cash_drawer_name_en_snapshot);
    @endphp

    <header class="center">
        <h1>{{ $shift->store?->company?->name_en ?: $shift->company_name_en_snapshot ?: 'TOY & JOY' }}</h1>
        <p>{{ $storeName ?: $shift->store_code_snapshot }}</p>
        <p class="small">{{ __('Shift closing receipt') }} · {{ $shift->closing_document_number }}</p>
    </header>

    <div class="rule"></div>
    <div class="row"><span class="label">{{ __('Shift') }}</span><span class="value">#{{ $shift->id }}</span></div>
    <div class="row"><span class="label">{{ __('Cash drawer') }}</span><span class="value">{{ $shift->cashDrawer?->code ?: $shift->cash_drawer_code_snapshot }} · {{ $drawerName }}</span></div>
    <div class="row"><span class="label">{{ __('Cashier') }}</span><span class="value">{{ $shift->cashier?->name ?: $shift->cashier?->username }}</span></div>
    <div class="row"><span class="label">{{ __('Opened at') }}</span><span class="value">{{ $shift->opened_at?->format('Y-m-d H:i') }}</span></div>
    <div class="row"><span class="label">{{ __('Closed at') }}</span><span class="value">{{ $shift->closed_at?->format('Y-m-d H:i') }}</span></div>

    <div class="rule"></div>
    <h2>{{ __('Actual totals') }}</h2>
    <div class="row"><span class="label">{{ __('Cash') }}</span><span class="value"><x-money :amount="$submission->actual_cash" :currency="$shift->currency_code" /></span></div>
    @foreach ($methodRows as $row)
        <div class="row"><span class="label">{{ $row['code'] }}</span><span class="value"><x-money :amount="$row['actual']" :currency="$shift->currency_code" /></span></div>
    @endforeach

    @if ($canViewExpected)
        <div class="rule"></div>
        <h2>{{ __('Variance summary') }}</h2>
        <div class="row"><span class="label">{{ __('Expected cash') }}</span><span class="value"><x-money :amount="$submission->expected_cash" :currency="$shift->currency_code" /></span></div>
        <div class="row"><span class="label">{{ __('Cash variance') }}</span><span class="value"><x-money :amount="$submission->cash_variance" :currency="$shift->currency_code" /></span></div>
        <div class="row"><span class="label">{{ __('Total variance') }}</span><span class="value"><x-money :amount="$submission->total_variance" :currency="$shift->currency_code" /></span></div>
    @else
        <p class="notice">{{ __('Variance details are restricted to authorized reviewers.') }}</p>
    @endif

    <div class="rule"></div>
    <div class="row"><span class="label">{{ __('Approval') }}</span><span class="value">{{ $shift->variance_approved_at?->format('Y-m-d H:i') ?: __('Approved') }}</span></div>
    <p class="center small">{{ __('Printed from the immutable closed-shift record.') }}</p>
    <button class="no-print" type="button" onclick="window.print()">{{ __('Print') }}</button>
</body>
</html>
