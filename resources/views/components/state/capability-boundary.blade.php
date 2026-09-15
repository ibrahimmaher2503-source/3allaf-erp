@props([
    'title',
    'description' => null,
    'boundary' => null,
    'cards' => [],
    'empty' => null,
    'emptyTitle' => null,
    'guidePrefix' => 'capability-review',
    'statusLabel' => null,
    'cardStatusLabel' => null,
])

@php
    $displayDescription = $description ?: __('Use this area when the related records are available.');
@endphp

<div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6" data-guide="{{ $guidePrefix }}-header">
    <header class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0 max-w-3xl">
            <h1 class="text-2xl font-bold tracking-tight text-text-primary sm:text-3xl">{{ $title }}</h1>
            <p class="mt-2 text-sm leading-6 text-text-muted">{{ $displayDescription }}</p>
        </div>
        @if (isset($actions))
            <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
        @endif
    </header>

    <section class="max-w-2xl" data-guide="{{ $guidePrefix }}-empty" aria-labelledby="{{ $guidePrefix }}-empty-title">
        <x-state.empty
            :title="$emptyTitle ?: __('No records are available yet.')"
            :description="$empty ?: __('There is nothing to show yet.')"
            icon="document-text"
        />
    </section>
</div>
