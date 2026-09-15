<x-layouts::pos :title="__('Resume suspended sale')" :store="$sale->store" :shift="$preview['shift']">
    @php
        $currency = $sale->currency_code;
        $electronicIndex = $cashMethod ? 1 : 0;
    @endphp

    <div class="mx-auto w-full max-w-5xl space-y-5 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">{{ __('Complete suspended sale') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Review the saved items, take payment, and complete the sale.') }}</flux:text>
            </div>
            <flux:button href="{{ route('pos.suspended') }}" variant="subtle" icon="arrow-left">{{ __('Back to suspended sales') }}</flux:button>
        </div>

        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle">
                <ul class="list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </flux:callout>
        @endif

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="flex flex-wrap items-center justify-between gap-2"><flux:heading size="lg">{{ __('Saved items') }}</flux:heading><flux:badge color="emerald">{{ __('Ready for payment') }}</flux:badge></div>
                    <flux:text class="mt-1 text-sm">{{ __('Active drawer') }}: {{ $preview['shift']->cashDrawer?->code }} · {{ __('Prices and stock are checked again when you complete payment.') }}</flux:text>
                </div>
                <div class="space-y-2 p-3" role="region" aria-label="{{ __('Saved suspended-sale items') }}">
                    @foreach ($preview['lines'] as $index => $line)
                        <article class="rounded-lg border border-zinc-200 bg-zinc-50/70 p-3 dark:border-zinc-800 dark:bg-zinc-950/40">
                            <div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="truncate font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? $line['product']->name_ar : $line['product']->name_en }}</div><div class="mt-1 text-xs text-zinc-500">{{ $line['product']->item_code }}{{ $line['is_open_price'] ? ' · '.__('Open price') : '' }}</div></div><div class="shrink-0 text-end"><div class="text-[11px] font-medium uppercase tracking-wide text-zinc-500">{{ __('Line total') }}</div><div class="mt-0.5 font-bold tabular-nums"><x-money :amount="$preview['totals']['lines'][$index]['net_amount']" :currency="$currency" /></div></div></div>
                            @php($lineDiscount = (string) $preview['totals']['lines'][$index]['discount_amount'])
                            <dl class="mt-3 grid grid-cols-3 gap-2 border-t border-zinc-200 pt-3 text-xs tabular-nums dark:border-zinc-800"><div><dt class="text-zinc-500">{{ __('Qty') }}</dt><dd class="mt-0.5 font-semibold"><x-product-quantity :value="$line['quantity']" /></dd></div><div><dt class="text-zinc-500">{{ __('Each') }}</dt><dd class="mt-0.5 font-semibold"><x-money :amount="$line['unit_price']" :currency="$currency" /></dd></div><div class="text-end"><dt class="text-zinc-500">{{ __('Discount') }}</dt><dd class="mt-0.5 font-semibold {{ bccomp($lineDiscount, '0.00', 2) === 1 ? 'text-emerald-700 dark:text-emerald-300' : 'text-zinc-700 dark:text-zinc-200' }}">@if (bccomp($lineDiscount, '0.00', 2) === 1)<x-money :amount="$lineDiscount" :currency="$currency" />@else<span aria-label="{{ __('No discount') }}">—</span>@endif</dd></div></dl>
                        </article>
                    @endforeach
                </div>
            </section>

            <aside class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Payment') }}</flux:heading>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Subtotal') }}</dt><dd>{{ $preview['totals']['subtotal'] }} {{ $currency }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Discount') }}</dt><dd><x-money :amount="$preview['totals']['discount_total']" :currency="$currency" /></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Tax') }}</dt><dd>{{ $preview['totals']['tax_total'] }} {{ $currency }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-zinc-200 pt-2 font-bold dark:border-zinc-700"><dt>{{ __('Invoice total') }}</dt><dd>{{ $preview['totals']['total'] }} {{ $currency }}</dd></div>
                </dl>

                @if ($cashDenomination === null)
                    <flux:callout class="mt-3" variant="warning" icon="exclamation-triangle">{{ $cashRoundingConfigurationError }}</flux:callout>
                @endif

                <form method="POST" action="{{ route('pos.suspended.finalize', $sale) }}" enctype="multipart/form-data" class="mt-4 grid gap-4">
                    @csrf
                    <input type="hidden" name="resume_token" value="{{ $resumeToken }}">

                    @if ($electronicMethods->isNotEmpty())
                        <fieldset class="grid gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <legend class="px-1 text-sm font-semibold">{{ __('Manual electronic payment') }}</legend>
                            <flux:select name="payments[{{ $electronicIndex }}][method_id]" :label="__('Payment method')">
                                <flux:select.option value="">{{ __('No electronic payment') }}</flux:select.option>
                                @foreach ($electronicMethods as $method)
                                    <flux:select.option value="{{ $method->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $method->name_ar : $method->name_en }}{{ $method->requires_evidence ? ' · '.__('evidence required') : '' }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:input name="payments[{{ $electronicIndex }}][amount]" type="number" min="0.01" step="0.01" :label="__('Electronic amount')" />
                            @can('pos_sales.payment_evidence_upload')
                                <flux:input name="payments[{{ $electronicIndex }}][evidence]" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" :label="__('Protected payment evidence')" />
                            @endcan
                            <flux:input name="payments[{{ $electronicIndex }}][evidence_reference]" :label="__('Terminal reference (optional)')" />
                        </fieldset>
                    @endif

                    @if ($cashMethod)
                        <fieldset class="grid gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <legend class="px-1 text-sm font-semibold">{{ __('Cash residual') }}</legend>
                            <flux:checkbox name="payments[0][method_id]" value="{{ $cashMethod->id }}" :label="__('Use cash for the remaining residual')" :disabled="$cashDenomination === null" />
                            <input type="hidden" name="payments[0][amount]" value="0">
                            <flux:input name="payments[0][tendered]" type="number" min="0" step="0.01" :label="__('Cash tendered')" />
                        </fieldset>
                    @endif

                    <flux:button type="submit" variant="primary" icon="check" class="w-full">{{ __('Complete sale') }}</flux:button>
                </form>
            </aside>
        </div>
    </div>
</x-layouts::pos>
