<x-layouts::app :title="__('Final Party settlement')">
    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 sm:p-6">
        <x-page-header :title="__('Final Party settlement')" :description="__('Review the final close checklist before the Party invoice becomes immutable.')"><x-slot:actions><flux:button href="{{ route('parties.invoices.show', $invoice->id) }}" variant="subtle">{{ __('Back to invoice') }}</flux:button></x-slot:actions></x-page-header>
        @if($errors->any())<flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>@endif
        <flux:card class="border-amber-300 bg-amber-50 dark:bg-amber-950/20">
            <flux:heading size="lg">{{ __('This action is permanent') }}</flux:heading>
            <flux:text class="mt-2">{{ __('The server will lock the booking, invoice, payments, Party Wallet ledger, and numbering sequence together. Open operating orders, invalid totals, Product Wallet use, and concurrent close attempts are blocked.') }}</flux:text>
            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-3"><div><dt class="text-text-muted">{{ __('Working invoice') }}</dt><dd class="font-mono font-semibold">{{ $invoice->invoice_number }}</dd></div><div><dt class="text-text-muted">{{ __('Final total') }}</dt><dd class="font-semibold">{{ number_format((float)$invoice->total_amount,2) }} {{ $invoice->currency_code }}</dd></div><div><dt class="text-text-muted">{{ __('Party payments') }}</dt><dd class="font-semibold">{{ number_format((float)$invoice->paid_amount,2) }} {{ $invoice->currency_code }}</dd></div></dl>
            @can('party_bookings_invoices.approve')
                <form method="POST" action="{{ route('parties.invoices.finalize', $invoice->id) }}" class="mt-6 flex flex-wrap items-end gap-3">@csrf<input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}"><div class="min-w-64 flex-1"><flux:input name="confirmation" :label="__('Type FINAL CLOSE to confirm')" required /></div><flux:button type="submit" variant="primary">{{ __('Finalize and close') }}</flux:button></form>
            @else
                <flux:callout class="mt-6" variant="info">{{ __('You may review this settlement, but only an approver can finalize it.') }}</flux:callout>
            @endcan
        </flux:card>
    </div>
</x-layouts::app>
