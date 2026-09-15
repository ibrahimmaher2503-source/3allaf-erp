<x-layouts::app :title="$title">
    <x-state.capability-boundary
        :title="$title"
        :description="$description"
        :boundary="__('Asset condition and maintenance events will appear here when available.')"
        :cards="$items"
        :empty="__('No asset condition or maintenance events are available yet.')"
        guide-prefix="party-asset-events-readiness"
        :card-status-label="__('Review required')"
    />
</x-layouts::app>
