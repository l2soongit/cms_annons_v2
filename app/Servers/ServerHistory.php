<?php

namespace App\Servers;

use App\Models\Server;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Точки для графіків онлайну й голосів з погодинної статистики.
 * Доба і тиждень — по годинах, місяць — по днях (групування в PHP, щоб не залежати від СУБД).
 */
class ServerHistory
{
    public const PERIODS = ['day' => 1, 'week' => 7, 'month' => 30];

    /**
     * @return Collection<int, array{label: string, players: int, votes: int}>
     */
    public function points(Server $server, string $period): Collection
    {
        $days = self::PERIODS[$period] ?? 7;

        $rows = DB::table('server_stats_hourly')
            ->where('server_id', $server->id)
            ->where('hour', '>=', now()->subDays($days)->startOfHour())
            ->orderBy('hour')
            ->get(['hour', 'players_avg', 'votes']);

        if ($period !== 'month') {
            return $rows->map(fn ($r) => [
                'label' => Carbon::parse($r->hour)->format($period === 'day' ? 'H:00' : 'd.m H:00'),
                'players' => (int) $r->players_avg,
                'votes' => (int) $r->votes,
            ]);
        }

        return $rows
            ->groupBy(fn ($r) => Carbon::parse($r->hour)->toDateString())
            ->map(fn ($day, $date) => [
                'label' => Carbon::parse($date)->format('d.m'),
                'players' => (int) round($day->avg('players_avg')),
                'votes' => (int) $day->sum('votes'),
            ])
            ->values();
    }
}
