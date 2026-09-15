<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

final class PosAwareLoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $fallback = '/dashboard';
        $user = $request->user();

        if ($user !== null
            && ! $user->can('dashboard_reports.view')
            && $user->can('pos_sales.view')) {
            $fallback = route('pos');
        } elseif ($user !== null
            && ! $user->can('dashboard_reports.view')
            && $user->can('party_bookings_invoices.view')) {
            $fallback = route('parties.bookings.index');
        }

        return redirect()->intended($fallback);
    }
}
