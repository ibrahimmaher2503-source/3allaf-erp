<x-layouts::app :title="$title">
    <x-state.capability-boundary
        :title="$title"
        :description="$description"
        :boundary="__('Rental assets and reservations will appear here when available.')"
        :cards="$items"
        :empty="__('No rental assets or reservations are available yet.')"
        guide-prefix="party-assets-readiness"
        :card-status-label="__('Review required')"
    />
</x-layouts::app>
