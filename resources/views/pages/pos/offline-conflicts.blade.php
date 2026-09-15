<x-layouts::app :title="__('offline.conflict_title')">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <header class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"><div class="min-w-0 max-w-3xl"><h1 class="text-2xl font-bold tracking-tight text-text-primary sm:text-3xl">{{ __('offline.conflict_title') }}</h1><p class="mt-2 text-sm leading-6 text-text-muted">{{ __('offline.conflict_description') }}</p></div><flux:button href="{{ route('pos.offline.queue') }}" variant="subtle" icon="arrow-left" wire:navigate>{{ __('offline.queue_title') }}</flux:button></header>
        @if (session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif
        <section class="overflow-hidden rounded-xl border border-border bg-surface" aria-labelledby="offline-conflicts-table-title">
            <h2 id="offline-conflicts-table-title" class="sr-only">{{ __('offline.conflict_title') }}</h2>
            @if ($transactions->isEmpty())
                <div class="p-6 text-center" role="status"><p class="font-medium text-text-primary">{{ __('offline.no_conflicts') }}</p></div>
            @else
                <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-surface-muted text-start text-xs text-text-muted"><tr><th class="px-4 py-3 font-medium">{{ __('offline.local_reference') }}</th><th class="px-4 py-3 font-medium">{{ __('offline.scope') }}</th><th class="px-4 py-3 font-medium">{{ __('offline.device') }}</th><th class="px-4 py-3 font-medium">{{ __('offline.captured') }}</th><th class="px-4 py-3"><span class="sr-only">{{ __('offline.review') }}</span></th></tr></thead><tbody class="divide-y divide-border">@foreach ($transactions as $transaction)<tr><td class="px-4 py-3 font-mono text-xs text-text-primary">{{ $transaction->local_uuid }}</td><td class="px-4 py-3 text-text-muted">{{ $transaction->offline_branch_code }} · {{ $transaction->offline_store_code }}</td><td class="px-4 py-3 text-text-muted">{{ $transaction->offline_device_name }}</td><td class="px-4 py-3 text-text-muted">{{ $transaction->synced_at?->format('Y-m-d H:i') }}</td><td class="px-4 py-3 text-end"><x-actions.button semantic="view" :label="__('offline.review')" :href="route('offline.conflicts.show', $transaction)" wire:navigate>{{ __('offline.review') }}</x-actions.button></td></tr>@endforeach</tbody></table></div>
                <div class="border-t border-border px-4 py-3">{{ $transactions->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts::app>
