@props([
    'title',
    'description' => null,
    'badge' => null,
    'badgeColor' => 'zinc',
    'requestId' => null,
    'breadcrumbs' => null,
])

<div {{ $attributes->merge(['class' => 'app-page-header mb-3 w-full space-y-2 border-b border-border pb-3']) }}>
    @if ($breadcrumbs || isset($breadcrumbs))
        <div class="min-w-0 text-xs font-semibold tracking-wide text-text-muted" dir="auto">
            @if (isset($breadcrumbs))
                {{ $breadcrumbs }}
            @else
                {{ $breadcrumbs }}
            @endif
        </div>
    @endif

    <div class="flex min-w-0 flex-col gap-2 md:flex-row md:items-start md:justify-between">
        <div class="min-w-0 space-y-1">
            <div class="flex flex-wrap items-center gap-2.5">
                <flux:heading size="xl" level="1" class="font-bold tracking-[-0.03em] text-text-primary">
                    {{ $title }}
                </flux:heading>

                @if ($badge)
                    @if ($badgeColor === 'primary')
                        <span class="inline-flex items-center rounded-md bg-primary-soft px-2 py-1 text-xs font-medium text-primary ring-1 ring-primary/20">
                            {{ $badge }}
                        </span>
                    @else
                        <flux:badge size="sm" :color="$badgeColor">
                            {{ $badge }}
                        </flux:badge>
                    @endif
                @endif
            </div>

            @if ($description)
                <flux:subheading class="max-w-2xl text-sm leading-6 text-text-muted">
                    {{ $description }}
                </flux:subheading>
            @endif
        </div>

        <div class="flex w-full shrink-0 flex-wrap items-center gap-2 md:w-auto md:justify-end">
            @if ($requestId)
                <flux:badge size="sm" variant="outline" icon="finger-print" class="font-mono text-xs" title="{{ __('Correlation ID') }}">
                    {{ $requestId }}
                </flux:badge>
            @endif

            @if (isset($actions))
                {{ $actions }}
            @elseif ($slot->isNotEmpty())
                {{ $slot }}
            @endif
        </div>
    </div>
</div>
