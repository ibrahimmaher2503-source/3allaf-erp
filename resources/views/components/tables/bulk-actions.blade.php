@props([
    'pageIds' => [],
    'selectedCount' => 0,
    'selectedIds' => [],
    'pageCount' => 0,
    'maxSelection' => 100,
])

@php
    $pageSelectionComplete = $pageIds !== [] && count(array_diff($pageIds, $selectedIds)) === 0;
@endphp

<div {{ $attributes->merge(['class' => 'table-bulk-actions']) }} role="region" aria-label="{{ __('Bulk operations') }}" data-bulk-actions data-selected-count="{{ $selectedCount }}">
    <div class="table-bulk-actions__summary">
        <div class="flex min-w-0 items-start gap-3">
            <span class="table-bulk-actions__icon" aria-hidden="true">
                <flux:icon name="check-circle" class="size-4" />
            </span>
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold text-text-primary">{{ __('Bulk operations') }}</span>
                    <flux:badge size="sm" color="{{ $selectedCount > 0 ? 'blue' : 'zinc' }}" data-bulk-selected-count aria-live="polite">
                        {{ trans_choice(':count selected', $selectedCount, ['count' => $selectedCount]) }}
                    </flux:badge>
                </div>
                <p class="text-xs leading-5 text-text-muted">
                    @if ($selectedCount > 0)
                        {{ __('Ready to apply to :count selected records.', ['count' => $selectedCount]) }}
                    @else
                        {{ __('Select records to enable bulk actions.') }}
                    @endif
                    <span class="table-bulk-actions__limit">{{ __('Limit: :limit.', ['limit' => $maxSelection]) }}</span>
                </p>
            </div>
        </div>

        <label class="table-bulk-actions__page-toggle">
            <input
                type="checkbox"
                class="size-4 rounded border-border text-primary focus:ring-primary"
                wire:click="toggleBulkPage(@js($pageIds))"
                @checked($pageSelectionComplete)
                aria-label="{{ $pageSelectionComplete ? __('Clear page selection') : __('Select all records on this page') }}"
            />
            <span class="min-w-0">
                <span class="block text-sm font-medium text-text-primary">{{ $pageSelectionComplete ? __('Clear page selection') : __('Select page') }}</span>
                <span class="block text-xs text-text-muted">{{ __(':count visible records', ['count' => $pageCount]) }}</span>
            </span>
        </label>
    </div>

    <div class="table-bulk-actions__commands">
        <div class="flex min-w-0 items-center gap-2 text-xs text-text-muted">
            <span class="hidden sm:inline">{{ __('Selection scope') }}:</span>
            <span class="table-bulk-actions__scope">{{ __('Current page') }}</span>
        </div>

        @if ($selectedCount > 0 && isset($actions))
            <div class="table-bulk-actions__action-list" data-bulk-action-list>
                <span class="sr-only">{{ __('Actions for selected records') }}</span>
                {{ $actions }}
            </div>
        @endif

        @if ($selectedCount > 0)
            <flux:button type="button" size="sm" variant="ghost" icon="x-mark" wire:click="clearBulkSelection">
                {{ __('Clear selection') }}
            </flux:button>
        @endif

        <span wire:loading.flex wire:target="toggleBulkPage,clearBulkSelection,bulkToggleProductStatus,bulkToggleCategoryStatus,bulkToggleBrandStatus,bulkToggleSupplierStatus,bulkToggleBranchStatus,bulkToggleStoreStatus" class="table-bulk-actions__loading" role="status" aria-live="polite">
            <flux:icon name="arrow-path" class="size-3.5 animate-spin" />
            {{ __('Updating selection...') }}
        </span>
    </div>
</div>
