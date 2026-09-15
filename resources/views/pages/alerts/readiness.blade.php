<x-layouts::app :title="$title">
    <x-state.capability-boundary
        :title="$title"
        :description="__('Review alerts, exceptions, and follow-up actions in one place.')"
        :boundary="__('Alert records will appear here when the related workflow is available.')"
        :cards="$cards"
        :empty="__('No alerts or exceptions are available yet.')"
        guide-prefix="alerts-readiness"
        :card-status-label="__('Review required')"
    />
</x-layouts::app>
