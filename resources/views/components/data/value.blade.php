@props([
    'value' => null,
    'empty' => '—',
    'numeric' => false,
])

<span {{ $attributes->class(['tabular-nums' => $numeric]) }}>
    {{ filled($value) ? $value : $empty }}
</span>
