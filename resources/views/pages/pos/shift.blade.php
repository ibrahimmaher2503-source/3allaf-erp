{{--
    Cashier shift screen (docs/32 §17).

    CSH-02 / docs/32 §10: this view must never render an expected total, a
    variance, or any figure the cashier could work one out from. The controller
    deliberately passes no expected data at all, so there is nothing here to
    leak through HTML, a hidden field, or a preloaded response.
--}}
<x-layouts::pos :title="__('Cash Shift')" :store="$shift?->store" :shift="$shift">
    <div class="mx-auto w-full max-w-3xl p-4 sm:p-6">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-border pb-4">
            <div><flux:heading size="xl">{{ __('Shifts & collections') }}</flux:heading><flux:text class="mt-1">{{ __('Manage your active drawer and review recent immutable shift closes.') }}</flux:text></div>
            <nav class="flex flex-wrap gap-2" aria-label="{{ __('Sales navigation') }}">
                <flux:button href="{{ route('sales.index') }}" variant="subtle" icon="presentation-chart-line" wire:navigate>{{ __('Sales overview') }}</flux:button>
                @can('pos_sales.create')<flux:button href="{{ route('pos') }}" variant="primary" icon="shopping-cart" wire:navigate>{{ __('Open POS') }}</flux:button>@endcan
            </nav>
        </header>

        @if (session('success'))
            <flux:callout class="mt-4" variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        @if (! $shift)
            <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Open a shift') }}</flux:heading>
                <flux:text class="mt-1">{{ __('A shift must be open before any sale can be recorded.') }}</flux:text>

                @if ($drawers->isEmpty())
                    <flux:callout class="mt-4" variant="warning" icon="exclamation-triangle">{{ __('No active cash drawer is assigned to you.') }}</flux:callout>
                @else
                    <form method="POST" action="{{ route('pos.shift.store') }}" class="mt-4 grid gap-3">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $openToken }}">
                        <flux:select name="cash_drawer_id" :label="__('Sales outlet and cash drawer')" required>
                            @foreach ($drawers as $drawer)
                                <flux:select.option value="{{ $drawer->id }}">{{ \App\Support\HumanName::name($drawer->store) }} — {{ \App\Support\HumanName::code($drawer->store) }} · {{ \App\Support\HumanName::name($drawer) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @if($drawers->count() === 1)
                            <flux:callout variant="info" icon="building-storefront">{{ __('Your authorized sales outlet is :outlet.', ['outlet'=>\App\Support\HumanName::name($drawers->first()->store)]) }}</flux:callout>
                        @else
                            <flux:text>{{ __('Select the exact authorized sales outlet before opening the shift.') }}</flux:text>
                        @endif
                        <flux:checkbox name="confirm_outlet" value="1" :label="__('I confirm this sales outlet and its stock source for the shift.')" required />
                        <flux:input type="number" step="0.01" min="0" name="opening_float" :label="__('Opening float')" value="0.00" required />
                        @error('cash_drawer_id')<flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>@enderror
                        <flux:button type="submit" variant="primary" icon="lock-open">{{ __('Open shift') }}</flux:button>
                    </form>
                @endif
            </div>

            @if ($closedShifts->isNotEmpty())
                <section class="mt-6 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="closed-shifts-heading">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <flux:heading id="closed-shifts-heading" size="lg">{{ __('Closed shifts') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Print a historical close without changing its immutable record.') }}</flux:text>
                        </div>
                        <flux:badge color="zinc">{{ $closedShifts->count() }}</flux:badge>
                    </div>
                    <div class="mt-4 overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="data-table responsive-resource-table w-full text-sm">
                            <thead><tr><th class="text-start">{{ __('Document') }}</th><th class="text-start">{{ __('Drawer') }}</th><th class="text-start">{{ __('Closed at') }}</th><th class="text-end">{{ __('Outputs') }}</th></tr></thead>
                            <tbody>
                                @foreach ($closedShifts as $closedShift)
                                    <tr>
                                        <td data-primary class="font-mono font-semibold">{{ $closedShift->closing_document_number }}</td>
                                        <td data-label="{{ __('Drawer') }}">{{ $closedShift->cashDrawer?->code ?: $closedShift->cash_drawer_code_snapshot }}</td>
                                        <td data-label="{{ __('Closed at') }}">{{ $closedShift->closed_at?->translatedFormat(str_starts_with(app()->getLocale(), 'ar') ? 'j M Y، H:i' : 'M j, Y g:i A') }}</td>
                                        <td data-label="{{ __('Outputs') }}" class="text-end">
                                            @can('shifts_cash_movements.print')
                                                <div class="flex flex-wrap justify-end gap-2">
                                                    <x-actions.button semantic="print" :label="__('Thermal')" :href="route('pos.shift.print.thermal', $closedShift)" target="_blank" rel="noopener">{{ __('Thermal') }}</x-actions.button>
                                                    <x-actions.button semantic="print" :label="__('Print A4')" :href="route('pos.shift.print.a4', $closedShift)" target="_blank" rel="noopener">{{ __('Print A4') }}</x-actions.button>
                                                </div>
                                            @else
                                                <span class="text-zinc-500">{{ __('Not permitted') }}</span>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        @else
            <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg">{{ __('Active shift') }}</flux:heading>
                    <x-status.badge :status="$shift->status->value" />
                </div>
                <dl class="mt-4 grid gap-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Drawer') }}</dt><dd class="text-end font-semibold">{{ $shift->cashDrawer?->code }} · {{ str_starts_with(app()->getLocale(), 'ar') ? $shift->cashDrawer?->name_ar : $shift->cashDrawer?->name_en }}<span class="block text-xs font-normal text-zinc-500">{{ $shift->branch?->code }} → {{ $shift->store?->code }}</span></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Opened at') }}</dt><dd>{{ $shift->opened_at?->translatedFormat(str_starts_with(app()->getLocale(), 'ar') ? 'j M Y، H:i' : 'M j, Y g:i A') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Opening float') }}</dt><dd><x-money :amount="$shift->opening_cash" :currency="$shift->currency_code" /></dd></div>
                    @if ($shift->recount_count > 0)
                        <div class="flex justify-between gap-3"><dt class="text-zinc-500">{{ __('Recounts requested') }}</dt><dd>{{ $shift->recount_count }}</dd></div>
                    @endif
                </dl>
            </div>

            @if ($shift->status->acceptsActivity())
                <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Cash movement') }}</flux:heading>
                    <form method="POST" action="{{ route('pos.shift.cash-movement', $shift) }}" class="mt-4 grid gap-3">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $movementToken }}">
                        <flux:select name="movement_type" :label="__('Type')" required>
                            @foreach ($movementTypes as $type)
                                <flux:select.option value="{{ $type }}">{{ __(ucwords(str_replace('_', ' ', $type))) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input type="number" step="0.01" min="0.01" name="amount" :label="__('Amount')" required />
                        <flux:input name="reason" :label="__('Reason')" required />
                        <flux:input name="reference" :label="__('Reference (optional)')" />
                        @error('amount')<flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>@enderror
                        <flux:button type="submit" variant="subtle" icon="banknotes">{{ __('Record movement') }}</flux:button>
                    </form>

                    @if ($shift->cashMovements->isNotEmpty())
                        <x-tables.table-shell :label="__('Cash movement')" class="mt-5">
                            <table class="data-table w-full text-sm">
                                <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Reason') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($shift->cashMovements as $movement)
                                        <tr>
                                            <td>{{ __(ucwords(str_replace('_', ' ', $movement->movement_type))) }}</td>
                                            <td>{{ $movement->reason }}</td>
                                            <td class="text-end"><x-money :amount="$movement->amount" :currency="$shift->currency_code" class="font-semibold" /></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-tables.table-shell>
                    @endif
                </div>

                <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Blind close') }}</flux:heading>
                    {{-- CSH-02: the cashier counts first and is shown nothing to count towards. --}}
                    <flux:text class="mt-1">{{ __('Count the drawer and enter the actual amounts. Expected totals are not shown before you submit.') }}</flux:text>
                    <form method="POST" action="{{ route('pos.shift.blind-close', $shift) }}" class="mt-4 grid gap-3">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $closeToken }}">
                        <flux:input type="number" step="0.01" min="0" name="actual_cash" :label="__('Counted cash')" required />
                        @foreach ($methods as $method)
                            <flux:input
                                type="number"
                                step="0.01"
                                min="0"
                                name="actual_by_method[{{ $method->code }}]"
                                :label="(str_starts_with(app()->getLocale(), 'ar') ? $method->name_ar : $method->name_en).' — '.__('counted total')" />
                        @endforeach
                        <flux:textarea name="notes" :label="__('Notes (optional)')" rows="2" />
                        @error('actual_cash')<flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>@enderror
                        <flux:button type="submit" variant="primary" icon="lock-closed">{{ __('Submit count') }}</flux:button>
                    </form>
                </div>
            @else
                <flux:callout class="mt-6" variant="secondary" icon="clock">{{ __('Your count has been submitted and is awaiting review. No further sales or cash movements can be recorded on this shift.') }}</flux:callout>
            @endif
        @endif
    </div>
</x-layouts::pos>
