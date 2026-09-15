<x-layouts::app :title="$title">
    <x-state.capability-boundary
        :title="$title"
        :description="__('Find generated files and review audit history.')"
        :boundary="__('Export and audit records will appear here when available.')"
        :cards="$cards"
        :empty="__('No exports or audit records are available yet.')"
        guide-prefix="export-audit-readiness"
        :card-status-label="__('Review required')"
    />
</x-layouts::app>
