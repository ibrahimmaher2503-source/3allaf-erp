<div class="mt-3 rounded-lg bg-amber-50 p-3 text-amber-900">
    <p class="font-semibold">{{ __('This product cannot be printed until it has an active barcode.') }}</p>
    <input type="hidden" :name="`products[${product.id}][barcode_id]`" value="">
    <input type="hidden" :name="`products[${product.id}][copies]`" :value="product.copies">

    @if ($canManageBarcodes)
        <label class="mt-2 block">
            {{ __('Manual EAN-13 or Code 128 barcode') }}
            <input
                :name="`barcode_values[${product.id}]`"
                dir="ltr"
                class="mt-1 min-h-11 w-full rounded-lg border border-amber-300 px-3"
            >
        </label>
        <div class="mt-2 flex gap-2">
            <flux:button
                type="submit"
                formaction="{{ route('pricing.labels.barcodes.store') }}"
                name="barcode_product_id"
                x-bind:value="product.id"
                variant="primary"
            >
                {{ __('Add barcode') }}
            </flux:button>
            <flux:button
                type="submit"
                formaction="{{ route('pricing.labels.barcodes.store') }}"
                name="barcode_product_id"
                x-bind:value="product.id"
                x-on:click="$el.form.querySelector('[name=barcode_action]').value='generate'"
            >
                {{ __('Generate local Code 128') }}
            </flux:button>
        </div>
    @endif
</div>
