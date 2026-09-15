<x-layouts::app :title="$title">
    <x-state.capability-boundary
        :title="$title"
        :description="__('Review release controls, handover, and operational approvals.')"
        :boundary="__('Release records will appear here when available.')"
        :cards="$cards"
        :empty="__('No release records are available yet.')"
        guide-prefix="release-readiness"
        :card-status-label="__('Review required')"
    />
</x-layouts::app>
