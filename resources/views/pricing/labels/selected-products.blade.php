<section>
    <div class="mb-2 flex justify-between">
        <h2 class="font-semibold">{{ __('Selected products') }}</h2>
        <flux:badge x-text="selected.length"></flux:badge>
    </div>

    <div x-show="!selected.length" class="rounded-xl border border-dashed border-border p-5 text-center text-text-muted">
        {{ __('Search and select one or more products to prepare labels.') }}
    </div>

    @if ($canCreateLabels)
        <form method="POST" action="{{ route('pricing.labels.preview') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="barcode_action" value="manual">
            <input type="hidden" name="return_search" x-model="query">

            <div class="grid gap-3 lg:grid-cols-2">
                <template x-for="product in selected" :key="product.id">
                    @include('pricing.labels.selected-product')
                </template>
            </div>

            @include('pricing.labels.advanced-settings')

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" icon="eye" x-bind:disabled="!selected.length">
                    {{ __('Preview labels') }}
                </flux:button>
            </div>
        </form>
    @endif
</section>
