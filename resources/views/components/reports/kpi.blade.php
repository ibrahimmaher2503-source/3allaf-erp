@props([
    'label',
    'value',
    'description' => null,
    'icon' => 'chart-bar',
    'tone' => 'primary',
    'href' => null,
])

@php
    $tones = [
        'primary' => ['surface' => 'bg-teal-50/75 dark:bg-teal-950/25', 'icon' => 'bg-teal-600 text-teal-50 dark:bg-teal-500 dark:text-teal-950', 'ring' => 'border-teal-200/80 dark:border-teal-800/70', 'link' => 'text-teal-700 dark:text-teal-300'],
        'info' => ['surface' => 'bg-sky-50/75 dark:bg-sky-950/25', 'icon' => 'bg-sky-600 text-sky-50 dark:bg-sky-500 dark:text-sky-950', 'ring' => 'border-sky-200/80 dark:border-sky-800/70', 'link' => 'text-sky-700 dark:text-sky-300'],
        'success' => ['surface' => 'bg-emerald-50/75 dark:bg-emerald-950/25', 'icon' => 'bg-emerald-600 text-emerald-50 dark:bg-emerald-500 dark:text-emerald-950', 'ring' => 'border-emerald-200/80 dark:border-emerald-800/70', 'link' => 'text-emerald-700 dark:text-emerald-300'],
        'warning' => ['surface' => 'bg-amber-50/80 dark:bg-amber-950/25', 'icon' => 'bg-amber-500 text-amber-950 dark:bg-amber-400', 'ring' => 'border-amber-200/90 dark:border-amber-800/70', 'link' => 'text-amber-800 dark:text-amber-300'],
        'danger' => ['surface' => 'bg-rose-50/80 dark:bg-rose-950/25', 'icon' => 'bg-rose-600 text-rose-50 dark:bg-rose-500 dark:text-rose-950', 'ring' => 'border-rose-200/90 dark:border-rose-800/70', 'link' => 'text-rose-700 dark:text-rose-300'],
        'violet' => ['surface' => 'bg-violet-50/75 dark:bg-violet-950/25', 'icon' => 'bg-violet-600 text-violet-50 dark:bg-violet-500 dark:text-violet-950', 'ring' => 'border-violet-200/80 dark:border-violet-800/70', 'link' => 'text-violet-700 dark:text-violet-300'],
    ];
    $palette = $tones[$tone] ?? $tones['primary'];
@endphp

<article data-report-kpi {{ $attributes->class("group relative min-w-0 overflow-hidden rounded-2xl border p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md sm:p-5 {$palette['surface']} {$palette['ring']}") }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-text-muted">{{ $label }}</p>
            <p class="mt-2 truncate text-2xl font-bold tabular-nums tracking-tight text-text-primary" dir="ltr">{{ $value }}</p>
        </div>
        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl shadow-sm {{ $palette['icon'] }}">
            <flux:icon :name="$icon" class="size-5" />
        </span>
    </div>

    @if($description || $href)
        <div class="mt-4 flex items-end justify-between gap-3 border-t border-current/10 pt-3">
            @if($description)<p class="text-xs leading-5 text-text-muted">{{ $description }}</p>@endif
            @if($href)
                <a href="{{ $href }}" class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold {{ $palette['link'] }} hover:underline focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">
                    {{ __('Explore') }}
                    <flux:icon name="arrow-up-right" class="size-3.5 rtl:rotate-[-90deg]" />
                </a>
            @endif
        </div>
    @endif
</article>
