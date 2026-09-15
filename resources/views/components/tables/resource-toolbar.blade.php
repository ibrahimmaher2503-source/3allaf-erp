@props([
    'filterTarget' => null,
])

<div {{ $attributes->class('table-resource-toolbar') }} data-table-resource-toolbar>
    <div class="table-resource-toolbar__controls">
        @if (filled($filterTarget))
            <flux:button href="#{{ $filterTarget }}" variant="subtle" icon="funnel">
                {{ __('Filters') }}
            </flux:button>
        @endif

        {{ $slot }}
    </div>
</div>
