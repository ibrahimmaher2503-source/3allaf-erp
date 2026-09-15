<x-layouts::app :title="__('Sale :number', ['number' => $sale->document_number ?: $sale->id])">
    <div class="mx-auto w-full max-w-6xl space-y-4 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><flux:heading size="xl">{{ $sale->document_number ?: __('Sale #:id', ['id' => $sale->id]) }}</flux:heading><flux:text class="mt-1">{{ __('Retail sale source, financial snapshots, payments, and stock-linked lines.') }}</flux:text></div>
            <div class="flex flex-wrap gap-2">
                @if ($sale->status === 'approved')
                    @can('pos_sales.print')
                        <flux:button href="{{ route('sales.receipt.thermal', $sale) }}" target="_blank" variant="subtle" icon="printer">{{ __('Thermal receipt') }}</flux:button>
                        <flux:button href="{{ route('sales.print', $sale) }}" target="_blank" variant="subtle" icon="document-text">{{ __('A4 invoice') }}</flux:button>
                    @endcan
                    @can('gift_receipts.issue')
                        <flux:button href="{{ route('gift.receipts.index', ['sale_id' => $sale->id]) }}" variant="subtle" icon="gift">{{ __('Issue Gift Receipt') }}</flux:button>
                    @endcan
                @endif
                <flux:button href="{{ route('sales.index') }}" variant="subtle">{{ __('Back') }}</flux:button>
            </div>
        </div>

        @if (session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"><div class="text-xs text-zinc-500">{{ __('Store') }}</div><div class="mt-1 font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? $sale->store->name_ar : $sale->store->name_en }}</div></div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"><div class="text-xs text-zinc-500">{{ __('Cashier') }}</div><div class="mt-1 font-semibold">{{ $sale->cashier->name }}</div></div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"><div class="text-xs text-zinc-500">{{ __('Status') }}</div><div class="mt-1"><x-status.badge :status="$sale->status" /></div></div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"><div class="text-xs text-zinc-500">{{ __('Approved') }}</div><div class="mt-1 font-semibold">{{ $sale->approved_at?->format('Y-m-d H:i') ?? '—' }}</div></div>
        </div>

        <x-tables.data-panel :title="__('Sale lines')" :description="__('Products, quantities, prices, and discounts recorded on this sale.')">
            <table class="data-table min-w-[980px] text-sm" aria-label="{{ __('Sale lines') }}">
                <thead><tr><th scope="col">{{ __('Product') }}</th><th scope="col" class="text-end">{{ __('Quantity') }}</th><th scope="col" class="text-end">{{ __('Original price') }}</th><th scope="col" class="text-end">{{ __('Selling price') }}</th><th scope="col" class="text-end">{{ __('Gross') }}</th><th scope="col" class="text-end">{{ __('Discount') }}</th><th scope="col" class="text-end">{{ __('Line total') }}</th><th scope="col">{{ __('Pricing context') }}</th></tr></thead>
                <tbody>
                    @foreach ($sale->lines as $line)
                        <tr>
                            <td><div class="font-medium">{{ str_starts_with(app()->getLocale(), 'ar') ? $line->name_ar : $line->name_en }}</div><div class="text-xs text-text-muted">{{ $line->item_code }}</div><x-variant-snapshot :snapshot="$line->variant_snapshot" class="mt-1 block" /></td>
                            <td class="text-end tabular-nums"><x-product-quantity :value="$line->quantity" /></td>
                            <td class="text-end"><x-money :amount="\App\Modules\Retail\Support\DecimalMoney::round((string) ($line->reference_price ?? $line->unit_price))" :currency="$sale->currency_code" /></td>
                            <td class="text-end"><x-money :amount="\App\Modules\Retail\Support\DecimalMoney::round((string) $line->unit_price)" :currency="$sale->currency_code" /></td>
                            <td class="text-end"><x-money :amount="$line->gross_amount" :currency="$sale->currency_code" /></td>
                            <td class="text-end"><x-money :amount="$line->discount_amount" :currency="$sale->currency_code" :muted="true" />@if ($line->discount_type)<div class="text-xs text-text-muted">{{ __(str_replace('_', ' ', ucfirst($line->discount_type))) }}</div>@endif</td>
                            <td class="text-end font-semibold"><x-money :amount="$line->net_amount" :currency="$sale->currency_code" /></td>
                            <td>@if ($line->is_open_price)<x-status.badge status="override" :label="__('Open price')" /><div class="mt-1 max-w-48 text-xs text-text-muted">{{ $line->open_price_reason }}</div>@else<span class="text-text-muted">{{ __('Approved price') }}</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-tables.data-panel>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Financial reconciliation') }}</flux:heading>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Gross subtotal') }}</dt><dd><x-money :amount="$sale->subtotal" :currency="$sale->currency_code" /></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Discount total') }}</dt><dd><x-money :amount="$sale->discount_total" :currency="$sale->currency_code" /></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Post-discount net') }}</dt><dd><x-money :amount="bcsub((string) $sale->subtotal, (string) $sale->discount_total, 2)" :currency="$sale->currency_code" /></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Tax') }}@if ($sale->tax_applicable) · {{ $sale->tax_rate_snapshot }}%@endif</dt><dd><x-money :amount="$sale->tax_total" :currency="$sale->currency_code" /></dd></div>
                    <div class="flex justify-between gap-4 border-t border-zinc-200 pt-2 font-semibold dark:border-zinc-700"><dt>{{ __('Final total') }}</dt><dd><x-money :amount="$sale->total" :currency="$sale->currency_code" /></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Cash rounding') }}</dt><dd><x-money :amount="$sale->cash_rounding_amount" :currency="$sale->currency_code" /></dd></div>
                    <div class="flex justify-between gap-4 font-semibold"><dt>{{ __('Payable / payment total') }}</dt><dd><x-money :amount="$sale->payable_total" :currency="$sale->currency_code" /> / <x-money :amount="$sale->paid_total" :currency="$sale->currency_code" /></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Change') }}</dt><dd><x-money :amount="$sale->change_total" :currency="$sale->currency_code" /></dd></div>
                </dl>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Payments') }}</flux:heading>
                <div class="mt-4 space-y-3">
                    @forelse ($sale->payments as $payment)
                        <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                            <div class="flex justify-between gap-3"><span class="font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? $payment->paymentMethod->name_ar : $payment->paymentMethod->name_en }}</span><span class="font-semibold"><x-money :amount="$payment->amount" :currency="$sale->currency_code" /></span></div>
                            @if ($payment->tendered_amount !== null)<div class="mt-1 text-xs text-zinc-500">{{ __('Tendered') }} {{ $payment->tendered_amount }} · {{ __('Change') }} {{ $payment->change_amount }}</div>@endif
                            @if ($payment->evidenceAttachment && auth()->user()->can('pos_sales.payment_evidence_view'))<a class="mt-2 inline-block text-xs text-blue-600 hover:underline" href="{{ route('payments.evidence.show', $payment->evidenceAttachment) }}" target="_blank">{{ __('View protected payment evidence') }}</a>@endif
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center text-zinc-500 dark:border-zinc-700">{{ __('No payments have been posted.') }}</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-layouts::app>
