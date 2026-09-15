<x-layouts::app :title="$title">
    <x-state.capability-boundary
        :title="$title"
        :description="$description"
        :boundary="__('Operating orders and consumable movements will appear here when available.')"
        :cards="$items"
        :empty="__('No operating orders or consumable movements are available yet.')"
        guide-prefix="party-operating-readiness"
        :card-status-label="__('Review required')"
    />
</x-layouts::app>
