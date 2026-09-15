<x-layouts::app :title="$title">
    <x-state.capability-boundary
        :title="$title"
        :description="$description"
        :boundary="__('Final settlements will appear here when the workflow is available.')"
        :cards="$items"
        :empty="__('No final settlements are available yet.')"
        guide-prefix="party-final-close-readiness"
        :card-status-label="__('Review required')"
    />
</x-layouts::app>
