@props([
    'amount' => null,
    'currency' => null,
    'precision' => 2,
    'muted' => false,
])

@php
    $formatted = $amount === null || $amount === ''
        ? '—'
        : number_format((float) $amount, (int) $precision, '.', ',');
    $displayCurrency = strtoupper((string) $currency) === 'EGP'
        ? (str_starts_with(app()->getLocale(), 'ar') ? 'ج.م' : 'EGP')
        : $currency;
@endphp

<span
    {{ $attributes->class(['inline-flex items-baseline gap-1 whitespace-nowrap tabular-nums', 'text-text-muted' => $muted]) }}
    dir="ltr"
    data-money
>
    <span>{{ $formatted }}</span>
    @if (filled($currency))
        <span class="text-xs font-medium text-text-muted">{{ $displayCurrency }}</span>
    @endif
</span>
