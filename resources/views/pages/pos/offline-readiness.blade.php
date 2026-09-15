<x-layouts::app :title="__('offline.title')">
    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6" data-offline-policy data-offline-enabled="{{ $enabled ? 'true' : 'false' }}" data-offline-schema="{{ config('offline.schema_version') }}">
        <header class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0 max-w-3xl">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-text-primary sm:text-3xl">{{ __('offline.title') }}</h1>
                    <flux:badge :color="$enabled ? 'green' : 'amber'">{{ $enabled ? __('offline.enabled') : __('offline.disabled') }}</flux:badge>
                </div>
                <p class="mt-2 text-sm leading-6 text-text-muted">{{ $enabled ? __('offline.enabled_description') : (app()->isProduction() ? __('offline.production_disabled') : __('offline.disabled_description')) }}</p>
            </div>
            @if ($enabled && auth()->user()->hasPermission('offline_queue_conflicts.view'))
                <flux:button href="{{ route('pos.offline.queue') }}" variant="primary" icon="arrow-path" wire:navigate>{{ __('offline.queue_title') }}</flux:button>
            @endif
        </header>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if ($errors->has('offline'))
            <flux:callout variant="danger" icon="exclamation-triangle">{{ $errors->first('offline') }}</flux:callout>
        @endif

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="{{ __('offline.title') }}">
            <div class="rounded-xl border border-border bg-surface p-4"><p class="text-xs font-medium text-text-muted">{{ __('offline.policy') }}</p><p class="mt-2 font-semibold text-text-primary">{{ config('offline.policy_version') }}</p></div>
            <div class="rounded-xl border border-border bg-surface p-4"><p class="text-xs font-medium text-text-muted">{{ __('offline.schema') }}</p><p class="mt-2 font-semibold text-text-primary">{{ config('offline.schema_version') }}</p></div>
            <div class="rounded-xl border border-border bg-surface p-4"><p class="text-xs font-medium text-text-muted">{{ __('offline.queued') }}</p><p class="mt-2 text-xl font-semibold tabular-nums text-text-primary">{{ $queuedCount }}</p></div>
            <div class="rounded-xl border border-border bg-surface p-4"><p class="text-xs font-medium text-text-muted">{{ __('offline.conflicts') }}</p><p class="mt-2 text-xl font-semibold tabular-nums text-text-primary">{{ $conflictCount }}</p></div>
        </section>

        <flux:callout variant="warning" icon="shield-exclamation">{{ __('offline.prohibited') }}</flux:callout>

        <section class="rounded-xl border border-border bg-surface p-4 sm:p-5" aria-labelledby="offline-devices-title">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 id="offline-devices-title" class="text-base font-semibold text-text-primary">{{ __('offline.devices') }}</h2><p class="mt-1 text-sm text-text-muted">{{ __('offline.device_token_help') }}</p></div>
            </div>
            @if ($devices->isEmpty())
                <div class="mt-4" role="status">{{ __('offline.no_devices') }}</div>
            @else
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($devices as $device)
                        <div class="rounded-lg border border-border p-3"><p class="font-medium text-text-primary">{{ $device->name }}</p><p class="mt-1 text-xs text-text-muted">{{ $device->policy_version }} · {{ $device->schema_version }} · {{ __('offline.expires') }} {{ $device->expires_at->format('Y-m-d H:i') }}</p></div>
                    @endforeach
                </div>
            @endif
        </section>

        @if ($enabled && auth()->user()->hasPermission('offline_queue_conflicts.create'))
            <section class="rounded-xl border border-border bg-surface p-4 sm:p-5" aria-labelledby="offline-enroll-title">
                <h2 id="offline-enroll-title" class="text-base font-semibold text-text-primary">{{ __('offline.enroll') }}</h2>
                <form method="POST" action="{{ route('pos.offline.devices.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2" autocomplete="off">
                    @csrf
                    <flux:select name="shift_id" :label="__('offline.shift')" required>
                        <option value="">{{ __('Select an option') }}</option>
                        @foreach ($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->store?->code }} · {{ $shift->cashDrawer?->code }}</option>@endforeach
                    </flux:select>
                    <flux:input name="name" :label="__('offline.device_name')" required maxlength="100" />
                    <flux:input name="token" type="password" :label="__('offline.device_token')" required minlength="20" class="sm:col-span-2" />
                    <div class="sm:col-span-2"><flux:button type="submit" variant="primary" icon="device-phone-mobile">{{ __('offline.enroll') }}</flux:button></div>
                </form>
            </section>
        @endif
    </div>
</x-layouts::app>
