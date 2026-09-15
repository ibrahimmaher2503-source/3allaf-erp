{{--
    Manager variance review (docs/32 §13).

    This is the first screen permitted to show expected versus actual, and it is
    reachable only with `shifts_cash_movements.approve`. Sign convention per
    §12: variance = actual - expected, so a negative figure is a shortage.
--}}
<x-layouts::app :title="__('Shift Variance Review')">
    <div class="mx-auto w-full max-w-5xl p-4 sm:p-6">
        <flux:heading size="xl">{{ __('Shift Variance Review') }}</flux:heading>

        @if (session('success'))
            <flux:callout class="mt-4" variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        @if ($shifts->isEmpty())
            <flux:callout class="mt-6" variant="secondary" icon="inbox">{{ __('No submitted shift is awaiting review.') }}</flux:callout>
        @else
            <div class="mt-6 grid gap-5">
                @foreach ($shifts as $shift)
                    @php($submission = $shift->closingSubmissions->sortByDesc('attempt')->first())
                    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <flux:heading size="lg">{{ $shift->cashDrawer?->code }} — {{ $shift->cashier?->name ?? $shift->cashier?->username }}</flux:heading>
                            <x-status.badge :status="$shift->status->value" />
                        </div>

                        @if ($submission)
                            <x-tables.table-shell :label="__('Shift Variance Review')" class="mt-4">
                                <table class="data-table w-full text-sm">
                                    <thead>
                                        <tr class="text-zinc-500">
                                            <th class="py-2 text-start">{{ __('Method') }}</th>
                                            <th class="py-2 text-end">{{ __('Expected') }}</th>
                                            <th class="py-2 text-end">{{ __('Actual') }}</th>
                                            <th class="py-2 text-end">{{ __('Variance') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                            <td class="py-2 font-medium">{{ __('Cash') }}</td>
                                            <td class="text-end"><x-money :amount="$submission->expected_cash" /></td>
                                            <td class="text-end"><x-money :amount="$submission->actual_cash" /></td>
                                            <td class="text-end"><x-money :amount="$submission->cash_variance" class="font-semibold" /></td>
                                        </tr>
                                        @foreach (($submission->method_variance ?? []) as $code => $delta)
                                            <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                                <td class="py-2 font-medium">{{ $code }}</td>
                                                <td class="text-end"><x-money :amount="(($submission->expected_by_method ?? [])[$code] ?? 0)" /></td>
                                                <td class="text-end"><x-money :amount="(($submission->actual_by_method ?? [])[$code] ?? 0)" /></td>
                                                <td class="text-end"><x-money :amount="$delta" class="font-semibold" /></td>
                                            </tr>
                                        @endforeach
                                        <tr class="border-t-2 border-zinc-200 dark:border-zinc-700">
                                            <td class="py-2 font-bold" colspan="3">{{ __('Total variance') }}</td>
                                            <td class="text-end"><x-money :amount="$submission->total_variance" class="font-bold" /></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </x-tables.table-shell>

                            <flux:text class="mt-2 text-xs text-zinc-500">
                                {{ __('Attempt :n · submitted :at', ['n' => $submission->attempt, 'at' => $submission->submitted_at?->toDayDateTimeString()]) }}
                                @if ($submission->notes) · {{ $submission->notes }} @endif
                            </flux:text>
                        @endif

                        <div class="mt-5">
                            {{-- Decisions are intentionally made only in the shared
                                 Platform inbox. This screen is review evidence,
                                 not a second approval workflow. --}}
                            <flux:button :href="route('admin.approvals')" variant="primary" icon="inbox-arrow-down">
                                {{ __('Open canonical approval request') }}
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">{{ $shifts->links() }}</div>
        @endif

        @if ($closedShifts->isNotEmpty())
            <section class="mt-8 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="closed-shifts-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading id="closed-shifts-heading" size="lg">{{ __('Closed shifts') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Reprint the immutable close record in the required format.') }}</flux:text>
                    </div>
                    <flux:badge color="zinc">{{ $closedShifts->count() }}</flux:badge>
                </div>
                <div class="mt-4 overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="data-table w-full min-w-[42rem] text-sm">
                        <thead><tr><th class="text-start">{{ __('Document') }}</th><th class="text-start">{{ __('Drawer') }}</th><th class="text-start">{{ __('Cashier') }}</th><th class="text-start">{{ __('Closed at') }}</th><th class="text-end">{{ __('Outputs') }}</th></tr></thead>
                        <tbody>
                            @foreach ($closedShifts as $closedShift)
                                <tr>
                                    <td class="font-mono font-semibold">{{ $closedShift->closing_document_number }}</td>
                                    <td>{{ $closedShift->cashDrawer?->code ?: $closedShift->cash_drawer_code_snapshot }}</td>
                                    <td>{{ $closedShift->cashier?->name ?: $closedShift->cashier?->username }}</td>
                                    <td>{{ $closedShift->closed_at?->toDayDateTimeString() }}</td>
                                    <td class="text-end">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <x-actions.button semantic="print" :label="__('Thermal')" :href="route('pos.shift.print.thermal', $closedShift)" target="_blank" rel="noopener">{{ __('Thermal') }}</x-actions.button>
                                            <x-actions.button semantic="print" :label="__('Print A4')" :href="route('pos.shift.print.a4', $closedShift)" target="_blank" rel="noopener">{{ __('Print A4') }}</x-actions.button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-layouts::app>
