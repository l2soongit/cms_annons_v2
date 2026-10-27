<?php

namespace App\Rating;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\MonthlyWinner;
use App\Models\Server;
use Illuminate\Support\Facades\DB;

class RatingCalculator
{
    /**
     * Перерахувати голоси місяця, бали та місця для всіх ігор.
     */
    public function recalculate(): void
    {
        $monthStart = now()->startOfMonth();

        $votes = DB::table('votes')
            ->where('created_at', '>=', $monthStart)
            ->where('status', 'counted')
            ->groupBy('server_id')
            ->pluck(DB::raw('COUNT(*)'), 'server_id');

        $bonus = DB::table('orders')
            ->join('tariffs', 'tariffs.id', '=', 'orders.tariff_id')
            ->where('tariffs.service', 'boost')
            ->where('orders.status', 'active')
            ->where('orders.starts_at', '>=', $monthStart)
            ->groupBy('orders.server_id')
            ->pluck(DB::raw('SUM(tariffs.points)'), 'orders.server_id');

        $w = config('graj.rating.weights');

        Game::each(function (Game $game) use ($votes, $bonus, $w) {
            $servers = $game->servers()->where('status', ServerStatus::Published)->get();

            foreach ($servers as $server) {
                $server->votes_month = (int) ($votes[$server->id] ?? 0);
                $server->bonus_points = (int) ($bonus[$server->id] ?? 0);
                $server->score = (int) round(
                    $server->votes_month * $w['votes']
                    + $server->bonus_points * $w['bonus']
                    + ($server->suspicious_online ? 0 : $server->players) * $w['online']
                    + (float) $server->uptime * $w['uptime']
                );
            }

            $rank = 1;
            foreach ($servers->sortBy([['score', 'desc'], ['votes_month', 'desc'], ['id', 'asc']]) as $server) {
                $server->rank = $rank++;
                $server->saveQuietly();
            }

            $game->servers()->where('status', '!=', ServerStatus::Published)->update(['rank' => null]);
        });
    }

    /**
     * Перший день місяця: зберегти топ-3 минулого місяця, потім обнулити рейтинг.
     */
    public function closeMonth(): void
    {
        $month = now()->subMonth()->startOfMonth();

        Game::each(function (Game $game) use ($month) {
            $top = Server::where('game_id', $game->id)
                ->where('status', ServerStatus::Published)
                ->orderByDesc('score')
                ->orderByDesc('votes_month')
                ->limit(3)
                ->get();

            foreach ($top as $i => $server) {
                MonthlyWinner::updateOrCreate(
                    ['game_id' => $game->id, 'month' => $month->toDateString(), 'place' => $i + 1],
                    ['server_id' => $server->id, 'server_name' => $server->name, 'votes' => $server->votes_month],
                );
            }
        });

        $this->recalculate();
    }
}
