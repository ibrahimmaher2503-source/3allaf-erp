<x-layouts::app :title="$title">
    <x-state.capability-boundary
        :title="$title"
        :description="$description"
        :boundary="__('Party payments and balances will appear here when the workflow is available.')"
        :cards="$items"
        :empty="__('No party payments or balances are available yet.')"
        guide-prefix="party-payments-readiness"
        :card-status-label="__('Review required')"
    />
</x-layouts::app>
