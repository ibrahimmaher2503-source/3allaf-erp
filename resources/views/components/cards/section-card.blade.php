@props([
    'title' => null,
    'description' => null,
])

<flux:card {{ $attributes->class('space-y-5 rounded-2xl border-border/80 bg-surface/95 p-5 shadow-card sm:p-6') }}>
    @if ($title || $description || isset($actions))
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-1">
                @if ($title)
                    <flux:heading size="lg" class="text-text-primary">{{ $title }}</flux:heading>
                @endif
                @if ($description)
                    <flux:text size="sm" class="text-text-muted">{{ $description }}</flux:text>
                @endif
            </div>
            @if (isset($actions))
                <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
            @endif
        </div>
    @endif

    {{ $slot }}
</flux:card>
