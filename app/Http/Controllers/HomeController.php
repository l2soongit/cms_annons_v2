<?php

namespace App\Http\Controllers;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\Vote;
use App\Support\HomePageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** За скільки хвилин показувати голоси одразу після відкриття сторінки */
    private const FEED_BACKLOG_MINUTES = 10;

    public function __invoke(): View
    {
        return view('home', [
            'data' => HomePageData::get(),
            'feedAfter' => (int) Vote::where('created_at', '<', now()->subMinutes(self::FEED_BACKLOG_MINUTES))->max('id'),
        ]);
    }

    /**
     * Живі події для головної: нові зараховані голоси після ?after=id і загальні лічильники.
     */
    public function live(Request $request): JsonResponse
    {
        $after = max(0, (int) $request->query('after'));
        // Сторінка гри отримує лише голоси й онлайн своєї гри
        $gameId = $request->filled('game') ? Game::where('slug', $request->string('game'))->value('id') : null;
        $scope = fn ($q) => $q->where('status', ServerStatus::Published)->when($gameId, fn ($q) => $q->where('game_id', $gameId));

        $votes = Vote::where('id', '>', $after)->where('status', 'counted')
            ->whereHas('server', $scope)
            ->with('server.game')
            ->latest('id')->limit(10)->get()
            ->reverse()->values();

        return response()->json([
            'last' => $votes->max('id') ?? $after,
            'votes' => $votes->map(fn (Vote $v) => [
                'id' => $v->id,
                'nick' => $v->nickname ?: null,
                'server' => $v->server->name,
                'url' => route('servers.show', $v->server),
                'theme' => $v->server->game->themeKey(),
            ]),
            // Актуальні голоси місяця для серверів, що отримали голос, — оновлюють цифри в топі
            'servers' => $votes->pluck('server')->unique('id')->mapWithKeys(fn (Server $s) => [$s->id => (int) $s->votes_month]),
            'online' => (int) Server::published()->where('is_online', true)->when($gameId, fn ($q) => $q->where('game_id', $gameId))->sum('players'),
        ]);
    }
}
