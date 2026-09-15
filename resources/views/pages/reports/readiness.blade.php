@php $isArabic = str_starts_with(app()->getLocale(), 'ar'); @endphp
<x-layouts::app :title="$title">
    <x-state.capability-boundary
        :title="$title"
        :description="__('Review reports, summaries, and operational figures.')"
        :boundary="__('Report records will appear here when available.')"
        :cards="$cards"
        :empty="__('No reports or summaries are available yet.')"
        guide-prefix="reports-readiness"
        :card-status-label="__('Review required')"
    />
</x-layouts::app>
