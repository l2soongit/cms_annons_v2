<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ТЗ: 2FA обов'язкова для модераторів і адміністраторів. Без неї адмінка недоступна.
 */
class RequireStaffTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (config('graj.security.require_staff_2fa') && $user?->isStaff() && ! $user->two_factor_confirmed_at) {
            return redirect()->route('cabinet.security')->with('error', __('profile.security.staff_required'));
        }

        return $next($request);
    }
}
