<x-layouts::app :title="$title">
    <x-state.capability-boundary
        :title="$title"
        :description="__('Review validation scenarios, evidence, and approvals.')"
        :boundary="__('Validation records will appear here when available.')"
        :cards="$cards"
        :empty="__('No validation records are available yet.')"
        guide-prefix="uat-readiness"
        :card-status-label="__('Review required')"
    />
</x-layouts::app>
