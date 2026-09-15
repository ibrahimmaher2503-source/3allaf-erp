<div>
    <label for="product-search" class="mb-1 block text-sm font-semibold">{{ __('Product search') }}</label>
    <div class="flex gap-2">
        <input
            id="product-search"
            x-model="query"
            x-on:input.debounce.350ms="search()"
            x-on:keydown.enter.prevent="search(true)"
            type="search"
            class="min-h-11 w-full rounded-lg border border-border bg-surface px-3"
            placeholder="{{ __('Arabic/English name, barcode, item code, or model number') }}"
        >
        <flux:button type="button" x-on:click="search()" icon="magnifying-glass">{{ __('Search') }}</flux:button>
    </div>
    <p class="mt-1 text-xs text-text-muted">
        {{ __('Scan a barcode and press Enter to select its exact product immediately.') }}
    </p>
</div>

<div x-show="loading" class="rounded-lg bg-surface-muted p-3">{{ __('Searching…') }}</div>
<div x-show="error" x-text="error" class="rounded-lg bg-red-50 p-3 text-red-800"></div>

<section x-show="results.length">
    <h2 class="mb-2 font-semibold">{{ __('Search results') }}</h2>
    <div class="divide-y divide-border rounded-xl border border-border">
        <template x-for="product in results" :key="product.id">
            <button
                type="button"
                class="flex min-h-14 w-full items-center justify-between gap-3 p-3 text-start hover:bg-surface-muted disabled:opacity-50"
                x-on:click="select(product)"
                :disabled="has(product.id)"
            >
                <span class="min-w-0">
                    <strong class="block truncate" x-text="name(product)"></strong>
                    <small class="text-text-muted" dir="ltr" x-text="product.item_code + (product.model_number ? ' · ' + product.model_number : '')"></small>
                </span>
                <span class="text-end">
                    <small class="block font-mono" dir="ltr" x-text="primary(product)?.barcode || @js(__('No active barcode'))"></small>
                    <span class="text-xs text-primary" x-text="has(product.id) ? @js(__('Selected')) : @js(__('Select'))"></span>
                </span>
            </button>
        </template>
    </div>
    <flux:button x-show="nextPage" type="button" class="mt-3" variant="subtle" x-on:click="more()">
        {{ __('Load more') }}
    </flux:button>
</section>
