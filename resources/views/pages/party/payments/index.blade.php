<x-layouts::app :title="__('Party payments')">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <x-page-header :title="__('Party payments')" :description="__('Record separate payments on account against this Party invoice.')">
            <x-slot:actions><flux:button href="{{ route('parties.invoices.show', $invoice->id) }}" variant="subtle">{{ __('Back to invoice') }}</flux:button></x-slot:actions>
        </x-page-header>
        @if(session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif
        @if($errors->any())<flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>@endif
        <div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
            <flux:card>
                <flux:heading size="lg">{{ __('Payment history') }}</flux:heading>
                <flux:text class="mt-1">{{ $invoice->invoice_number }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $invoice->booking->customer->name_ar : $invoice->booking->customer->name_en }}</flux:text>
                <div class="mt-5 overflow-x-auto"><table class="data-table min-w-[680px] w-full text-sm"><thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Method') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Recorded') }}</th><th class="text-end">{{ __('Output') }}</th></tr></thead><tbody>
                    @forelse($invoice->payments as $payment)<tr><td><div class="font-mono font-semibold">{{ $payment->receipt_number }}</div><div class="text-xs text-text-muted">{{ $payment->receipt_label }}</div></td><td>{{ $payment->method_code }}</td><td class="font-semibold">{{ number_format((float)$payment->amount,2) }} {{ $invoice->currency_code }}</td><td>{{ $payment->created_at?->format('Y-m-d H:i') }}</td><td class="text-end">@can('party_bookings_invoices.print')<x-actions.button semantic="print" :label="__('Receipt')" :href="route('parties.payments.print', $payment->id)" target="_blank">{{ __('Receipt') }}</x-actions.button>@endcan</td></tr>
                    @empty<tr><td colspan="5"><x-state.empty :title="__('No Party payments yet.')" :description="__('Payments will appear here as separate immutable receipts.')" /></td></tr>@endforelse
                </tbody></table></div>
            </flux:card>
            <flux:card>
                @can('party_bookings_invoices.create')
                    <flux:heading size="lg">{{ __('Record payment on account') }}</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ __('Overpayment is rejected. Retry with the same idempotency key returns the original receipt.') }}</flux:text>
                    <form method="POST" action="{{ route('parties.invoices.payments.store', $invoice->id) }}" class="mt-5 space-y-4">
                        @csrf<input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}">
                        <flux:select name="payment_method_id" :label="__('Payment method')" required><flux:select.option value="">{{ __('Choose method') }}</flux:select.option>@foreach($methods as $method)<flux:select.option value="{{ $method->id }}" :selected="(string) old('payment_method_id') === (string) $method->id">{{ str_starts_with(app()->getLocale(), 'ar') ? $method->name_ar : $method->name_en }} ({{ $method->type }})</flux:select.option>@endforeach</flux:select>
                        <flux:input name="amount" type="number" step="0.0001" min="0.0001" :value="old('amount')" :label="__('Amount')" required />
                        <flux:input name="reference" :value="old('reference')" :label="__('Reference')" />
                        <flux:input name="evidence_reference" :value="old('evidence_reference')" :label="__('Evidence reference (if required)')" />
                        <flux:button type="submit" variant="primary" class="w-full">{{ __('Record Party payment') }}</flux:button>
                    </form>
                @else
                    <flux:callout variant="info">{{ __('You have read-only access to Party payment history.') }}</flux:callout>
                @endcan
                <div class="mt-5 border-t border-border pt-4 text-sm"><div class="flex justify-between"><span>{{ __('Total') }}</span><strong>{{ number_format((float)$invoice->total_amount,2) }}</strong></div><div class="mt-2 flex justify-between"><span>{{ __('Paid') }}</span><strong>{{ number_format((float)$invoice->paid_amount,2) }}</strong></div><div class="mt-2 flex justify-between font-bold"><span>{{ __('Balance due') }}</span><strong>{{ number_format((float)$invoice->balance_due,2) }}</strong></div></div>
            </flux:card>
        </div>
    </div>
</x-layouts::app>
