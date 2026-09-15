<article class="rounded-xl border border-border p-4">
    <input type="hidden" :name="`products[${product.id}][product_id]`" :value="product.id">
    <input type="hidden" :name="`products[${product.id}][selected]`" value="1">

    <div class="flex justify-between gap-3">
        <span class="min-w-0">
            <strong class="block truncate" x-text="name(product)"></strong>
            <small dir="ltr" class="text-text-muted" x-text="product.item_code + (product.model_number ? ' · ' + product.model_number : '')"></small>
        </span>
        <button type="button" class="min-h-11 text-red-700" x-on:click="remove(product.id)">
            {{ __('Remove') }}
        </button>
    </div>

    <template x-if="primary(product)">
        <div class="mt-3 grid grid-cols-2 gap-3">
            <label>
                {{ __('Barcode') }}
                <select
                    :name="`products[${product.id}][barcode_id]`"
                    x-model="product.barcode_id"
                    class="mt-1 min-h-11 w-full rounded-lg border border-border bg-surface"
                >
                    <template x-for="barcode in product.barcodes" :key="barcode.id">
                        <option :value="barcode.id" x-text="barcode.barcode"></option>
                    </template>
                </select>
            </label>
            <label>
                {{ __('Label copies') }}
                <input
                    :name="`products[${product.id}][copies]`"
                    x-model="product.copies"
                    type="number"
                    min="1"
                    max="500"
                    step="1"
                    required
                    class="mt-1 min-h-11 w-full rounded-lg border border-border bg-surface px-3"
                >
            </label>
        </div>
    </template>

    <template x-if="!primary(product)">
        @include('pricing.labels.missing-barcode')
    </template>
</article>
