<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), config('app.rtl_locales'), true) ? 'rtl' : 'ltr' }}"
>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-zinc-950 antialiased dark:bg-zinc-950 dark:text-white">
        <div class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-5 sm:px-8 lg:px-12">
            <header class="flex min-h-20 items-center justify-between border-b border-zinc-200 dark:border-zinc-800">
                <a href="{{ route('home') }}" aria-label="{{ __('TOY & JOY home') }}" class="flex items-center gap-3">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-teal-700 text-white">
                        <x-app-logo-icon class="size-5" />
                    </span>
                    <span class="text-sm font-bold tracking-wide">{{ config('app.name', 'TOY & JOY') }}</span>
                </a>

                <nav aria-label="{{ __('Account navigation') }}" class="flex items-center gap-2">
                    @auth
                        <flux:button :href="route('dashboard')" variant="primary" wire:navigate>
                            {{ __('Open dashboard') }}
                        </flux:button>
                    @else
                        <flux:button :href="route('login')" variant="ghost" wire:navigate>
                            {{ __('Sign in') }}
                        </flux:button>

                        @if (Route::has('register'))
                            <flux:button :href="route('register')" variant="primary" wire:navigate>
                                {{ __('Create account') }}
                            </flux:button>
                        @endif
                    @endauth
                </nav>
            </header>

            <main class="grid flex-1 items-center gap-14 py-16 lg:grid-cols-[minmax(0,1.1fr)_minmax(22rem,0.9fr)] lg:py-24">
                <section class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-teal-700 dark:text-teal-300">
                        {{ __('TOY & JOY operations') }}
                    </p>
                    <h1 class="mt-5 text-4xl font-bold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                        {{ __('One dependable workspace for daily TOY & JOY operations.') }}
                    </h1>
                    <p class="mt-6 max-w-2xl text-lg leading-8 text-zinc-600 dark:text-zinc-300">
                        {{ __('A browser-based operational platform for retail, inventory, customers, point of sale, and party services, built with clear controls and traceable workflows.') }}
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        @auth
                            <flux:button :href="route('dashboard')" variant="primary" icon-trailing="arrow-right" wire:navigate>
                                {{ __('Continue to workspace') }}
                            </flux:button>
                        @else
                            <flux:button :href="route('login')" variant="primary" icon-trailing="arrow-right" wire:navigate>
                                {{ __('Sign in to continue') }}
                            </flux:button>
                        @endauth
                    </div>
                </section>

                <aside aria-labelledby="scope-heading" class="overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-6 py-5 dark:border-zinc-800">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-500">{{ __('Current delivery focus') }}</p>
                        <h2 id="scope-heading" class="mt-2 text-xl font-semibold">{{ __('Platform foundation') }}</h2>
                    </div>

                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach ([
                            __('Daily retail sales and payments'),
                            __('Inventory and purchasing visibility'),
                            __('Customer, party, and asset workflows'),
                            __('Arabic RTL and English LTR direction support'),
                        ] as $item)
                            <li class="flex items-start gap-3 px-6 py-4 text-sm text-zinc-700 dark:text-zinc-200">
                                <span class="mt-1.5 size-2 shrink-0 rounded-full bg-teal-600" aria-hidden="true"></span>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="px-6 py-5 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Use the workspace to manage the work assigned to your role.') }}
                    </div>
                </aside>
            </main>

            <footer class="border-t border-zinc-200 py-6 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                {{ __('TOY & JOY operational workspace') }}
            </footer>
        </div>

        @fluxScripts
    </body>
</html>
