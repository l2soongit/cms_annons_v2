<?php

namespace App\Console\Commands;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Models\ServerCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Щогодини: зводить сирі перевірки в погодинну статистику, рахує аптайм за 30 днів,
 * чистить старі дані й архівує сервери, що довго офлайн.
 */
class AggregateServerStats extends Command
{
    protected $signature = 'servers:aggregate {--hour= : Година для зведення, Y-m-d H:00 (за замовчуванням попередня)}';

    protected $description = 'Погодинна статистика онлайну, аптайм, очищення та архівація';

    public function handle(): int
    {
        $hour = $this->option('hour')
            ? Carbon::parse($this->option('hour'))->startOfHour()
            : now()->subHour()->startOfHour();

        $this->aggregateHour($hour);
        $this->refreshUptime();
        $this->prune();
        $this->archiveDeadServers();

        return self::SUCCESS;
    }

    private function aggregateHour(Carbon $hour): void
    {
        $rows = ServerCheck::query()
            ->whereBetween('checked_at', [$hour, $hour->copy()->endOfHour()])
            ->groupBy('server_id')
            ->select('server_id')
            ->selectRaw('AVG(players) as players_avg, MAX(players) as players_max')
            ->selectRaw('100.0 * SUM(CASE WHEN is_online THEN 1 ELSE 0 END) / COUNT(*) as uptime')
            ->get();

        $votes = DB::table('votes')
            ->whereBetween('created_at', [$hour, $hour->copy()->endOfHour()])
            ->where('status', 'counted')
            ->groupBy('server_id')
            ->pluck(DB::raw('COUNT(*)'), 'server_id');

        foreach ($rows as $row) {
            DB::table('server_stats_hourly')->upsert([
                'server_id' => $row->server_id,
                'hour' => $hour->toDateTimeString(),
                'players_avg' => (int) round($row->players_avg),
                'players_max' => (int) $row->players_max,
                'uptime' => round($row->uptime, 2),
                'votes' => (int) ($votes[$row->server_id] ?? 0),
            ], ['server_id', 'hour'], ['players_avg', 'players_max', 'uptime', 'votes']);
        }

        $this->info("Зведено {$rows->count()} серверів за {$hour->format('Y-m-d H:00')}");
    }

    private function refreshUptime(): void
    {
        $uptimes = DB::table('server_stats_hourly')
            ->where('hour', '>=', now()->subDays(30))
            ->groupBy('server_id')
            ->pluck(DB::raw('AVG(uptime)'), 'server_id');

        foreach ($uptimes as $serverId => $uptime) {
            DB::table('servers')->where('id', $serverId)->update(['uptime' => round($uptime, 2)]);
        }
    }

    private function prune(): void
    {
        ServerCheck::where('checked_at', '<', now()->subDays(config('graj.monitoring.raw_retention_days')))->delete();
        DB::table('server_stats_hourly')->where('hour', '<', now()->subDays(config('graj.monitoring.hourly_retention_days')))->delete();
    }

    private function archiveDeadServers(): void
    {
        $archived = Server::where('status', ServerStatus::Published)
            ->where('offline_since', '<', now()->subDays(config('graj.monitoring.archive_after_offline_days')))
            ->update(['status' => ServerStatus::Archived]);

        if ($archived) {
            $this->info("Архівовано серверів: {$archived}");
        }
    }
}
