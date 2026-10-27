<?php

namespace App\Console\Commands;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Monitoring\ServerMonitor;
use Illuminate\Console\Command;

/**
 * Опитує сервери, у яких минув інтервал перевірки. Запускається cron щохвилини;
 * обмеження за часом не дає задачі вийти за хвилину на віртуальному хостингу.
 */
class MonitorServers extends Command
{
    protected $signature = 'servers:monitor {--server= : ID одного сервера} {--budget=50 : Ліміт часу, секунди}';

    protected $description = 'Опитати ігрові сервери і оновити статус та онлайн';

    public function handle(ServerMonitor $monitor): int
    {
        $deadline = microtime(true) + (int) $this->option('budget');

        $query = Server::with('game')
            ->whereIn('status', [ServerStatus::Published, ServerStatus::Pending])
            ->orderByRaw('checked_at IS NOT NULL')
            ->orderBy('checked_at');

        if ($id = $this->option('server')) {
            $query->whereKey($id);
        } else {
            $query->where(fn ($q) => $q
                ->whereNull('checked_at')
                ->orWhere('checked_at', '<=', now()->subMinutes(config('graj.monitoring.interval_minutes'))));
        }

        $checked = 0;
        foreach ($query->limit(config('graj.monitoring.batch_size'))->cursor() as $server) {
            if (microtime(true) >= $deadline) {
                break;
            }

            $result = $monitor->check($server);
            $checked++;

            if ($this->output->isVerbose()) {
                $this->line(sprintf('%s %s: %s %d/%d', $server->id, $server->address(), $result->online ? 'online' : 'offline', $result->players, $result->maxPlayers));
            }
        }

        $this->info("Перевірено серверів: {$checked}");

        return self::SUCCESS;
    }
}
