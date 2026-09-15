<x-layouts::app :title="__('offline.queue_title')">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6" data-offline-policy data-offline-enabled="{{ ! app()->isProduction() && config('offline.enabled') ? 'true' : 'false' }}" data-offline-schema="{{ config('offline.schema_version') }}">
        <header class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0 max-w-3xl"><h1 class="text-2xl font-bold tracking-tight text-text-primary sm:text-3xl">{{ __('offline.queue_title') }}</h1><p class="mt-2 text-sm leading-6 text-text-muted">{{ __('offline.queue_description') }}</p></div>
            <div class="flex flex-wrap gap-2"><flux:button href="{{ route('pos.offline-readiness') }}" variant="subtle" icon="signal-slash" wire:navigate>{{ __('offline.title') }}</flux:button>@can('offline_queue_conflicts.approve')<flux:button href="{{ route('offline.conflicts.index') }}" variant="subtle" icon="exclamation-triangle" wire:navigate>{{ __('offline.conflicts') }}</flux:button>@endcan</div>
        </header>

        @if (session('success'))<flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>@endif
        @if ($errors->has('offline'))<flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first('offline') }}</flux:callout>@endif

        <section class="rounded-xl border border-border bg-surface p-4 sm:p-5" aria-labelledby="offline-sync-title">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"><div><h2 id="offline-sync-title" class="text-base font-semibold text-text-primary">{{ __('offline.sync_now') }}</h2><p class="mt-1 text-sm text-text-muted">{{ __('offline.sync_help') }}</p></div>
                @if ($devices->isNotEmpty())
                    <form method="POST" action="{{ route('pos.offline.sync') }}" class="grid gap-3 sm:grid-cols-3" x-data="{ submitting: false }" x-on:submit="submitting = true" autocomplete="off">
                        @csrf
                        <flux:select name="offline_device_id" :label="__('offline.device')" required>@foreach ($devices as $device)<option value="{{ $device->id }}">{{ $device->name }}</option>@endforeach</flux:select>
                        <flux:input name="token" type="password" :label="__('offline.device_token')" required />
                        <div class="flex items-end"><flux:button type="submit" variant="primary" icon="arrow-path" x-bind:disabled="submitting"><span x-show="!submitting">{{ __('offline.sync_now') }}</span><span x-cloak x-show="submitting">{{ __('offline.syncing') }}</span></flux:button></div>
                    </form>
                @endif
            </div>
            <p class="mt-3 text-xs text-text-muted" role="status" aria-live="polite" data-offline-sync-status>{{ __('offline.ready') }}</p>
        </section>

        <section class="overflow-hidden rounded-xl border border-border bg-surface" aria-labelledby="offline-queue-table-title">
            <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3"><h2 id="offline-queue-table-title" class="font-semibold text-text-primary">{{ __('offline.queued') }}</h2><span class="text-xs text-text-muted">{{ $transactions->total() }}</span></div>
            @if ($transactions->isEmpty())
                <div class="p-6 text-center" role="status"><p class="font-medium text-text-primary">{{ __('offline.no_transactions') }}</p><p class="mt-1 text-sm text-text-muted">{{ __('offline.queue_description') }}</p></div>
            @else
                <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-surface-muted text-start text-xs text-text-muted"><tr><th class="px-4 py-3 font-medium">{{ __('offline.local_reference') }}</th><th class="px-4 py-3 font-medium">{{ __('offline.device') }}</th><th class="px-4 py-3 font-medium">{{ __('offline.state') }}</th><th class="px-4 py-3 font-medium">{{ __('offline.captured') }}</th><th class="px-4 py-3 font-medium">{{ __('offline.expires') }}</th></tr></thead><tbody class="divide-y divide-border">@foreach ($transactions as $transaction)<tr><td class="px-4 py-3 font-mono text-xs text-text-primary">{{ $transaction->local_uuid }}</td><td class="px-4 py-3 text-text-muted">{{ $transaction->device?->name }}</td><td class="px-4 py-3"><flux:badge size="sm" color="{{ $transaction->state->value === 'conflict' ? 'amber' : ($transaction->state->value === 'accepted' ? 'emerald' : 'zinc') }}">{{ $transaction->state->value }}</flux:badge></td><td class="px-4 py-3 text-text-muted">{{ $transaction->captured_at->format('Y-m-d H:i') }}</td><td class="px-4 py-3 text-text-muted">{{ $transaction->expires_at->format('Y-m-d H:i') }}</td></tr>@endforeach</tbody></table></div>
                <div class="border-t border-border px-4 py-3">{{ $transactions->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts::app>
