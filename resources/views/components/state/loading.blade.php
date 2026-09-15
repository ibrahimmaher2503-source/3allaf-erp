@props([
    'title' => null,
    'description' => null,
    'inline' => false,
])

@if ($inline)
    <div {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5 text-sm text-zinc-500 dark:text-zinc-400']) }} role="status">
        <flux:icon name="arrow-path" class="size-4 animate-spin text-accent shrink-0" />
        <span>{{ $title ?? __('Loading data...') }}</span>
        <span class="sr-only">{{ __('Loading...') }}</span>
    </div>
@else
    <div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center p-12 text-center rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 my-4 space-y-3']) }} role="status">
        <div class="flex items-center justify-center size-12 rounded-full bg-primary-soft text-primary">
            <flux:icon name="arrow-path" class="size-6 animate-spin shrink-0" />
        </div>

        <div class="space-y-1 max-w-sm">
            <flux:heading level="3" size="base" class="font-semibold">
                {{ $title ?? __('Loading data...') }}
            </flux:heading>

            @if ($description)
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ $description }}
                </flux:text>
            @endif
        </div>
        <span class="sr-only">{{ __('Loading...') }}</span>
    </div>
@endif
