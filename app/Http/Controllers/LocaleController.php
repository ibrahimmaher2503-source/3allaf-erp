<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class LocaleController extends Controller
{
    private const SUPPORTED_LOCALES = ['ar', 'en', 'ar-EG'];

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(self::SUPPORTED_LOCALES)],
        ]);

        $request->session()->put('locale', $validated['locale']);

        return back()->withCookie(cookie('locale', $validated['locale'], 60 * 24 * 365));
    }
}
