<?php

namespace App\Http\Controllers;

use App\Analytics\Tracker;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Приймає події з браузера (navigator.sendBeacon): перехід на сайт сервера, Discord, копіювання IP, кнопка «Голосувати».
 */
class AnalyticsController extends Controller
{
    public function event(Request $request, Tracker $tracker): Response
    {
        $name = (string) $request->input('n');
        if (in_array($name, Tracker::CLIENT_EVENTS, true)) {
            // Сервер — id (сторінка сервера) або slug (посилання «Голосувати» у списках)
            $key = (string) $request->input('s');
            $server = match (true) {
                ctype_digit($key) => Server::query()->whereKey((int) $key)->first(['id', 'game_id']),
                (bool) preg_match('/^[a-z0-9-]{1,191}$/', $key) => Server::query()->where('slug', $key)->first(['id', 'game_id']),
                default => null,
            };
            $label = $name === 'outbound' || $name === 'share'
                ? (parse_url((string) $request->input('l'), PHP_URL_HOST) ?: (string) $request->input('l'))
                : null;
            $tracker->event($request, $name, $server, $server?->game_id, $label ? preg_replace('/^www\./', '', $label) : null);
        }

        return response()->noContent();
    }
}
