<x-layouts::app :title="$booking->booking_number">
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <x-page-header :title="$booking->booking_number" :description="__('Party booking and working invoice control center.')">
            <x-slot:actions>
                <flux:button href="{{ route('parties.bookings.index') }}" variant="subtle">{{ __('All bookings') }}</flux:button>
                @can('party_bookings_invoices.print')<flux:button href="{{ route('parties.invoices.print', $booking->invoice->id) }}" target="_blank" variant="subtle" icon="printer">{{ __('Print invoice') }}</flux:button>@endcan
            </x-slot:actions>
        </x-page-header>
        @if(session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif
        @if($errors->any())<flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>@endif

        <div class="grid gap-4 lg:grid-cols-3">
            <flux:card class="lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><flux:heading size="lg">{{ __('Schedule and scope') }}</flux:heading><flux:text class="mt-1">{{ str_starts_with(app()->getLocale(), 'ar') ? $booking->store->name_ar : $booking->store->name_en }} · {{ $booking->location }}</flux:text></div><x-status.badge :status="$booking->status" /></div>
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-3"><div><dt class="text-text-muted">{{ __('Customer') }}</dt><dd class="font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? $booking->customer->name_ar : $booking->customer->name_en }}</dd></div><div><dt class="text-text-muted">{{ __('Party time') }}</dt><dd class="font-semibold">{{ $booking->party_date?->format('Y-m-d') }} · {{ $booking->starts_at?->format('H:i') }}–{{ $booking->ends_at?->format('H:i') }}</dd><dd class="text-xs text-text-muted">{{ $booking->timezone }}</dd></div><div><dt class="text-text-muted">{{ __('Contact') }}</dt><dd class="font-semibold" dir="ltr">{{ $booking->primary_contact }}</dd></div></dl>
                <p class="mt-5 whitespace-pre-wrap text-sm text-text-secondary">{{ $booking->notes ?: __('No additional notes.') }}</p>
            </flux:card>
            <flux:card>
                <flux:heading size="lg">{{ __('Next safe action') }}</flux:heading>
                <div class="mt-4 space-y-3">
                    @if($booking->status === 'draft')
                        @can('party_bookings_invoices.approve')<form method="POST" action="{{ route('parties.bookings.confirm', $booking->id) }}">@csrf<flux:button type="submit" variant="primary" class="w-full">{{ __('Confirm booking') }}</flux:button></form>@endcan
                        <flux:text class="text-sm">{{ __('Confirmation rechecks overlapping Party schedules and resource keys.') }}</flux:text>
                    @elseif(in_array($booking->status, ['confirmed','rescheduled'], true))
                        @can('party_operating_orders_consumables.create')<form method="POST" action="{{ route('parties.orders.store', $booking->id) }}">@csrf<input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}"><flux:button type="submit" variant="primary" class="w-full">{{ __('Create operating order') }}</flux:button></form>@endcan
                    @elseif($booking->status === 'completed_pending_settlement')
                        <flux:callout variant="warning">{{ __('Operations are complete. Reconcile the Party invoice before final close.') }}</flux:callout>
                    @elseif($booking->status === 'closed')
                        <flux:callout variant="success">{{ __('Closed and immutable. Corrections require a referenced document.') }}</flux:callout>
                    @endif
                </div>
            </flux:card>
        </div>

        @if(in_array($booking->status, ['draft', 'tentative', 'confirmed', 'rescheduled'], true))
            <div class="grid gap-4 lg:grid-cols-2">
                @can('party_bookings_invoices.edit')
                    <flux:card>
                        <flux:heading size="lg">{{ __('Reschedule booking') }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ __('Changing the schedule releases current reservations and requires confirmation again.') }}</flux:text>
                        <form method="POST" action="{{ route('parties.bookings.reschedule', $booking->id) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                            @csrf
                            <flux:input name="party_date" type="date" :label="__('Party date')" :value="old('party_date', $booking->party_date?->format('Y-m-d'))" required />
                            <flux:input name="timezone" :label="__('Timezone')" :value="old('timezone', $booking->timezone)" required />
                            <flux:input name="start_time" type="time" :label="__('Start time')" :value="old('start_time', $booking->starts_at?->timezone($booking->timezone)->format('H:i'))" required />
                            <flux:input name="end_time" type="time" :label="__('End time')" :value="old('end_time', $booking->ends_at?->timezone($booking->timezone)->format('H:i'))" required />
                            <div class="sm:col-span-2"><flux:input name="location" :label="__('Location')" :value="old('location', $booking->location)" required /></div>
                            <div class="sm:col-span-2"><flux:textarea name="reason" :label="__('Reschedule reason')" required>{{ old('reason') }}</flux:textarea></div>
                            <div class="sm:col-span-2"><flux:button type="submit" variant="primary" class="w-full sm:w-auto">{{ __('Reschedule booking') }}</flux:button></div>
                        </form>
                    </flux:card>
                @endcan
                @can('party_bookings_invoices.cancel')
                    <flux:card class="border-rose-300 bg-rose-50/60 dark:border-rose-900 dark:bg-rose-950/20">
                        <flux:heading size="lg">{{ __('Cancel booking') }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ __('Cancellation locks the working invoice and releases reserved rental assets. It cannot proceed after payments or operations begin.') }}</flux:text>
                        <form method="POST" action="{{ route('parties.bookings.cancel', $booking->id) }}" class="mt-4 space-y-3" onsubmit="return confirm('{{ __('Cancel this Party booking?') }}')">
                            @csrf
                            <flux:textarea name="reason" :label="__('Cancellation reason')" required>{{ old('reason') }}</flux:textarea>
                            <flux:button type="submit" variant="danger">{{ __('Cancel booking') }}</flux:button>
                        </form>
                    </flux:card>
                @endcan
            </div>
        @endif

        <flux:card>
            <div class="flex flex-wrap items-start justify-between gap-3"><div><flux:heading size="lg">{{ __('Working invoice') }}</flux:heading><flux:text class="mt-1">{{ $booking->invoice->invoice_number }} · {{ ucfirst(str_replace('_',' ',$booking->invoice->state)) }}</flux:text></div><div class="flex flex-wrap gap-2"><flux:button href="{{ route('parties.invoices.show', $booking->invoice->id) }}" variant="subtle">{{ __('Open invoice') }}</flux:button><flux:button href="{{ route('parties.invoices.payments', $booking->invoice->id) }}" variant="subtle">{{ __('Payments') }}</flux:button></div></div>
            <div class="mt-4 overflow-x-auto"><table class="data-table min-w-[650px] w-full text-sm"><thead><tr><th>{{ __('Type') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Line total') }}</th></tr></thead><tbody>@foreach($booking->invoice->lines as $line)<tr><td><flux:badge color="cyan">{{ ucfirst($line->line_type) }}</flux:badge></td><td>{{ str_starts_with(app()->getLocale(), 'ar') ? $line->description_ar : $line->description_en }}</td><td class="text-end tabular-nums">{{ $line->quantity }}</td><td class="text-end tabular-nums">{{ number_format((float)$line->line_total, 2) }} {{ $booking->invoice->currency_code }}</td></tr>@endforeach</tbody></table></div>
            <div class="mt-4 flex justify-end border-t border-border pt-4 text-lg font-bold">{{ __('Total') }}: {{ number_format((float)$booking->invoice->total_amount, 2) }} {{ $booking->invoice->currency_code }}</div>
        </flux:card>
    </div>
</x-layouts::app>
