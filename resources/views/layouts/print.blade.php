<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), config('app.rtl_locales', ['ar']), true) ? 'rtl' : 'ltr' }}"
>
    <head>
        @include('partials.theme-bootstrap')
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? __('Print Preview Placeholder') }} - TOY & JOY</title>
        @vite(['resources/css/app.css'])
        <style>
            @media print {
                .no-print {
                    display: none !important;
                }
                body, html {
                    background-color: #ffffff !important;
                    background: #ffffff !important;
                    color: #000000 !important;
                }
                .print-container {
                    background-color: #ffffff !important;
                    background: #ffffff !important;
                    color: #000000 !important;
                    border: none !important;
                    box-shadow: none !important;
                }
                .print-container * {
                    color: #000000 !important;
                }
            }
        </style>
    </head>
    <body class="bg-zinc-100 dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 antialiased p-4 sm:p-8 min-h-screen">
        <div class="max-w-4xl mx-auto space-y-4 no-print mb-6">
            <div class="flex items-center justify-between bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-xs">
                <div class="flex items-center gap-2">
                    <flux:icon name="printer" class="size-5 text-teal-600 dark:text-teal-400" />
                    <div>
                        <flux:heading size="base" class="font-semibold">{{ __('Print Layout Placeholder') }}</flux:heading>
                        <flux:text size="xs" class="text-zinc-500 dark:text-zinc-400">{{ __('Safe shared layout format without business totals or fake monetary figures.') }}</flux:text>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <flux:button icon="printer" variant="primary" size="sm" onclick="window.print()">
                        {{ __('Print') }}
                    </flux:button>

                    <flux:button icon="x-mark" variant="subtle" size="sm" onclick="window.close()">
                        {{ __('Close') }}
                    </flux:button>
                </div>
            </div>
        </div>

        <!-- Printable Document Body Wrapper -->
        <main class="print-container bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 p-8 rounded-xl shadow-xs border border-zinc-200 dark:border-zinc-700 mx-auto {{ $paperClass ?? 'print-a4-portrait max-w-3xl' }}">
            <!-- Layout Document Header -->
            <header class="border-b border-zinc-300 dark:border-zinc-700 pb-4 mb-6 flex items-start justify-between">
                <div class="space-y-1">
                    <h1 class="text-lg font-bold tracking-tight text-zinc-900 dark:text-zinc-100">{{ $docTitle ?? __('DOCUMENT PREVIEW PLACEHOLDER') }}</h1>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 uppercase tracking-wider font-mono">{{ $docSubheading ?? __('LAYOUT REFERENCE ONLY') }}</p>
                </div>

                <div class="text-end text-xs space-y-1">
                    <span class="inline-block px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 font-mono text-[11px] border border-zinc-200 dark:border-zinc-600">
                        {{ __('LAYOUT PLACEHOLDER') }}
                    </span>
                    <p class="text-zinc-400 dark:text-zinc-500 font-mono text-[10px]">{{ date('Y-m-d H:i:s') }}</p>
                </div>
            </header>

            <!-- Document Content Slot -->
            <section class="space-y-6">
                {{ $slot }}
            </section>

            <!-- Layout Document Footer -->
            <footer class="mt-8 pt-4 border-t border-zinc-200 dark:border-zinc-700 text-xs text-zinc-500 dark:text-zinc-400 flex items-center justify-between font-mono">
                <span>TOY & JOY - Platform Layout Engine</span>
                <span>{{ __('Page 1 of 1') }}</span>
            </footer>
        </main>
    </body>
</html>
