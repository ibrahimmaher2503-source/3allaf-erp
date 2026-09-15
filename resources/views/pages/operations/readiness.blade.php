<x-layouts::app :title="$title">
    <x-state.capability-boundary
        :title="$title"
        :description="__('Review operational handover, devices, support, and system checks.')"
        :boundary="__('Operational checks will appear here when configured.')"
        :cards="$cards"
        :empty="__('No operational checks are available yet.')"
        guide-prefix="operations-readiness"
        :card-status-label="__('Review required')"
    />
</x-layouts::app>
