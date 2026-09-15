<x-layouts::app :title="$title">
    <x-state.capability-boundary
        :title="$title"
        :description="$description"
        :boundary="__('Quotations will appear here when the workflow is available.')"
        :cards="$items"
        :empty="__('No quotations are available yet.')"
        guide-prefix="quotations-readiness"
        :card-status-label="__('Review required')"
    />
</x-layouts::app>
