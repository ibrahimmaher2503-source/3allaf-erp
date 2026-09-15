@php
    $pageTitle = match($mode) {
        'payments' => __('Party payment invoices'),
        'settlement' => __('Party settlement invoices'),
        default => __('Working invoices'),
    };
    $pageDescription = match($mode) {
        'payments' => __('Find an open Party invoice with a remaining balance, then record its payment.'),
        'settlement' => __('Find a non-final Party invoice, then review its final settlement checklist.'),
        default => __('Find a Party booking or invoice, then open its permission-scoped working invoice editor.'),
    };
@endphp
<x-layouts::app :title="$pageTitle">
    <x-app.page :title="$pageTitle" :description="$pageDescription" max-width="7xl">
        <flux:card>
            <form method="GET" class="grid gap-3 sm:grid-cols-[1fr_12rem_auto] sm:items-end">
                <input type="hidden" name="mode" value="{{ $mode }}">
                <flux:input name="q" :value="$term" :label="__('Invoice, booking, or customer')" placeholder="PI-, PB-, or customer name" />
                <flux:select name="state" :label="__('Invoice state')"><option value="">{{ __('All states') }}</option>@foreach(['draft','confirmed','final'] as $option)<option value="{{ $option }}" @selected($state === $option)>{{ str($option)->headline() }}</option>@endforeach</flux:select>
                <flux:button type="submit" variant="subtle" icon="magnifying-glass">{{ __('Filter') }}</flux:button>
            </form>
        </flux:card>
        <x-tables.data-panel :title="__('Party invoice work queue')" :description="__('Only invoices in your authorized Party branch and store scope are shown.')">
            <div class="responsive-resource-table"><table class="data-table data-table--mobile-summary w-full"><thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Booking') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Store') }}</th><th>{{ __('State') }}</th><th class="text-end">{{ __('Balance due') }}</th><th class="text-end">{{ __('Action') }}</th></tr></thead><tbody>
                @forelse($invoices as $invoice)<tr><td data-label="{{ __('Invoice') }}" class="font-mono font-semibold">{{ $invoice->invoice_number }}</td><td data-label="{{ __('Booking') }}" class="font-mono">{{ $invoice->booking?->booking_number }}</td><td data-label="{{ __('Customer') }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $invoice->booking?->customer?->name_ar : $invoice->booking?->customer?->name_en }}</td><td data-label="{{ __('Store') }}">{{ $invoice->booking?->store?->code }}</td><td data-label="{{ __('State') }}"><x-status.badge :status="$invoice->state" /></td><td data-label="{{ __('Balance due') }}" class="text-end tabular-nums">{{ number_format((float) $invoice->balance_due, 2) }} {{ $invoice->currency_code }}</td><td data-label="{{ __('Action') }}" class="text-end">
                    @if($mode === 'payments')
                        <x-actions.button semantic="view" :label="__('Open payments')" :href="route('parties.invoices.payments', $invoice->id)">{{ __('Open payments') }}</x-actions.button>
                    @elseif($mode === 'settlement')
                        <x-actions.button semantic="view" :label="__('Review settlement')" :href="route('parties.invoices.settle', $invoice->id)">{{ __('Review settlement') }}</x-actions.button>
                    @else
                        <x-actions.button semantic="edit" :label="__('Open editor')" :href="route('parties.invoices.show', $invoice->id)">{{ __('Open editor') }}</x-actions.button>
                    @endif
                </td></tr>
                @empty<tr><td colspan="7"><x-state.empty :title="__('No Party invoices found.')" :description="__('Create a Party booking or adjust the filters.')" /></td></tr>@endforelse
            </tbody></table></div>
            <x-slot:footer>@if($invoices->hasPages()){{ $invoices->links() }}@endif</x-slot:footer>
        </x-tables.data-panel>
    </x-app.page>
</x-layouts::app>
