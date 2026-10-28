<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Мова, якою користувач переглядає сайт, — мова його листів і сповіщень.
 */
class RememberUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->locale !== app()->getLocale() && ! $request->is('admin', 'admin/*', 'livewire/*')) {
            $user->forceFill(['locale' => app()->getLocale()])->saveQuietly();
        }

        return $next($request);
    }
}
