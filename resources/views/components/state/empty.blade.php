@props([
    'title' => null,
    'description' => null,
    'icon' => 'inbox',
])

<div {{ $attributes->merge(['class' => 'my-3 flex flex-col items-center justify-center space-y-3 rounded-xl border border-dashed border-border bg-surface-muted/40 p-6 text-center']) }} role="status">
    <div class="flex size-10 items-center justify-center rounded-full bg-surface-muted text-text-muted">
        <flux:icon :name="$icon" class="size-6 shrink-0" />
    </div>

    <div class="max-w-md space-y-1">
        <flux:heading level="3" size="lg" class="font-semibold text-text-primary">
            {{ $title ?? __('No records found') }}
        </flux:heading>

        <flux:text class="text-sm leading-6 text-text-muted">
            {{ $description ?? __('No records match the current filters.') }}
        </flux:text>
    </div>

    @if (isset($action))
        <div class="pt-2">
            {{ $action }}
        </div>
    @elseif ($slot->isNotEmpty())
        <div class="pt-2">
            {{ $slot }}
        </div>
    @endif
</div>
