@props([
    'rows' => [],
    'emptyMessage' => 'Add at least one line to continue.',
    'addLabel' => 'Add line',
])

<div {{ $attributes->merge(['class' => 'space-y-3']) }} data-line-editor>
    <div class="space-y-3" role="list" aria-label="Line items">
        @forelse ($rows as $index => $row)
            <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" role="listitem" wire:key="line-{{ $row['id'] ?? $index }}">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                    {{ __('Line :number', ['number' => $index + 1]) }}
                </div>
                {{ $slot }}
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-500 dark:border-zinc-600 dark:text-zinc-400" role="status">
                {{ $emptyMessage }}
            </div>
        @endforelse
    </div>

    @if ($addLabel !== null)
        <button type="button" {{ $attributes->only(['wire:click', 'x-on:click']) }} class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800">
            {{ $addLabel }}
        </button>
    @endif
</div>
