<x-layouts::app :title="__('New Party booking')">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <x-page-header :title="__('New Party booking')" :description="__('Capture the customer, schedule, Party store, responsibilities, and Party-only working invoice lines.')">
            <x-slot:actions><flux:button href="{{ route('parties.bookings.index') }}" variant="subtle" icon="arrow-left">{{ __('Back to bookings') }}</flux:button></x-slot:actions>
        </x-page-header>

        @if($errors->any())<flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first() }}</flux:callout>@endif

        <form method="POST" action="{{ route('parties.bookings.store') }}" class="space-y-5" data-product-line-editor data-autofocus="true" data-next-index="3">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}">
            <section class="rounded-2xl border border-border bg-surface p-5 shadow-card">
                <flux:heading size="lg">{{ __('Party identity and scope') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('This booking is scoped to a Party store. Retail/POS lines are rejected server-side.') }}</flux:text>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <flux:select name="store_id" :label="__('Party store')" required><flux:select.option value="">{{ __('Choose Party store') }}</flux:select.option>@foreach($stores as $store)<flux:select.option value="{{ $store->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $store->name_ar : $store->name_en }}</flux:select.option>@endforeach</flux:select>
                    <flux:select name="customer_id" :label="__('Customer')" required><flux:select.option value="">{{ __('Choose customer') }}</flux:select.option>@foreach($customers as $customer)<flux:select.option value="{{ $customer->id }}">{{ str_starts_with(app()->getLocale(), 'ar') ? $customer->name_ar : $customer->name_en }} · {{ $customer->phone_display }}</flux:select.option>@endforeach</flux:select>
                    <flux:input name="child_id" type="number" :label="__('Child profile ID (optional)')" :value="old('child_id')" />
                    <flux:input name="party_date" type="date" :label="__('Party date')" :value="old('party_date', now()->addDays(5)->toDateString())" required />
                    <flux:input name="start_time" type="time" :label="__('Start time')" :value="old('start_time', '14:00')" required />
                    <flux:input name="end_time" type="time" :label="__('End time')" :value="old('end_time', '17:00')" required />
                    <flux:input name="timezone" :label="__('Timezone')" :value="old('timezone', config('app.timezone', 'UTC'))" required />
                    <flux:input name="location" :label="__('Location / room')" :value="old('location')" required />
                    <flux:input name="primary_contact" :label="__('Primary contact')" :value="old('primary_contact')" required autocomplete="tel" />
                    <flux:input name="secondary_contact" :label="__('Secondary contact')" :value="old('secondary_contact')" autocomplete="tel" />
                    <flux:textarea name="notes" :label="__('Notes and responsibilities')" class="sm:col-span-2" :value="old('notes')" />
                </div>
            </section>

            <section class="rounded-2xl border border-cyan-200 bg-cyan-50/60 p-5 shadow-card dark:border-cyan-900 dark:bg-cyan-950/20">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><flux:heading size="lg">{{ __('Working invoice lines') }}</flux:heading><flux:text class="mt-1 text-sm">{{ __('Services, consumables, rental assets, and other Party charges only. Prices are recalculated on the server.') }}</flux:text></div>
                    <flux:badge color="cyan">{{ __('Party only') }}</flux:badge>
                </div>
                <div class="mt-4 space-y-3">
                    @foreach(range(0, 2) as $i)
                        <div class="grid gap-3 rounded-xl border border-cyan-100 bg-white p-3 dark:border-cyan-900 dark:bg-zinc-900 sm:grid-cols-12" data-product-line>
                            <div class="sm:col-span-2"><flux:select name="lines[{{ $i }}][line_type]" :label="__('Type')" required><flux:select.option value="service">{{ __('Service') }}</flux:select.option><flux:select.option value="consumable">{{ __('Consumable') }}</flux:select.option><flux:select.option value="rental_asset">{{ __('Rental asset') }}</flux:select.option><flux:select.option value="other">{{ __('Other Party charge') }}</flux:select.option></flux:select></div>
                            <div class="sm:col-span-4"><flux:input name="lines[{{ $i }}][description]" :label="__('Description')" :value="old('lines.'.$i.'.description')" :required="$i === 0" /></div>
                            <div class="sm:col-span-2"><label class="text-xs font-semibold">{{ __('Quantity') }}</label><input data-line-quantity name="lines[{{ $i }}][quantity]" type="number" step="0.000001" min="0.000001" value="{{ old('lines.'.$i.'.quantity', $i === 0 ? '1' : '') }}" class="mt-1 h-10 w-full rounded-lg border-zinc-300" @required($i === 0)></div>
                            <div class="sm:col-span-2"><label class="text-xs font-semibold">{{ __('Unit price') }}</label><input data-line-price data-price-kind="price" name="lines[{{ $i }}][unit_price]" type="number" step="0.0001" min="0" value="{{ old('lines.'.$i.'.unit_price', $i === 0 ? '0' : '') }}" class="mt-1 h-10 w-full rounded-lg border-zinc-300" @required($i === 0)></div>
                            <div class="sm:col-span-2"><x-product-line-lookup :name="'lines['.$i.'][product_id]'" :value="old('lines.'.$i.'.product_id')" :label="__('Product')" /></div>
                            <div class="sm:col-span-2"><span class="block text-xs font-semibold">{{ __('Line total') }}</span><output class="mt-1 flex h-10 items-center justify-end rounded-lg bg-zinc-50 px-3 font-mono" data-line-total>0.00</output></div>
                            <div class="sm:col-span-12"><flux:select name="lines[{{ $i }}][asset_id]" :label="__('Rental asset (actual reservation)')"><flux:select.option value="">{{ __('No rental asset for this line') }}</flux:select.option>@foreach($assets as $asset)<flux:select.option value="{{ $asset->id }}">{{ $asset->code }} · {{ $asset->name_en }} · {{ $asset->store->code }}</flux:select.option>@endforeach</flux:select></div>
                        </div>
                    @endforeach
                    <template data-line-template><div class="grid gap-3 rounded-xl border border-cyan-100 bg-white p-3 sm:grid-cols-12" data-product-line><div class="sm:col-span-2"><label class="text-xs font-semibold">{{ __('Type') }}</label><select name="lines[__INDEX__][line_type]" class="mt-1 h-10 w-full rounded-lg border-zinc-300"><option value="service">{{ __('Service') }}</option><option value="consumable">{{ __('Consumable') }}</option><option value="rental_asset">{{ __('Rental asset') }}</option><option value="other">{{ __('Other Party charge') }}</option></select></div><div class="sm:col-span-3"><label class="text-xs font-semibold">{{ __('Description') }}</label><input name="lines[__INDEX__][description]" class="mt-1 h-10 w-full rounded-lg border-zinc-300"></div><div class="sm:col-span-2"><x-product-line-lookup name="lines[__INDEX__][product_id]" :label="__('Product')" /></div><div class="sm:col-span-2"><label class="text-xs font-semibold">{{ __('Quantity') }}</label><input data-line-quantity name="lines[__INDEX__][quantity]" type="number" min="0.000001" step="0.000001" value="1" class="mt-1 h-10 w-full rounded-lg border-zinc-300"></div><div class="sm:col-span-2"><label class="text-xs font-semibold">{{ __('Unit price') }}</label><input data-line-price data-price-kind="price" name="lines[__INDEX__][unit_price]" type="number" min="0" step="0.0001" value="0" class="mt-1 h-10 w-full rounded-lg border-zinc-300"></div><div class="sm:col-span-1"><span class="block text-xs font-semibold">{{ __('Line total') }}</span><output class="mt-1 flex h-10 items-center justify-end" data-line-total>0.00</output></div><div class="sm:col-span-12"><label class="text-xs font-semibold">{{ __('Rental asset') }}</label><select name="lines[__INDEX__][asset_id]" class="mt-1 h-10 w-full rounded-lg border-zinc-300"><option value="">{{ __('No rental asset for this line') }}</option>@foreach($assets as $asset)<option value="{{ $asset->id }}">{{ $asset->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $asset->name_ar : $asset->name_en }} · {{ $asset->store->code }}</option>@endforeach</select></div><button type="button" data-remove-line class="text-sm text-rose-700 underline">{{ __('Remove') }}</button></div></template>
                    <button type="button" data-add-line class="text-sm font-semibold text-cyan-700 underline">{{ __('Add item') }}</button>
                </div>
            </section>

            <div class="flex flex-wrap items-center justify-between gap-3"><flux:text class="max-w-2xl text-sm text-text-muted">{{ __('Saving creates a draft booking and editable working invoice. Confirmation rechecks schedule conflicts before operations can begin.') }}</flux:text><flux:button type="submit" variant="primary">{{ __('Create booking') }}</flux:button></div>
        </form>
    </div>
</x-layouts::app>
