@props([
    'name' => null,
    'value' => '',
    'label' => __('Product'),
    'placeholder' => __('Scan barcode or search product'),
    'required' => false,
    'valueField' => 'id',
    'display' => '',
    'purchasing' => false,
    'supplierId' => '',
    'supplierOnly' => true,
    'currencyCode' => '',
    'purchaseInvoiceId' => '',
    'disabled' => false,
])

<div
    {{ $attributes->whereDoesntStartWith('wire:model')->merge(['class' => 'relative']) }}
    data-product-lookup
    data-search-url="{{ route('transaction-products.search') }}"
    data-value-field="{{ $valueField }}"
    data-context="{{ $purchasing ? 'purchasing' : 'transaction' }}"
    data-supplier-id="{{ $supplierId }}"
    data-supplier-only="{{ $supplierOnly ? '1' : '0' }}"
    data-currency-code="{{ $currencyCode }}"
    data-purchase-invoice-id="{{ $purchaseInvoiceId }}"
    data-message-empty="{{ __('No matching products.') }}"
    data-message-loading="{{ __('Searching products…') }}"
    data-message-error="{{ __('Product search could not be completed. Try again.') }}"
    data-message-supplier-required="{{ __('Select a supplier before adding products.') }}"
    data-message-supplier-empty="{{ __('This supplier has no associated products. Uncheck “Use supplier products only” to search all authorized products.') }}"
    data-label-unit="{{ __('Unit') }}"
    data-label-supplier-code="{{ __('Supplier product code') }}"
    data-label-last-price="{{ __('Last supplier price') }}"
    data-label-fallback-price="{{ __('Fallback product cost') }}"
    data-label-no-price="{{ __('No saved supplier price or fallback cost') }}"
    data-camera-unsupported="{{ __('Camera barcode scanning is not supported by this browser. Use an external scanner or manual search.') }}"
    data-camera-error="{{ __('The camera could not be opened. Use an external scanner or manual search.') }}"
>
    <label class="mb-1 block text-xs font-semibold" data-product-label>{{ $label }}</label>
    <input
        type="hidden"
        @if($name) name="{{ $name }}" @endif
        value="{{ $value }}"
        data-product-id
        {{ $attributes->whereStartsWith('wire:model') }}
    >
    <div class="flex items-stretch gap-1.5">
        <div class="relative min-w-0 flex-1">
            <input
                type="search"
                role="combobox"
                aria-autocomplete="list"
                aria-expanded="false"
                autocomplete="off"
                dir="{{ str_starts_with(app()->getLocale(), 'ar') ? 'rtl' : 'ltr' }}"
                @if($required) required @endif
                @if($disabled) disabled @endif
                placeholder="{{ $disabled ? __('Select a supplier before adding products.') : $placeholder }}"
                value="{{ $display }}"
                class="block h-10 w-full appearance-none rounded-lg border border-zinc-300 bg-white px-3 {{ str_starts_with(app()->getLocale(), 'ar') ? 'text-right' : 'text-left' }} text-sm [unicode-bidi:plaintext] outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-600/15 disabled:cursor-not-allowed disabled:bg-zinc-100 disabled:text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:disabled:bg-zinc-800"
                data-product-search
                @if(filled($value)) data-selected="true" @endif
            >
        </div>
        @if($purchasing)
            <button type="button" data-camera-trigger class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-zinc-300 bg-white text-zinc-500 hover:border-cyan-600 hover:text-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-600/20 disabled:cursor-not-allowed disabled:bg-zinc-100 disabled:opacity-40 dark:border-zinc-700 dark:bg-zinc-900" aria-label="{{ __('Scan with camera') }}" title="{{ __('Scan with camera') }}" @if($disabled) disabled @endif>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5 8.1 5.7A1.5 1.5 0 0 1 9.3 5.1h5.4a1.5 1.5 0 0 1 1.2.6l1.35 1.8H19.5A1.5 1.5 0 0 1 21 9v8.25a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.25V9a1.5 1.5 0 0 1 1.5-1.5h2.25Z"/><circle cx="12" cy="12.75" r="3.25"/></svg>
            </button>
        @endif
    </div>
    <div class="absolute z-40 mt-1 hidden max-h-64 w-full overflow-y-auto rounded-lg border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900" role="listbox" aria-live="polite" data-product-results></div>
    <p class="mt-1 hidden text-xs font-semibold text-rose-600" role="alert" data-product-error>{{ __('Select a product from the search results.') }}</p>
    @if($purchasing)
        <div class="mt-2 hidden rounded-xl border border-zinc-200 bg-zinc-950 p-3" data-camera-panel>
            <video playsinline muted class="mx-auto max-h-64 w-full rounded-lg" data-camera-video></video>
            <div class="mt-2 flex items-center justify-between gap-3">
                <p class="text-xs text-white" data-camera-status>{{ __('Point the camera at a barcode. Video stays on this device.') }}</p>
                <button type="button" data-camera-close class="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-zinc-900">{{ __('Close camera') }}</button>
            </div>
        </div>
        <p class="mt-1 hidden text-xs font-semibold text-amber-700 dark:text-amber-300" role="status" data-camera-message></p>
    @endif
</div>
