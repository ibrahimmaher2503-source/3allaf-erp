@props([
    'label' => null,
])

<div
    {{ $attributes->class('app-table-frame table-shell') }}
    data-table-shell
    tabindex="0"
    role="region"
    aria-label="{{ $label ?? __('Data table') }}"
>
    {{ $slot }}
</div>
