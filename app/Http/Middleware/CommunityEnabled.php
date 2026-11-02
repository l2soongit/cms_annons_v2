<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Розділ «Спільнота» вимкнено в адмінці — сторінки відповідають 404 (персоналу доступні для перевірки) */
class CommunityEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('graj.community.enabled', true) || $request->user()?->isStaff(), 404);

        return $next($request);
    }
}
