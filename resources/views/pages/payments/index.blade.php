<x-layouts::app :title="__('Payments')">
    <div class="mx-auto w-full max-w-7xl space-y-4 p-4 sm:p-6">
        <div><flux:heading size="xl">{{ __('Payments') }}</flux:heading><flux:text class="mt-1">{{ __('Immutable payment records linked to approved scoped sales.') }}</flux:text></div>
        <form method="GET" class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-zinc-800 dark:bg-zinc-900">
            <flux:input name="q" value="{{ request('q') }}" :label="__('Invoice')" />
            <flux:select name="method_id" :label="__('Method')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach ($methods as $method)<flux:select.option value="{{ $method->id }}" :selected="(string) request('method_id') === (string) $method->id">{{ str_starts_with(app()->getLocale(), 'ar') ? $method->name_ar : $method->name_en }}</flux:select.option>@endforeach</flux:select>
            <flux:input name="date_from" type="date" value="{{ request('date_from') }}" :label="__('From')" />
            <div class="flex items-end gap-2"><flux:input name="date_to" type="date" value="{{ request('date_to') }}" :label="__('To')" /><flux:button type="submit">{{ __('Filter') }}</flux:button></div>
        </form>
        <x-tables.table-shell :label="__('Payments')">
            <table class="data-table w-full min-w-[920px] text-sm"><thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Method') }}</th><th>{{ __('Store') }}</th><th>{{ __('Recorded by') }}</th><th class="text-end">{{ __('Applied') }}</th><th class="text-end">{{ __('Tendered') }}</th><th class="text-end">{{ __('Change') }}</th><th>{{ __('Evidence') }}</th></tr></thead><tbody>
                @forelse ($payments as $payment)
                    <tr><td><a class="font-semibold text-primary hover:underline" href="{{ route('sales.show', $payment->sale) }}">{{ $payment->sale->document_number }}</a><div class="text-xs text-text-muted">{{ $payment->created_at?->format('Y-m-d H:i') }}</div></td><td>{{ str_starts_with(app()->getLocale(), 'ar') ? $payment->paymentMethod->name_ar : $payment->paymentMethod->name_en }}</td><td>{{ $payment->sale->store->code }}</td><td>{{ $payment->creator->name }}</td><td class="text-end"><x-money :amount="$payment->amount" :currency="$payment->sale->currency_code" class="font-semibold" /></td><td class="text-end"><x-money :amount="$payment->tendered_amount" :currency="$payment->sale->currency_code" muted /></td><td class="text-end"><x-money :amount="$payment->change_amount" :currency="$payment->sale->currency_code" muted /></td><td>@if ($payment->evidenceAttachment && auth()->user()->can('pos_sales.payment_evidence_view'))<x-actions.button semantic="view" :label="__('View protected file')" :href="route('payments.evidence.show', $payment->evidenceAttachment)" target="_blank">{{ __('View protected file') }}</x-actions.button>@else<span class="text-text-muted">{{ __('None') }}</span>@endif</td></tr>
                @empty
                    <tr><td colspan="8"><x-state.empty :title="__('No payments match the selected filters.')" :description="__('Try another filter or clear the current filters.')" /></td></tr>
                @endforelse
            </tbody></table>
        </x-tables.table-shell>
        {{ $payments->links() }}
    </div>
</x-layouts::app>
