@props([
    'title',
    'description' => null,
])

<x-cards.section-card :title="$title" :description="$description" {{ $attributes }}>
    <div class="grid gap-4 sm:grid-cols-2">{{ $slot }}</div>
</x-cards.section-card>
