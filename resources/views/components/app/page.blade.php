@props([
    'title' => null,
    'description' => null,
    'eyebrow' => null,
    'badge' => null,
    'badgeColor' => 'zinc',
    'breadcrumbs' => null,
    'requestId' => null,
    'maxWidth' => '7xl',
])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        '4xl' => 'max-w-4xl',
        '5xl' => 'max-w-5xl',
        '6xl' => 'max-w-6xl',
        '7xl' => 'max-w-7xl',
        'full' => 'max-w-full',
        default => 'max-w-7xl',
    };
@endphp

<main {{ $attributes->merge(['class' => 'page-frame w-full mx-auto space-y-4 ' . $maxWidthClass]) }}>
    @if (filled($title))
        <x-page-header
            :title="$title"
            :description="$description"
            :badge="$badge"
            :badge-color="$badgeColor"
            :breadcrumbs="$breadcrumbs ?? $eyebrow"
            :request-id="$requestId"
            data-guide="page-header"
        >
            @if (isset($actions))
                <x-slot:actions>
                    {{ $actions }}
                </x-slot:actions>
            @endif
        </x-page-header>
    @endif

    {{ $slot }}
</main>
