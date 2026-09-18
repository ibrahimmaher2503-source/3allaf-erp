<x-layouts::app :title="__('Returns & Exchanges')">
    <x-app.page :title="__('Returns & Exchanges')" :description="__('Create source-linked return documents; the original sale is never edited.')" max-width="7xl">
        @if(session('success'))
            <flux:callout variant="success">{{ session('success') }}</flux:callout>
        @endif
        @if(isset($errors) && $errors->any())
            <flux:callout variant="danger">{{ $errors->first() }}</flux:callout>
        @endif

        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('Choose one source only: the completed sale or its active Gift Receipt. A return is not complete until inspection, approval, settlement, and stock disposition are recorded.') }}
        </flux:callout>

        @can('returns.create')
            <flux:card class="mt-6 space-y-4 p-4 sm:p-6">
                <flux:heading size="lg">{{ __('New return or exchange') }}</flux:heading>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('For an exchange, add every replacement line below. Any difference requires an active payment method when the approved return is completed.') }}</p>

                <form method="POST" action="{{ route('returns.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" x-data="{ settlement: @js(old('settlement_type', 'cash_refund')) }" data-product-line-editor data-next-index="1">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                    <fieldset class="sm:col-span-2 lg:col-span-4 grid min-w-0 gap-4 rounded-xl border border-border p-4 sm:grid-cols-2">
                        <legend class="px-2 font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? '١. مصدر المرتجع والصنف' : '1. Source document & item' }}</legend>
                        <p class="text-sm text-text-muted sm:col-span-2">{{ str_starts_with(app()->getLocale(), 'ar') ? 'اختر فاتورة البيع أو إيصال بدون أسعار، وليس الاثنين معًا، ثم حدد الصنف من نفس المستند.' : 'Choose either the sales invoice or a receipt without prices, then select an item from that document.' }}</p>

                    <flux:select name="source_sale_id" label="{{ __('Source completed sale') }}">
                        <option value="">{{ __('Choose sale') }}</option>
                        @foreach($sales as $sale)
                            <option value="{{ $sale->id }}">{{ $sale->document_number ?: '#'.$sale->id }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select name="source_gift_receipt_id" label="{{ __('Or active Gift Receipt') }}">
                        <option value="">{{ __('Choose Gift Receipt') }}</option>
                        @foreach($receipts as $receipt)
                            <option value="{{ $receipt->id }}">{{ $receipt->reference }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select name="sale_line_id" label="{{ __('Source line') }}" required>
                        <option value="">{{ __('Choose item from the selected source') }}</option>
                        @foreach($sales as $sale)
                            @foreach($sale->lines as $line)
                                <option value="{{ $line->id }}">{{ __('Sale') }} {{ $sale->document_number ?: '#'.$sale->id }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $line->name_ar : $line->name_en }} · {{ $line->quantity }}</option>
                            @endforeach
                        @endforeach
                        @foreach($receipts as $receipt)
                            @foreach($receipt->lines as $line)
                                <option value="{{ $line->sale_line_id }}">{{ __('Gift Receipt') }} {{ $receipt->reference }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $line->name_ar : $line->name_en }} · {{ $line->quantity }}</option>
                            @endforeach
                        @endforeach
                    </flux:select>
                    <flux:input name="quantity" type="number" min="1" step="1" value="{{ old('quantity', 1) }}" label="{{ __('Quantity') }}" required />
                    </fieldset>

                    <fieldset class="sm:col-span-2 lg:col-span-4 grid min-w-0 gap-4 rounded-xl border border-border p-4 sm:grid-cols-2">
                        <legend class="px-2 font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? '٢. الفحص وطريقة التسوية' : '2. Inspection & settlement' }}</legend>

                    <flux:select name="settlement_type" label="{{ __('Settlement') }}" required x-model="settlement">
                        <option value="cash_refund">{{ __('Cash refund record') }}</option>
                        <option value="original_tender">{{ __('Original tender reversal record') }}</option>
                        <option value="gift_card">{{ __('Gift Card') }}</option>
                        <option value="exchange">{{ __('Exchange') }}</option>
                    </flux:select>
                    <flux:select name="condition" label="{{ __('Inspected condition') }}" required>
                        <option value="sellable">{{ __('Sellable') }}</option>
                        <option value="non_sellable">{{ __('Non-sellable') }}</option>
                        <option value="damaged">{{ __('Damaged') }}</option>
                        <option value="manager_review">{{ __('Manager review') }}</option>
                    </flux:select>
                    <flux:select name="disposition" label="{{ __('Stock disposition') }}" required>
                        <option value="restock">{{ __('Restock sellable') }}</option>
                        <option value="quarantine">{{ __('Quarantine to the damaged store') }}</option>
                    </flux:select>
                    <flux:textarea name="inspection_notes" label="{{ __('Inspection notes / evidence reference') }}" />
                    </fieldset>

                    <fieldset x-show="settlement === 'exchange'" x-cloak class="sm:col-span-2 lg:col-span-4 rounded-lg border border-teal-200 p-4 dark:border-teal-900">
                        <legend class="px-1 text-sm font-semibold">{{ __('Replacement lines') }}</legend>
                        <p class="mb-3 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Replacement stock leaves the selling store only when this return is completed.') }}</p>
                        <div class="grid gap-3 sm:grid-cols-12" data-product-line>
                            <div class="sm:col-span-5"><x-product-line-lookup name="exchange_lines[0][product_id]" :label="__('Product')" /></div>
                            <div class="sm:col-span-2"><label class="text-xs font-semibold">{{ __('Quantity') }}</label><input name="exchange_lines[0][quantity]" data-line-quantity type="number" min="1" step="1" value="1" class="mt-1 h-10 w-full rounded-lg border-zinc-300"></div>
                            <div class="sm:col-span-2"><span class="block text-xs font-semibold">{{ __('Unit price') }}</span><span class="mt-1 flex h-10 items-center rounded-lg bg-zinc-50 px-3 text-xs">{{ __('Calculated at completion') }}</span></div>
                            <div class="sm:col-span-2"><span class="block text-xs font-semibold">{{ __('Line total') }}</span><span class="mt-1 flex h-10 items-center rounded-lg bg-zinc-50 px-3">—</span></div>
                        </div>
                        <template data-line-template><div class="mt-3 grid gap-3 sm:grid-cols-12" data-product-line><div class="sm:col-span-5"><x-product-line-lookup name="exchange_lines[__INDEX__][product_id]" :label="__('Product')" /></div><div class="sm:col-span-2"><label class="text-xs font-semibold">{{ __('Quantity') }}</label><input name="exchange_lines[__INDEX__][quantity]" data-line-quantity type="number" min="1" step="1" value="1" class="mt-1 h-10 w-full rounded-lg border-zinc-300"></div><div class="sm:col-span-2"><span class="block text-xs font-semibold">{{ __('Unit price') }}</span><span class="mt-1 flex h-10 items-center rounded-lg bg-zinc-50 px-3 text-xs">{{ __('Calculated at completion') }}</span></div><div class="sm:col-span-2"><span class="block text-xs font-semibold">{{ __('Line total') }}</span><span class="mt-1 flex h-10 items-center rounded-lg bg-zinc-50 px-3">—</span></div><button type="button" data-remove-line class="self-end text-sm text-red-700 underline">{{ __('Remove') }}</button></div></template>
                        <button type="button" data-add-line class="mt-3 text-sm text-teal-700 underline">{{ __('Add item') }}</button>
                    </fieldset>

                    <flux:textarea name="reason" label="{{ __('Reason') }}" required class="sm:col-span-2 lg:col-span-4" />
                    <div class="sm:col-span-2 lg:col-span-4 flex justify-end">
                        <div class="flex flex-wrap items-center justify-end gap-3"><p class="text-sm text-text-muted">{{ str_starts_with(app()->getLocale(), 'ar') ? 'حفظ المسودة لا يرد المبلغ ولا يغيّر المخزون؛ يلزم استكمال الفحص والاعتماد والتسوية.' : 'Saving a draft does not refund money or change stock; inspection, approval and settlement must be completed.' }}</p><flux:button type="submit" variant="primary">{{ __('Create draft') }}</flux:button></div>
                    </div>
                </form>
            </flux:card>
        @endcan

        <flux:card class="mt-6 overflow-hidden p-0">
            <header class="border-b border-border p-4"><flux:heading size="lg">{{ str_starts_with(app()->getLocale(), 'ar') ? 'سجل مرتجعات البيع' : 'Sales return register' }}</flux:heading><flux:text>{{ str_starts_with(app()->getLocale(), 'ar') ? 'افتح المرتجع لمراجعة الأصناف والفحص ومرحلة الاعتماد والتسوية.' : 'Open a return to review its items, inspection, approval and settlement stage.' }}</flux:text></header>
            <div class="overflow-x-auto">
                <table class="data-table min-w-[960px] w-full text-sm">
                    <thead><tr><th>{{ __('Return') }}</th><th>{{ __('Source') }}</th><th>{{ __('Lines') }}</th><th>{{ __('Settlement') }}</th><th>{{ __('Value') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead>
                    <tbody>
                        @forelse($returns as $return)
                            <tr class="border-t border-zinc-100 align-top dark:border-zinc-800">
                                <td class="font-mono font-semibold">{{ $return->return_number ?: '#'.$return->id }}</td>
                                <td>{{ $return->sourceSale?->document_number ?: ($return->sourceGiftReceipt?->reference ?: __('Source unavailable')) }}</td>
                                <td>{{ $return->lines->count() }}</td><td>{{ __(match ($return->settlement_type) { 'cash_refund' => 'Cash refund record', 'original_tender' => 'Original tender reversal record', 'gift_card' => 'Gift Card', 'exchange' => 'Exchange', default => 'Settlement' }) }}</td>
                                <td>{{ number_format((float) $return->settlement_value, 2) }} {{ $return->currency_code }}</td><td><x-status.badge :status="$return->status" /></td>
                                <td><x-actions.button semantic="view" :label="__('Open')" :href="route('returns.show', $return)">{{ __('Open') }}</x-actions.button> <x-actions.button semantic="print" :label="__('Print')" :href="route('returns.print', $return)">{{ __('Print') }}</x-actions.button></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-state.empty :title="__('No returns yet')" :description="__('Start from a completed sale or a valid Gift Receipt.')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $returns->links() }}</div>
        </flux:card>
    </x-app.page>
</x-layouts::app>
