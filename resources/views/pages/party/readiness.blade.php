<x-layouts::app :title="$title">
    <x-state.capability-boundary
        :title="$title"
        :description="$description"
        :boundary="__('Party bookings and invoices will appear here when the workflow is available.')"
        :cards="$items"
        :empty="__('No party bookings or working invoices are available yet.')"
        guide-prefix="party-readiness"
        :card-status-label="__('Review required')"
    >
        <x-slot:actions>
            <flux:button href="{{ route('initial-setup') }}" variant="subtle" icon="arrow-left" wire:navigate>{{ __('Back to setup') }}</flux:button>
        </x-slot:actions>
    </x-state.capability-boundary>
</x-layouts::app>
