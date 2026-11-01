<?php

namespace App\Support;

use App\Enums\ServerStatus;
use App\Models\Game;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Ігри для перемикача в шапці: кольори теми, кількість серверів і гравців онлайн (кеш на 5 хвилин).
 */
final class GameNav
{
    /** Кольори тем: [основний, глибокий] — ті самі, що на картках головної */
    public const COLORS = [
        'l2' => ['#e8b04a', '#7a3e0b'], 'gta' => ['#ff4fa3', '#6d28d9'], 'mc' => ['#6cc644', '#8b5a2b'],
        'dayz' => ['#b8c48a', '#2f3a22'], 'rust' => ['#e2652f', '#5a2412'], 'cs' => ['#4fc3f7', '#0b2a4a'],
        'wow' => ['#7c9cff', '#1e1b4b'], 'pw' => ['#2dd4bf', '#134e4a'], 'aion' => ['#c4a7ff', '#3b1d6e'],
        'mu' => ['#ff4d5e', '#4c0519'], 'default' => ['#fbbf24', '#7c2d12'],
    ];

    /** @return list<array{slug: string, name: string, short: string, url: string, c1: string, c2: string, servers: int, online: int, icon: ?string}> */
    public static function items(): array
    {
        $stats = Cache::remember('nav:game-stats', 300, fn () => DB::table('servers')
            ->where('status', ServerStatus::Published->value)->whereNull('deleted_at')
            ->groupBy('game_id')
            ->selectRaw('game_id, COUNT(*) as servers, SUM(CASE WHEN is_online THEN players ELSE 0 END) as online')
            ->get()->keyBy('game_id')->map(fn ($r) => [(int) $r->servers, (int) $r->online])->all());

        return once(fn () => Game::active()->get(['id', 'slug', 'name', 'theme', 'icon'])->map(function (Game $game) use ($stats) {
            [$c1, $c2] = self::COLORS[$game->themeKey()];

            return [
                'slug' => $game->slug,
                'name' => $game->name,
                'short' => $game->shortName(),
                'url' => route('games.show', $game),
                'c1' => $c1,
                'c2' => $c2,
                'servers' => $stats[$game->id][0] ?? 0,
                'online' => $stats[$game->id][1] ?? 0,
                // Значок з адмінки, інакше — вбудований логотип гри (public/images/games/<тема>.png)
                'icon' => HomePageData::url($game->icon)
                    ?? (is_file(public_path("images/games/{$game->themeKey()}.png")) ? asset("images/games/{$game->themeKey()}.png") : null),
            ];
        })->all());
    }
}
