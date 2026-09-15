<!DOCTYPE html>
@props(['title' => 'POS', 'store' => null, 'shift' => null])
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), config('app.rtl_locales'), true) ? 'rtl' : 'ltr' }}"
>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-100 dark:bg-zinc-950 font-sans antialiased text-zinc-900 dark:text-zinc-100 flex flex-col">
        @php($posStore = $store ?? null)
        @php($posShift = $shift ?? null)
        @php($posBranch = $posStore?->branch)
        @php($posReady = $posStore !== null && $posShift !== null)
        <!-- Dedicated POS Application Top Bar -->
        <header class="border-b border-zinc-200 bg-white px-4 py-2.5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 sticky top-0 z-30">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <x-app-logo href="{{ route('dashboard') }}" wire:navigate />
                    <flux:badge size="sm" class="pos-mode-badge bg-primary-soft text-primary font-bold">{{ __('POS') }}</flux:badge>
                </div>

                <!-- Scope Context Indicators -->
                <div class="flex max-w-full flex-wrap items-center gap-1 overflow-x-auto text-[11px] sm:gap-2 sm:text-xs" data-pos-readiness="{{ $posReady ? 'ready' : 'blocked' }}">
                    <div class="flex shrink-0 flex-col rounded-md border border-zinc-200 bg-zinc-50 px-2 py-1 dark:border-zinc-800 dark:bg-zinc-800">
                        <span class="text-[10px] font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Branch') }}</span>
                        <span class="font-semibold">{{ $posBranch ? $posBranch->code.' · '.(in_array(app()->getLocale(), config('app.arabic_locales', ['ar']), true) ? $posBranch->name_ar : $posBranch->name_en) : __('Unavailable') }}</span>
                    </div>
                    <div class="flex shrink-0 flex-col rounded-md border border-zinc-200 bg-zinc-50 px-2 py-1 dark:border-zinc-800 dark:bg-zinc-800">
                        <span class="text-[10px] font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('POS selling location') }}</span>
                        <span class="font-semibold">{{ $posStore ? $posStore->code.' · '.(in_array(app()->getLocale(), config('app.arabic_locales', ['ar']), true) ? $posStore->name_ar : $posStore->name_en) : __('Unavailable') }}</span>
                    </div>
                    <div class="flex shrink-0 flex-col rounded-md border border-cyan-200 bg-cyan-50 px-2 py-1 text-cyan-900 dark:border-cyan-900/60 dark:bg-cyan-950/30 dark:text-cyan-100">
                        <span class="text-[10px] font-medium uppercase tracking-wide text-cyan-700 dark:text-cyan-300">{{ __('Stock source') }}</span>
                        <span class="font-semibold">{{ $posStore ? __('Same as POS selling location') : __('Unavailable') }}</span>
                    </div>
                    <div class="flex shrink-0 flex-col rounded-md border border-zinc-200 bg-zinc-50 px-2 py-1 dark:border-zinc-800 dark:bg-zinc-800">
                        <span class="text-[10px] font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Drawer') }}</span>
                        <span class="font-semibold">{{ $posShift?->cashDrawer ? $posShift->cashDrawer->code.' · '.(in_array(app()->getLocale(), config('app.arabic_locales', ['ar']), true) ? $posShift->cashDrawer->name_ar : $posShift->cashDrawer->name_en) : __('Unavailable') }}</span>
                    </div>
                    <div class="flex shrink-0 flex-col rounded-md border {{ $posReady ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300' : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300' }} px-2 py-1">
                        <span class="text-[10px] font-medium uppercase tracking-wide">{{ __('Shift') }}</span>
                        <span class="font-semibold">{{ $posShift ? __('Open') : __('Not ready') }}</span>
                    </div>
                </div>

                <!-- Connectivity, Locale, and Navigation controls -->
                <div class="flex min-w-0 max-w-full flex-wrap items-center justify-end gap-2">
                    <div x-data="{ online: navigator.onLine }"
                         x-on:online.window="online = true"
                         x-on:offline.window="online = false"
                         class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-xs font-medium dark:border-zinc-800 dark:bg-zinc-800">
                        <span class="size-2 rounded-full" :class="online ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                        <span x-text="online ? '{{ __('Online') }}' : '{{ __('Offline') }}'" class="text-zinc-700 dark:text-zinc-300"></span>
                    </div>

                    @if (auth()->user()->hasPermission('offline_queue_conflicts.view'))
                        @php($offlineEnabled = ! app()->isProduction() && config('offline.enabled'))
                        <flux:button href="{{ route('pos.offline.queue') }}" size="sm" variant="subtle" icon="signal-slash" wire:navigate>
                            <span class="hidden sm:inline">{{ $offlineEnabled ? __('offline.queue_title') : __('offline.disabled') }}</span>
                            <span class="sm:hidden" aria-label="{{ __('offline.queue_title') }}">{{ $offlineEnabled ? __('offline.queued') : __('Offline') }}</span>
                        </flux:button>
                    @endif

                    <!-- Locale switcher -->
                    <x-locale-switcher compact />

                    <flux:button href="{{ route('dashboard') }}" wire:navigate size="sm" variant="subtle" icon="arrow-left">
                        {{ __('Dashboard') }}
                    </flux:button>
                </div>
            </div>
        </header>

        @include('components.platform.dashboard-tools', ['pageGuide' => \App\Modules\Platform\Data\PageGuideContext::fromRequest(auth()->user())])

        <!-- Main Content Area -->
        <main class="flex-1 flex flex-col p-4">
            {{ $slot }}
        </main>

        <x-app-footer />

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
