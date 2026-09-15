<x-layouts::pos :title="__('Suspended Sales')" :store="null" :shift="null">
    <div class="mx-auto w-full max-w-6xl space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3" data-guide="suspended-header">
            <div><flux:heading size="xl">{{ __('Suspended Sales') }}</flux:heading><flux:text class="mt-1">{{ __('Local suspended carts awaiting resume by the cashier.') }}</flux:text></div>
            <flux:button href="{{ route('pos') }}" variant="subtle" icon="arrow-left">{{ __('Back to POS') }}</flux:button>
        </div>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="border-b border-zinc-200 text-xs text-zinc-500 dark:border-zinc-800" data-guide="suspended-table-heading"><tr><th class="p-4 text-start">{{ __('Resume code') }}</th><th class="p-4 text-start">{{ __('Items') }}</th><th class="p-4 text-start">{{ __('Created') }}</th><th class="p-4 text-end">{{ __('Action') }}</th></tr></thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr class="border-b border-zinc-100 last:border-0 dark:border-zinc-800"><td class="p-4 font-mono font-semibold">{{ $sale->suspendedSale?->resume_code }}</td><td class="p-4">{{ $sale->lines->count() }}</td><td class="p-4">{{ $sale->suspended_at?->format('Y-m-d H:i') }}</td><td class="p-4 text-end"><x-actions.button semantic="restore" :label="__('Resume and checkout')" href="{{ route('pos.suspended.resume', $sale) }}" size="sm" variant="primary" data-guide="suspended-action">{{ __('Resume and checkout') }}</x-actions.button></td></tr>
                    @empty
                        <tr><td colspan="4" class="p-10 text-center text-zinc-500">{{ __('No suspended sales.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $sales->links() }}
    </div>
</x-layouts::pos>
