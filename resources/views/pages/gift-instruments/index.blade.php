<x-layouts::app :title="__('Gift Receipts')">
    <x-app.page :title="__('Gift Receipts')" :description="__('Issue and reprint a privacy-safe receipt linked to a completed sale. Prices never leave the server-side return calculation.')" max-width="7xl">
        @if(session('success'))<flux:callout variant="success">{{ session('success') }}</flux:callout>@endif
        @if(session('errors')?->any())<flux:callout variant="danger">{{ session('errors')->first() }}</flux:callout>@endif
        <flux:callout variant="info" icon="shield-check">{{ __('Gift Receipts show item identity and quantity only. They never include price, discount, tax, total, or hidden price metadata.') }}</flux:callout>

        @can('gift_receipts.issue')
            <flux:card class="mt-6">
                <flux:heading size="lg">{{ __('Issue from completed sale') }}</flux:heading>
                <form method="GET" action="{{ route('gift.receipts.index') }}" class="mt-4 flex flex-wrap items-end gap-3">
                    <div class="min-w-[18rem] flex-1">
                        <label for="gift-receipt-sale" class="block text-sm font-medium">{{ __('Completed sale') }}</label>
                        <select id="gift-receipt-sale" name="sale_id" class="mt-1 block w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900" required>
                            <option value="">{{ __('Choose a sale') }}</option>
                            @foreach($sales as $sale)
                                <option value="{{ $sale->id }}" @selected($selectedSaleId === $sale->id)>{{ $sale->document_number ?: '#'.$sale->id }} &middot; {{ $sale->created_at?->format('Y-m-d H:i') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <flux:button type="submit" variant="subtle">{{ __('Load eligible lines') }}</flux:button>
                </form>

                @if ($selectedSale)
                    <form method="POST" action="{{ route('gift.receipts.store') }}" class="mt-5 grid gap-4">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <input type="hidden" name="sale_id" value="{{ $selectedSale->id }}">
                        <fieldset class="grid gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <legend class="px-1 text-sm font-semibold">{{ __('Select eligible lines') }}</legend>
                            @foreach ($selectedSale->lines as $line)
                                <label class="flex items-start gap-3 rounded-md border border-zinc-100 p-3 text-sm dark:border-zinc-800">
                                    <input type="checkbox" name="sale_line_ids[]" value="{{ $line->id }}" class="mt-1 rounded border-zinc-300 text-teal-600" checked>
                                    <span class="min-w-0"><span class="block font-medium">{{ str_starts_with(app()->getLocale(), 'ar') ? $line->name_ar : $line->name_en }}</span><span class="block text-xs text-zinc-500">{{ $line->item_code }} &middot; {{ __('Qty') }} {{ $line->quantity }}</span><x-variant-snapshot :snapshot="$line->variant_snapshot" class="mt-1 block" /></span>
                                </label>
                            @endforeach
                        </fieldset>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Only checked lines are copied. Prices and payment details remain suppressed.') }}</p>
                            <flux:button type="submit" variant="primary">{{ __('Issue price-free Gift Receipt') }}</flux:button>
                        </div>
                    </form>
                @else
                    <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Choose a completed sale to review and select its eligible lines.') }}</p>
                @endif
            </flux:card>
        @endcan

        <flux:card class="mt-6 overflow-hidden p-0">
            <div class="overflow-x-auto"><table class="data-table min-w-[760px] w-full text-sm"><thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Sale') }}</th><th>{{ __('Items') }}</th><th>{{ __('Status') }}</th><th>{{ __('Issued') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
                @forelse($receipts as $receipt)
                    <tr class="border-t border-zinc-100 align-top dark:border-zinc-800">
                        <td class="font-mono font-semibold">{{ $receipt->reference }}</td>
                        <td>{{ $receipt->sale?->document_number ?: '#'.$receipt->sale_id }}</td>
                        <td>{{ $receipt->lines->count() }}</td>
                        <td><x-status.badge :status="$receipt->status" /></td>
                        <td>{{ $receipt->created_at?->format('Y-m-d H:i') }}</td>
                        <td>
                            @if($receipt->print_events_count === 0 && auth()->user()->can('gift_receipts.print'))
                                <x-actions.button semantic="print" :label="__('Print')" :href="route('gift.receipts.print', $receipt)">{{ __('Print') }}</x-actions.button>
                            @elseif($receipt->print_events_count > 0 && auth()->user()->can('gift_receipts.reprint'))
                                <x-actions.button semantic="print" :label="__('Reprint')" :href="route('gift.receipts.print', [$receipt, 'reprint' => 1, 'reason' => __('Customer reprint')])">{{ __('Reprint') }}</x-actions.button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-state.empty :title="__('No Gift Receipts yet')" :description="__('Issue one from an approved completed sale. The printable output is intentionally price-free.')" /></td></tr>
                @endforelse
            </tbody></table></div><div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $receipts->links() }}</div>
        </flux:card>
    </x-app.page>
</x-layouts::app>
