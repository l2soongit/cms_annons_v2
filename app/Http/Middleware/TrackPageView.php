<?php

namespace App\Http\Middleware;

use App\Analytics\Tracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Перегляд сторінки пишеться вже після відправки відповіді (terminate), тож відвідувач не чекає на запис.
 * Рахується на сервері — блокувальники реклами статистику не обрізають.
 */
class TrackPageView
{
    public function __construct(private Tracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (Tracker::enabled($request) && Tracker::shouldTrack($request, $response)) {
            $this->tracker->view($request);
        }
    }
}
