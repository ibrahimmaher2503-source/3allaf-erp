@php
    $preference = auth()->user()?->uiPreference;
    $showByDefault = $preference?->showSearchFiltersByDefault() ?? false;
@endphp

<div {{ $attributes->class('table-filter-bar min-w-0 rounded-lg bg-surface-muted/35 p-3') }} data-table-filter-bar x-data="{ filtersOpen: @js($showByDefault) }" x-id="['search-filter-panel']">
    <div class="flex min-w-0 flex-wrap items-center justify-between gap-2">
        <button type="button" class="table-filter-bar__toggle" x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen.toString()" x-bind:aria-controls="$id('search-filter-panel')">
            <flux:icon.adjustments-horizontal class="size-4" />
            <span x-text="filtersOpen ? @js(__('Hide search and filters')) : @js(__('Filter / Search'))"></span>
            <flux:icon.chevron-down class="size-4 transition" x-bind:class="filtersOpen && 'rotate-180'" />
        </button>
        @if (isset($actions))
            <div class="flex w-full shrink-0 flex-wrap items-center gap-2 sm:w-auto sm:justify-end">{{ $actions }}</div>
        @endif
    </div>
    <div x-bind:id="$id('search-filter-panel')" x-show="filtersOpen" class="mt-3 min-w-0" data-search-filter-panel>
        {{ $slot }}
    </div>
</div>
