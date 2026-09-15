<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ str_starts_with(app()->getLocale(), 'ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Shift closing report') }} {{ $shift->closing_document_number }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 14mm; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="bg-white text-zinc-900">
    @php
        $storeName = str_starts_with(app()->getLocale(), 'ar') ? ($shift->store?->name_ar ?: $shift->store_name_ar_snapshot) : ($shift->store?->name_en ?: $shift->store_name_en_snapshot);
        $branchName = str_starts_with(app()->getLocale(), 'ar') ? ($shift->branch?->name_ar ?: $shift->branch_name_ar_snapshot) : ($shift->branch?->name_en ?: $shift->branch_name_en_snapshot);
        $drawerName = str_starts_with(app()->getLocale(), 'ar') ? ($shift->cashDrawer?->name_ar ?: $shift->cash_drawer_name_ar_snapshot) : ($shift->cashDrawer?->name_en ?: $shift->cash_drawer_name_en_snapshot);
    @endphp

    <main class="mx-auto max-w-5xl p-6">
        <div class="no-print mb-5 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('pos.shift-variance') }}" class="text-sm font-medium text-zinc-600 underline">{{ __('Back to shift review') }}</a>
            <button type="button" onclick="window.print()" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-semibold text-white">{{ __('Print') }}</button>
        </div>

        <header class="flex flex-wrap items-start justify-between gap-6 border-b border-zinc-300 pb-5">
            <div>
                <h1 class="text-2xl font-bold">{{ $shift->store?->company?->name_en ?: $shift->company_name_en_snapshot ?: 'TOY & JOY' }}</h1>
                <p class="mt-1 text-sm">{{ $branchName }} · {{ $storeName }}</p>
                <p class="mt-1 text-sm text-zinc-500">{{ $drawerName }}</p>
            </div>
            <div class="text-end">
                <div class="text-sm text-zinc-500">{{ __('Shift closing report') }}</div>
                <div class="font-mono text-lg font-bold">{{ $shift->closing_document_number }}</div>
                <div class="text-sm">{{ $shift->closed_at?->format('Y-m-d H:i') }}</div>
            </div>
        </header>

        <section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-zinc-200 p-4"><div class="text-xs text-zinc-500">{{ __('Shift') }}</div><div class="mt-1 font-semibold">#{{ $shift->id }}</div></div>
            <div class="rounded-lg border border-zinc-200 p-4"><div class="text-xs text-zinc-500">{{ __('Cashier') }}</div><div class="mt-1 font-semibold">{{ $shift->cashier?->name ?: $shift->cashier?->username }}</div></div>
            <div class="rounded-lg border border-zinc-200 p-4"><div class="text-xs text-zinc-500">{{ __('Opened at') }}</div><div class="mt-1 font-semibold">{{ $shift->opened_at?->format('Y-m-d H:i') }}</div></div>
            <div class="rounded-lg border border-zinc-200 p-4"><div class="text-xs text-zinc-500">{{ __('Approval') }}</div><div class="mt-1 font-semibold">{{ $shift->variance_approved_at?->format('Y-m-d H:i') ?: __('Approved') }}</div></div>
        </section>

        <section class="mt-8">
            <h2 class="text-lg font-semibold">{{ __('Payment methods') }}</h2>
            <div class="mt-3 overflow-hidden rounded-lg border border-zinc-200">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50"><tr><th class="p-3 text-start">{{ __('Method') }}</th>@if ($canViewExpected)<th class="p-3 text-end">{{ __('Expected') }}</th>@endif<th class="p-3 text-end">{{ __('Actual') }}</th>@if ($canViewExpected)<th class="p-3 text-end">{{ __('Variance') }}</th>@endif</tr></thead>
                    <tbody>
                        <tr class="border-t border-zinc-200"><td class="p-3 font-medium">{{ __('Cash') }}</td>@if ($canViewExpected)<td class="p-3 text-end"><x-money :amount="$submission->expected_cash" :currency="$shift->currency_code" /></td>@endif<td class="p-3 text-end"><x-money :amount="$submission->actual_cash" :currency="$shift->currency_code" /></td>@if ($canViewExpected)<td class="p-3 text-end font-semibold"><x-money :amount="$submission->cash_variance" :currency="$shift->currency_code" /></td>@endif</tr>
                        @forelse ($methodRows as $row)
                            <tr class="border-t border-zinc-200"><td class="p-3 font-medium">{{ $row['code'] }}</td>@if ($canViewExpected)<td class="p-3 text-end"><x-money :amount="$row['expected']" :currency="$shift->currency_code" /></td>@endif<td class="p-3 text-end"><x-money :amount="$row['actual']" :currency="$shift->currency_code" /></td>@if ($canViewExpected)<td class="p-3 text-end font-semibold"><x-money :amount="$row['variance']" :currency="$shift->currency_code" /></td>@endif</tr>
                        @empty
                            <tr class="border-t border-zinc-200"><td class="p-3 text-zinc-500" colspan="{{ $canViewExpected ? 4 : 2 }}">{{ __('No electronic method totals recorded.') }}</td></tr>
                        @endforelse
                        @if ($canViewExpected)<tr class="border-t-2 border-zinc-300"><td class="p-3 font-bold">{{ __('Total variance') }}</td><td></td><td></td><td class="p-3 text-end font-bold"><x-money :amount="$submission->total_variance" :currency="$shift->currency_code" /></td></tr>@endif
                    </tbody>
                </table>
            </div>
            @unless ($canViewExpected)
                <p class="mt-2 text-sm text-zinc-500">{{ __('Expected and variance detail is restricted to authorized reviewers.') }}</p>
            @endunless
        </section>

        <section class="mt-8 grid gap-6 lg:grid-cols-2">
            <div>
                <h2 class="text-lg font-semibold">{{ __('Cash movements') }}</h2>
                <div class="mt-3 overflow-hidden rounded-lg border border-zinc-200">
                    <table class="w-full text-sm"><thead class="bg-zinc-50"><tr><th class="p-3 text-start">{{ __('Type') }}</th><th class="p-3 text-start">{{ __('Reason') }}</th><th class="p-3 text-end">{{ __('Amount') }}</th></tr></thead><tbody>
                        @forelse ($shift->cashMovements as $movement)
                            <tr class="border-t border-zinc-200"><td class="p-3">{{ __(ucwords(str_replace('_', ' ', $movement->movement_type))) }}</td><td class="p-3">{{ $movement->reason }}</td><td class="p-3 text-end"><x-money :amount="$movement->amount" :currency="$shift->currency_code" /></td></tr>
                        @empty
                            <tr class="border-t border-zinc-200"><td class="p-3 text-zinc-500" colspan="3">{{ __('No cash movements recorded.') }}</td></tr>
                        @endforelse
                    </tbody></table>
                </div>
            </div>
            <div>
                <h2 class="text-lg font-semibold">{{ __('Sales and refunds') }}</h2>
                <dl class="mt-3 divide-y divide-zinc-200 rounded-lg border border-zinc-200 text-sm">
                    <div class="flex justify-between gap-4 p-3"><dt>{{ __('Sales recorded') }}</dt><dd class="font-semibold">{{ $salesCount }} · <x-money :amount="$salesTotal" :currency="$shift->currency_code" /></dd></div>
                    <div class="flex justify-between gap-4 p-3"><dt>{{ __('Refunds recorded') }}</dt><dd class="font-semibold">{{ $refundCount }} · <x-money :amount="$refundTotal" :currency="$shift->currency_code" /></dd></div>
                </dl>
                @if ($salesCount > 0)
                    <ul class="mt-3 space-y-1 text-xs text-zinc-500">
                        @foreach ($shift->sales as $sale)
                            <li class="flex justify-between gap-3"><span>{{ $sale->document_number }}</span><x-money :amount="$sale->payable_total" :currency="$shift->currency_code" /></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <footer class="mt-10 border-t border-zinc-300 pt-4 text-sm text-zinc-600">
            <div class="flex flex-wrap justify-between gap-3"><span>{{ __('Status') }}: {{ __('Approved') }}</span><span>{{ __('Printed from the immutable closed-shift record.') }}</span></div>
        </footer>
    </main>
</body>
</html>
