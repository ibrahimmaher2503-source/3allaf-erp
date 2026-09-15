<x-layouts::app :title="__('Gift Card History')">
    <x-app.page :title="__('Gift Card History')" :description="$document->identifier" max-width="6xl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a class="text-sm text-teal-700 underline" href="{{ route('gift.cards.index') }}">{{ __('Back to Gift Cards') }}</a>
            @can('gift_cards.print')<a class="text-sm text-teal-700 underline" href="{{ route('gift.cards.print', $document) }}">{{ __('Print') }}</a>@endcan
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:card><p class="text-sm text-zinc-500">{{ __('Status') }}</p><div class="mt-2"><x-status.badge :status="$document->status" /></div></flux:card>
            <flux:card><p class="text-sm text-zinc-500">{{ __('Issued value') }}</p><p class="mt-2 text-xl font-semibold">{{ number_format((float) $document->issued_value, 2) }} {{ $document->currency_code }}</p></flux:card>
            <flux:card><p class="text-sm text-zinc-500">{{ __('Available balance') }}</p><p class="mt-2 text-xl font-semibold">{{ number_format((float) $document->balance, 2) }} {{ $document->currency_code }}</p></flux:card>
            <flux:card><p class="text-sm text-zinc-500">{{ __('Issuing store') }}</p><p class="mt-2 font-medium">{{ $document->store?->name_en ?: $document->store_id }}</p></flux:card>
        </div>

        <flux:card class="mt-6 overflow-hidden p-0">
            <div class="border-b border-zinc-200 p-4 dark:border-zinc-700"><flux:heading size="lg">{{ __('Immutable ledger') }}</flux:heading></div>
            <div class="overflow-x-auto">
                <table class="data-table min-w-[820px] w-full text-sm">
                    <thead><tr><th>{{ __('Event') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Balance') }}</th><th>{{ __('Source') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Actor') }}</th><th>{{ __('Recorded') }}</th></tr></thead>
                    <tbody>
                        @forelse($ledger as $entry)
                            <tr class="border-t border-zinc-100 align-top dark:border-zinc-800">
                                <td class="font-medium">{{ str_replace('_', ' ', ucfirst($entry->event_type)) }}</td>
                                <td>{{ number_format((float) $entry->amount, 2) }}</td><td>{{ number_format((float) $entry->balance_after, 2) }}</td>
                                <td>{{ $entry->source_reference ?: ($entry->source_type ? $entry->source_type.' #'.$entry->source_id : '—') }}</td>
                                <td>{{ $entry->reason ?: '—' }}</td><td>{{ $entry->creator?->name ?: '—' }}</td><td>{{ $entry->created_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-state.empty :title="__('No ledger entries')" :description="__('A Gift Card ledger is created when the card is issued.')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $ledger->links() }}</div>
        </flux:card>
    </x-app.page>
</x-layouts::app>
