<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), config('app.rtl_locales', ['ar']), true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('System Error') }} - TOY & JOY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100 flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6 text-center space-y-6">
        <div class="mx-auto w-12 h-12 rounded-full bg-red-100 dark:bg-red-950/50 text-red-600 dark:text-red-400 flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>

        <div class="space-y-2">
            <h1 class="text-xl font-bold tracking-tight">خطأ غير متوقع في النظام</h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 uppercase tracking-widest font-semibold">{{ str_starts_with(app()->getLocale(), 'ar') ? '500' : 'Unexpected system error (500)' }}</p>
        </div>

        <p class="text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed">
            حدث خطأ غير متوقع أثناء معالجة طلبك. تم حماية النظام وتسجيل الملاحظة بأمان.
        </p>

        @php
            $requestId = \Illuminate\Support\Facades\Context::get('request_id') ?? request()->header('X-Request-ID') ?? 'REQ-UNAVAILABLE';
        @endphp

        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md bg-zinc-100 dark:bg-zinc-700/50 text-xs font-mono text-zinc-600 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-600 max-w-full truncate">
            <span class="text-zinc-400 dark:text-zinc-500 select-none">ID:</span>
            <span class="truncate">{{ $requestId }}</span>
        </div>

        <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="{{ request()->fullUrl() }}" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-lg bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 hover:bg-zinc-800 dark:hover:bg-white transition-colors">
                {{ str_starts_with(app()->getLocale(), 'ar') ? 'إعادة المحاولة' : 'Retry' }}
            </a>
            <a href="{{ route('dashboard') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-lg bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors border border-zinc-200 dark:border-zinc-700">
                {{ str_starts_with(app()->getLocale(), 'ar') ? 'لوحة التحكم' : 'Dashboard' }}
            </a>
        </div>
    </div>
</body>
</html>
