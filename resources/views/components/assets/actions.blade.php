@props(['asset', 'reservation' => null, 'checkout' => null, 'assetReturn' => null])

<div {{ $attributes->class('space-y-2') }}>
    <x-actions.button semantic="view" :label="__('View details')" :href="route('party.assets.show', $asset)">{{ __('View details') }}</x-actions.button>
    @can('rental_assets.print')<x-actions.button semantic="print" :label="__('History / print')" :href="route('party.assets.print', $asset)">{{ __('History / print') }}</x-actions.button>@endcan
    @can('rental_assets.reserve')
        @if($asset->status === 'available')
            <details class="rounded-xl border border-border p-3"><summary class="min-h-11 cursor-pointer py-2 text-sm font-semibold">{{ __('Reserve this asset') }}</summary><form method="POST" action="{{ route('party.assets.reserve', $asset) }}" class="mt-2 grid gap-2 sm:grid-cols-2">@csrf<input type="hidden" name="timezone" value="{{ config('app.timezone') }}"><input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}"><flux:input name="starts_at" type="datetime-local" label="{{ __('Starts') }}" value="{{ now()->addDay()->startOfHour()->format('Y-m-d\TH:i') }}" required /><flux:input name="ends_at" type="datetime-local" label="{{ __('Ends') }}" value="{{ now()->addDays(2)->startOfHour()->format('Y-m-d\TH:i') }}" required /><flux:input name="source_reference" label="{{ __('Party / booking reference') }}" required /><flux:button type="submit" size="sm" variant="primary">{{ __('Reserve interval') }}</flux:button></form></details>
        @endif
    @endcan
    @can('rental_assets.checkout')
        @if($asset->status === 'reserved' && $reservation)
            <form method="POST" action="{{ route('party.assets.checkout', $asset) }}" class="grid gap-2 rounded-xl border border-border p-3">@csrf<input type="hidden" name="reservation_id" value="{{ $reservation->id }}"><input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}"><flux:input name="source_reference" label="{{ __('Booking / order reference') }}" value="{{ $reservation->source_reference }}" required /><flux:button type="submit" size="sm" variant="primary">{{ __('Check out') }}</flux:button></form>
        @endif
    @endcan
    @can('rental_assets.return')
        @if($asset->status === 'checked_out' && $checkout)
            <form method="POST" action="{{ route('party.assets.return', $asset) }}" class="grid gap-2 rounded-xl border border-border p-3">@csrf<input type="hidden" name="checkout_id" value="{{ $checkout->id }}"><input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}"><flux:input name="condition_after" value="{{ $asset->condition }}" label="{{ __('Condition on return') }}" required /><flux:input name="location_after" value="{{ $asset->location }}" label="{{ __('Return location') }}" /><flux:button type="submit" size="sm" variant="primary">{{ __('Return for inspection') }}</flux:button></form>
        @endif
    @endcan
    @can('rental_assets.inspect')
        @if($asset->status === 'under_inspection' && $assetReturn)
            <form method="POST" action="{{ route('party.assets.inspect', $asset) }}" class="grid gap-2 rounded-xl border border-border p-3">@csrf<input type="hidden" name="return_id" value="{{ $assetReturn->id }}"><flux:select name="resulting_status" label="{{ __('Inspection outcome') }}"><option value="available">{{ __('Available') }}</option><option value="damaged">{{ __('Damaged') }}</option><option value="under_maintenance">{{ __('Under maintenance') }}</option><option value="lost">{{ __('Lost') }}</option></flux:select><flux:textarea name="assessment" label="{{ __('Inspection findings') }}" rows="2" required /><flux:button type="submit" size="sm" variant="primary">{{ __('Complete inspection') }}</flux:button></form>
        @endif
    @endcan
    @can('rental_assets.create')
        @if(in_array($asset->status, ['available', 'damaged', 'under_maintenance', 'lost'], true))
            <details class="rounded-xl border border-border p-3"><summary class="min-h-11 cursor-pointer py-2 text-sm font-semibold">{{ __('Record damage, loss, maintenance or depreciation') }}</summary><form method="POST" action="{{ route('party.assets.events.store', $asset) }}" class="mt-2 grid gap-2">@csrf<input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}"><flux:select name="event_type" label="{{ __('Event type') }}"><option value="damage">{{ __('Damage') }}</option><option value="loss">{{ __('Loss') }}</option><option value="maintenance">{{ __('Maintenance') }}</option><option value="depreciation">{{ __('Depreciation history') }}</option></flux:select><flux:textarea name="assessment" label="{{ __('Assessment') }}" rows="2" required /><flux:input name="party_reference" label="{{ __('Source / party reference') }}" /><flux:select name="resulting_status" label="{{ __('Resulting status') }}"><option value="damaged">{{ __('Damaged') }}</option><option value="lost">{{ __('Lost') }}</option><option value="under_maintenance">{{ __('Under maintenance') }}</option><option value="available">{{ __('Available') }}</option><option value="retired">{{ __('Retired') }}</option></flux:select>@can('rental_assets.cost_edit')<flux:input name="cost_value" type="number" step="0.01" min="0" label="{{ __('Optional operational cost') }}" />@endcan<flux:button type="submit" size="sm" variant="primary">{{ __('Submit for approval') }}</flux:button></form></details>
        @endif
    @endcan
</div>
