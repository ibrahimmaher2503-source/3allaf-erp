<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = config('app.supported_locales', ['ar', 'en', 'ar-EG']);
        // The global invocation runs before the session middleware. A locale
        // cookie keeps safe exception pages localized even when no route
        // matched; the web-group invocation below still gives the session
        // value precedence on ordinary application requests.
        $locale = $request->hasSession() ? $request->session()->get('locale') : null;
        $locale ??= $request->cookie('locale');

        if ($locale && in_array($locale, $supportedLocales, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
