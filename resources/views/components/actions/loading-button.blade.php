@props([
    'action',
    'label',
    'loadingLabel' => null,
    'variant' => 'primary',
    'type' => 'button',
])

@php
    $variantClasses = match ($variant) {
        'subtle' => 'border-border bg-surface text-text hover:bg-surface-muted',
        'danger' => 'border-red-300 bg-red-50 text-red-700 hover:bg-red-100 dark:border-red-800 dark:bg-red-950/50 dark:text-red-200',
        default => 'border-primary bg-primary text-white hover:brightness-95',
    };
@endphp

<button
    type="{{ $type }}"
    @if ($type !== 'submit') wire:click="{{ $action }}" @endif
    wire:loading.attr="disabled"
    wire:target="{{ $action }}"
    {{ $attributes->class(['inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/50 disabled:cursor-not-allowed disabled:opacity-60', $variantClasses]) }}
>
    <span wire:loading.remove wire:target="{{ $action }}">{{ $label }}</span>
    <span wire:loading.inline-flex wire:target="{{ $action }}" class="items-center gap-2" style="display: none">
        <flux:icon.arrow-path class="size-4 animate-spin" aria-hidden="true" />
        <span>{{ $loadingLabel ?? __('Saving...') }}</span>
    </span>
</button>
