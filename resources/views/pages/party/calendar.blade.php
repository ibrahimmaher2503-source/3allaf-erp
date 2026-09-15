<x-layouts::app :title="__('Party calendar')">
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <x-page-header :title="__('Party calendar')" :description="__('Review Party asset reservations in your authorized scope before scheduling or approving work.')">
            <x-slot:actions>
                <flux:button href="{{ route('parties.bookings.index') }}" variant="subtle" icon="cake">{{ __('Party bookings') }}</flux:button>
            </x-slot:actions>
        </x-page-header>

        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>
        @endif

        <flux:card>
            <form method="GET" class="grid gap-4 sm:grid-cols-[minmax(0,12rem)_minmax(0,12rem)_auto] sm:items-end">
                <flux:input name="from" type="date" :label="__('From')" :value="$from->toDateString()" required />
                <flux:input name="to" type="date" :label="__('To')" :value="$to->toDateString()" required />
                <flux:button type="submit" variant="primary" icon="calendar-days">{{ __('Show calendar') }}</flux:button>
            </form>
            <flux:text class="mt-3 text-sm">{{ __('Choose up to 31 days. Only overlapping reservations in your authorized branch or Party-store scope are shown.') }}</flux:text>
        </flux:card>

        @can('party_bookings_invoices.view')
            <x-tables.data-panel :title="str_starts_with(app()->getLocale(), 'ar') ? 'أجندة الحجوزات' : 'Booking agenda'" :description="str_starts_with(app()->getLocale(), 'ar') ? 'المواعيد المؤكدة والمبدئية ضمن النطاق والتاريخ المختارين.' : 'Operational bookings within the authorized scope and selected dates.'">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">@forelse($bookings as $booking)<a href="{{ route('parties.bookings.show', $booking) }}" class="rounded-xl border border-border bg-surface p-4 transition hover:border-primary"><div class="flex items-start justify-between gap-3"><strong class="font-mono" dir="ltr">{{ $booking->booking_number }}</strong><x-status.badge :status="$booking->status" /></div><p class="mt-2 font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? $booking->customer?->name_ar : $booking->customer?->name_en }}</p><p class="mt-1 text-sm text-text-muted">{{ $booking->starts_at?->format('Y-m-d H:i') }}–{{ $booking->ends_at?->format('H:i') }}</p><p class="mt-1 truncate text-sm text-text-muted">{{ $booking->location }} · {{ $booking->store?->code }}</p></a>@empty<div class="md:col-span-2 xl:col-span-3"><x-state.empty :title="str_starts_with(app()->getLocale(), 'ar') ? 'لا توجد حجوزات في هذه الفترة' : 'No bookings in this period'" icon="calendar-days" /></div>@endforelse</div>
            </x-tables.data-panel>
        @endcan

        <x-tables.data-panel :title="__('Scheduled asset reservations')" :description="__('Reservations are operational availability records, not retail stock or financial postings.')">
            <x-slot:actions>
                <flux:badge color="zinc">{{ $reservations->count() }} / 200 {{ __('shown') }}</flux:badge>
            </x-slot:actions>
            <div class="responsive-resource-table">
                <table class="data-table data-table--mobile-summary w-full text-sm">
                    <thead><tr><th>{{ __('Asset') }}</th><th>{{ __('Party store') }}</th><th>{{ __('Starts') }}</th><th>{{ __('Ends') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($reservations as $reservation)
                            <tr><td><div class="font-mono font-semibold">{{ $reservation->asset?->code }}</div><div class="text-xs text-text-muted">{{ str_starts_with(app()->getLocale(), 'ar') ? $reservation->asset?->name_ar : $reservation->asset?->name_en }}</div></td><td>{{ $reservation->store?->code ?: __('Not recorded') }}</td><td class="tabular-nums">{{ $reservation->starts_at?->timezone($reservation->timezone)->format('Y-m-d H:i') }}</td><td class="tabular-nums">{{ $reservation->ends_at?->timezone($reservation->timezone)->format('Y-m-d H:i') }}</td><td class="font-mono text-xs">{{ $reservation->source_reference ?: __('Not recorded') }}</td><td><x-status.badge :status="$reservation->status" /></td></tr>
                        @empty
                            <tr><td colspan="6"><x-state.empty :title="__('No Party asset reservations in this range.')" :description="__('Try another approved date range, or create a booking with an available rental asset.')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-tables.data-panel>
    </div>
</x-layouts::app>
