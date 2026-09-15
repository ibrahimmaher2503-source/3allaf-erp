<x-layouts::app :title="$title">
    <x-state.capability-boundary
        :title="$title"
        :description="__('Prepare approved master-data files and review import results.')"
        :boundary="__('Import records will appear here when the workflow is available.')"
        :cards="$cards"
        :empty="__('No import batches or reconciliation records are available yet.')"
        guide-prefix="master-data-migration-readiness"
        :card-status-label="__('Review required')"
    />
</x-layouts::app>
