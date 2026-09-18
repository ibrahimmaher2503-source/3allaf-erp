@if (! $fullPage)
    <div>
        <h2 class="text-lg font-bold text-zinc-900 dark:text-white">{{ __('Edit Draft Purchase Order') }}</h2>
        <p class="mt-1 text-xs text-zinc-500">{{ __('Enter supplier, destination store, order dates, and item lines.') }}</p>
    </div>
@endif

<form wire:submit.prevent="saveOrder" class="space-y-4" data-product-line-editor data-autofocus="true">
    @if($editingOrderId)<flux:callout variant="info" icon="pencil-square">{{ __('Draft') }} · {{ __('Next action') }}: {{ __('Submit for Review') }}</flux:callout>@endif
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(14rem,1.45fr)_minmax(13rem,1.3fr)_10.5rem_10.5rem]">
        <flux:select wire:model.live="orderForm.supplier_id" :label="__('Supplier') . ' *'">
            <option value="">{{ __('Select Supplier') }}</option>
            @foreach ($suppliers as $sup)
                <option value="{{ $sup->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $sup->name_ar : ($sup->name_en ?: $sup->name_ar) }} ({{ $sup->code }})</option>
            @endforeach
        </flux:select>

        <flux:select wire:model="orderForm.store_id" :label="__('Receiving Store / Warehouse') . ' *'">
            <option value="">{{ __('Select Store') }}</option>
            @foreach ($stores as $st)
                <option value="{{ $st->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $st->name_ar : $st->name_en }} ({{ $st->code }})</option>
            @endforeach
        </flux:select>

        <flux:input type="date" wire:model="orderForm.order_date" :label="__('Order Date') . ' *'" />
        <flux:input type="date" wire:model="orderForm.expected_delivery_date" :label="__('Expected Delivery Date')" />
    </div>

    <div class="space-y-2 border-t border-border pt-3">
        <flux:callout variant="info">{{ __('Choose a purchase unit, enter its quantity and price. Stock is received in the base unit only after invoice approval.') }}</flux:callout>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-sm font-bold text-zinc-900 dark:text-white">{{ __('Order Line Items') }}</h3>
            <div class="flex flex-wrap items-center gap-3 sm:justify-end">
                <flux:checkbox wire:model.live="supplierProductsOnly" :label="__('Use supplier products only')" :disabled="blank($orderForm['supplier_id'])" />
                <flux:button type="button" size="xs" variant="subtle" icon="plus" wire:click="addLine" data-add-line :disabled="blank($orderForm['supplier_id'])">{{ __('Add item') }}</flux:button>
            </div>
        </div>

        <div class="max-h-[32rem] overflow-y-auto rounded-lg border border-border" data-order-lines-scroll>
            <div class="sticky top-0 z-20 hidden grid-cols-[minmax(0,1fr)_7rem_5rem_7rem_7rem_2.5rem] gap-2 border-b border-border bg-zinc-100 px-2 py-1.5 text-[11px] font-semibold text-zinc-600 xl:grid dark:bg-zinc-800 dark:text-zinc-300" aria-hidden="true">
                <span>{{ __('Product') }}</span>
                <span>{{ __('Purchase unit') }}</span>
                <span>{{ __('Quantity') }}</span>
                <span>{{ __('Price per selected unit') }}</span>
                <span>{{ __('Line total') }}</span>
                <span></span>
            </div>
            <div class="space-y-1.5 p-1.5">
                @foreach ($lineItems as $index => $item)
                <div class="grid grid-cols-12 items-start gap-2 rounded-md bg-zinc-50 p-2 xl:grid-cols-[minmax(0,1fr)_7rem_5rem_7rem_7rem_2.5rem] dark:bg-zinc-800/40" data-product-line wire:key="order-line-{{ $index }}">
                    <div class="col-span-12 min-w-0 xl:col-auto">
                        @php
                            $selectedProduct = $products->firstWhere('id', (int) ($item['product_id'] ?? 0));
                        @endphp
                        <x-product-line-lookup wire:model.live="lineItems.{{ $index }}.product_id" :value="$item['product_id'] ?? ''" :display="$selectedProduct ? ((str_starts_with(app()->getLocale(), 'ar') ? $selectedProduct->name_ar : ($selectedProduct->name_en ?: $selectedProduct->name_ar)).' · '.$selectedProduct->item_code) : ''" :required="true" :purchasing="true" :supplier-id="$orderForm['supplier_id']" :supplier-only="$supplierProductsOnly" :disabled="blank($orderForm['supplier_id'])" x-on:product-selected="$wire.selectOrderProduct({{ $index }}, $event.detail.id)" />
                        @if($selectedProduct)<p class="mt-0.5 truncate text-[10px] text-zinc-500">{{ __('Internal code') }}: {{ $selectedProduct->item_code }} · {{ __('Supplier code') }}: {{ $selectedProduct->productSuppliers->first()?->supplier_item_code ?: '—' }} · {{ __('Model') }}: {{ $selectedProduct->model_number ?: '—' }}</p>@endif
                    </div>

                    <div class="col-span-7 sm:col-span-3 xl:col-auto">
                        <flux:select wire:model.live="lineItems.{{ $index }}.product_unit_id" wire:change="changeOrderUnit({{ $index }})" :label="__('Purchase unit')" :disabled="! $selectedProduct">
                            <option value="">{{ __('Base unit') }}</option>
                            @foreach($selectedProduct?->productUnits ?? [] as $purchaseUnit)
                                @if($purchaseUnit->is_purchase_unit && $purchaseUnit->unit?->status === 'active')
                                    <option value="{{ $purchaseUnit->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $purchaseUnit->unit->name_ar : $purchaseUnit->unit->name_en }} · {{ $purchaseUnit->unit->code }}</option>
                                @endif
                            @endforeach
                        </flux:select>
                        <flux:error name="lineItems.{{ $index }}.product_unit_id" />
                    </div>
                    <div class="col-span-5 sm:col-span-3 xl:col-auto">
                        <label for="order-quantity-{{ $index }}" class="text-xs font-semibold">{{ __('Quantity') }}</label>
                        <input id="order-quantity-{{ $index }}" data-line-quantity type="number" step="0.000001" min="0.000001" wire:model.live="lineItems.{{ $index }}.quantity_ordered" class="mt-1 h-10 w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900" required />
                        <flux:error name="lineItems.{{ $index }}.quantity_ordered" />
                    </div>

                    <div class="col-span-7 min-w-0 sm:col-span-3 xl:col-auto">
                        <label for="order-price-{{ $index }}" class="text-xs font-semibold">{{ __('Price per selected unit') }}</label>
                        <input id="order-price-{{ $index }}" data-line-price data-price-kind="cost" type="number" step="0.0001" min="0" wire:model.live="lineItems.{{ $index }}.unit_cost" wire:change="$set('lineItems.{{ $index }}.price_source','manual_authorized_cost')" class="mt-1 h-10 w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900" required />
                        <flux:error name="lineItems.{{ $index }}.unit_cost" />
                        <p class="mt-0.5 truncate text-[9px] leading-none text-zinc-500" data-price-source>{{ match($item['price_source'] ?? 'none') { 'last_supplier_price' => __('Last supplier price'), 'fallback_cost' => __('Fallback product cost'), 'saved_draft_cost' => __('Saved draft cost'), 'manual_authorized_cost' => __('Manually authorized cost'), default => __('No saved supplier price or fallback cost') } }}@if(filled($item['price_date'] ?? '')) · {{ $item['price_date'] }}@endif @if(filled($item['price_currency'] ?? '')) · {{ $item['price_currency'] }}@endif</p>
                    </div>

                    <div class="col-span-3 sm:col-span-2 xl:col-auto">
                        <span class="block text-xs font-semibold">{{ __('Line total') }}</span>
                        <output class="mt-1 flex h-10 items-center justify-end rounded-lg bg-white px-3 font-mono text-sm dark:bg-zinc-900" data-line-total>{{ number_format((float) ($item['quantity_ordered'] ?? 0) * (float) ($item['unit_cost'] ?? 0), 2) }}</output>
                    </div>

                    <div class="col-span-2 pt-1 text-end xl:col-auto">
                        @if (count($lineItems) > 1)
                            <flux:button type="button" size="xs" variant="ghost" icon="trash" class="text-rose-600" wire:click="removeLine({{ $index }})" />
                        @endif
                    </div>
                    @if($selectedProduct)
                        @php
                            $factor = (string) ($item['conversion_factor'] ?? '1');
                            $quantity = (string) ($item['quantity_ordered'] ?? '0');
                            $validPreview = preg_match('/^\d+(?:\.\d{1,6})?$/D', $quantity) === 1 && preg_match('/^\d+(?:\.\d{1,6})?$/D', $factor) === 1 && bccomp($factor, '0', 6) > 0;
                        @endphp
                        @if($validPreview)
                            <p class="col-span-12 rounded-md bg-primary-soft p-2 text-xs text-text-primary xl:col-span-6" aria-live="polite">
                                {{ __('Conversion factor') }}: {{ \App\Support\ProductQuantity::format($factor) }} · {{ __('Base quantity') }}: {{ \App\Support\ProductQuantity::format(bcmul($quantity, $factor, 6)) }} {{ $selectedProduct->baseProductUnit?->unit?->code }}
                                @if(is_numeric($item['unit_cost'] ?? null)) · {{ __('Cost per base unit') }}: {{ number_format((float) $item['unit_cost'] / (float) $factor, 4) }} @endif
                            </p>
                        @endif
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <div class="rounded-lg bg-zinc-100 px-4 py-2 text-end dark:bg-zinc-800">
                <span class="text-xs text-zinc-500">{{ __('Estimated Subtotal') }}: </span>
                <span class="font-mono font-bold text-zinc-900 dark:text-white">{{ number_format($formSubtotal, 2) }}</span>
                <span class="mt-0.5 block text-xs text-zinc-400">{{ __('Tax is not configured') }}</span>
            </div>
        </div>
    </div>

    <div>
        <flux:input wire:model="orderForm.notes" :label="__('Order Notes')" :placeholder="__('Internal procurement reference notes...')" />
    </div>

    <div class="flex flex-wrap justify-end gap-2 border-t border-border pt-3">
        @if ($fullPage)
            <flux:button variant="ghost" href="{{ route('purchasing.orders') }}" wire:navigate>{{ __('Cancel') }}</flux:button>
        @else
            <flux:button type="button" variant="ghost" wire:click="$set('showFormModal', false)">{{ __('Cancel') }}</flux:button>
        @endif
        <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="saveOrder"><span wire:loading.remove wire:target="saveOrder">{{ __('Save Draft') }}</span><span wire:loading wire:target="saveOrder">{{ __('Saving…') }}</span></flux:button>
    </div>
</form>
