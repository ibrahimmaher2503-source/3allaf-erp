@props([
    'title',
    'description' => null,
])

<section {{ $attributes->class('data-table-panel overflow-hidden rounded-xl border border-border bg-surface shadow-card') }} data-table-panel>
    @if ($title || $description || isset($actions))
        <header class="flex flex-col gap-3 border-b border-border/80 px-4 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-5">
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
        </header>
    @endif

    @if (isset($toolbar))
        <div class="table-panel-toolbar border-b border-border/70 px-4 py-3 sm:px-5" data-table-toolbar>{{ $toolbar }}</div>
    @endif

    <div class="table-panel-surface min-w-0 overflow-x-auto" data-table-surface>
        {{ $slot }}
    </div>

    @if (isset($footer))
        <footer class="table-panel-footer border-t border-border/80 px-4 py-3 sm:px-5">{{ $footer }}</footer>
    @endif
</section>
