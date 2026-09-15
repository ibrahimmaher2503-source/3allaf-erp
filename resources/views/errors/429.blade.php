<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ str_starts_with(app()->getLocale(), 'ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Too many requests') }} - TOY & JOY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-zinc-50 p-4 text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
    <main class="w-full max-w-md space-y-6 rounded-xl border border-zinc-200 bg-white p-6 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-800" aria-labelledby="error-title">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300" aria-hidden="true">!</div>
        <div class="space-y-2">
            <h1 id="error-title" class="text-xl font-bold tracking-tight">{{ str_starts_with(app()->getLocale(), 'ar') ? 'محاولات كثيرة جدًا' : 'Too many requests' }}</h1>
            <p class="text-xs font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Rate limited') }} (429)</p>
        </div>
        <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">{{ str_starts_with(app()->getLocale(), 'ar') ? 'تم إيقاف الطلبات مؤقتًا لحماية النظام. انتظر قليلًا ثم حاول مرة أخرى.' : 'Requests are temporarily limited to protect the system. Wait a moment and try again.' }}</p>
        @php($retryAfter = isset($exception) ? ($exception->getHeaders()['Retry-After'] ?? null) : null)
        @if ($retryAfter)
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Retry after') }}: {{ $retryAfter }}s</p>
        @endif
        <p class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ __('Reference') }}: {{ \Illuminate\Support\Facades\Context::get('request_id') ?? request()->header('X-Request-ID') ?? 'REQ-UNAVAILABLE' }}</p>
        <a href="{{ request()->fullUrl() }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-zinc-500 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">{{ str_starts_with(app()->getLocale(), 'ar') ? 'حاول مرة أخرى' : 'Try again' }}</a>
    </main>
</body>
</html>
