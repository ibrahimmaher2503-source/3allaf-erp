<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), config('app.rtl_locales', ['ar']), true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Access Denied') }} - TOY & JOY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100 flex items-center justify-center p-4">
    @php($isArabic = str_starts_with(app()->getLocale(), 'ar'))
    <div class="max-w-md w-full bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6 text-center space-y-6">
        <div class="mx-auto w-12 h-12 rounded-full bg-orange-100 dark:bg-orange-950/50 text-orange-600 dark:text-orange-400 flex items-center justify-center" aria-hidden="true">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-8 0v4h8z" /></svg>
        </div>
        <div class="space-y-2">
            <h1 class="text-xl font-bold tracking-tight">{{ $isArabic ? 'غير مصرح بالوصول' : 'Access denied' }}</h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 uppercase tracking-widest font-semibold">{{ $isArabic ? '403' : 'Access denied (403)' }}</p>
        </div>
        <p class="text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed">{{ app()->isLocale('ar-EG') ? 'ليس لديك الصلاحية الكافية للوصول إلى الصفحة دي أو تنفيذ الإجراء ده.' : ($isArabic ? 'ليس لديك الصلاحية الكافية للوصول إلى هذه الصفحة أو تنفيذ هذا الإجراء.' : 'You do not have permission to view this page or perform this action.') }}</p>
        @php($requestId = \Illuminate\Support\Facades\Context::get('request_id') ?? request()->header('X-Request-ID') ?? 'REQ-UNAVAILABLE')
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md bg-zinc-100 dark:bg-zinc-700/50 text-xs font-mono text-zinc-600 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-600 max-w-full truncate"><span class="text-zinc-400 dark:text-zinc-500 select-none">ID:</span><span class="truncate">{{ $requestId }}</span></div>
        <div class="pt-2"><a href="{{ route('dashboard') }}" class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-lg bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 hover:bg-zinc-800 dark:hover:bg-white transition-colors">{{ $isArabic ? 'العودة إلى لوحة التحكم' : 'Return to dashboard' }}</a></div>
    </div>
</body>
</html>
