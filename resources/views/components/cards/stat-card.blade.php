@props([
    'label',
    'value',
    'description' => null,
    'icon' => null,
    'tone' => 'primary',
])

@php
    $toneClasses = [
        'primary' => 'bg-primary-soft text-primary',
        'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
        'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
        'info' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300',
    ];
@endphp

<flux:card {{ $attributes->class('min-w-0 rounded-2xl border-border/80 bg-surface/95 p-5 shadow-card transition-shadow hover:shadow-lg') }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 space-y-1">
            <flux:subheading class="text-xs font-medium text-text-muted">{{ $label }}</flux:subheading>
            <flux:heading size="xl" class="text-text-primary">{{ $value }}</flux:heading>
        </div>

        @if ($icon)
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $toneClasses[$tone] ?? $toneClasses['primary'] }}">
                <flux:icon :name="$icon" class="size-4" />
            </span>
        @endif
    </div>

    @if ($description)
        <flux:text size="sm" class="mt-2 text-text-muted">{{ $description }}</flux:text>
    @endif
</flux:card>
