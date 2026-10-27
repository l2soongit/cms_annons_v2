<?php

namespace App\Monitoring;

use App\Models\Server;
use App\Models\ServerCheck;
use App\Support\PublicHost;

class ServerMonitor
{
    public function __construct(private DriverRegistry $drivers) {}

    public function check(Server $server): QueryResult
    {
        $port = $server->port ?: $server->game->default_port ?: 0;

        $result = PublicHost::isPublic($server->host)
            ? $this->drivers->for($server)->query($server->host, (int) $port, config('graj.monitoring.timeout_seconds'))
            : QueryResult::offline();

        $this->record($server, $result);

        return $result;
    }

    /**
     * Записати результат опитування (або push від власника через API).
     */
    public function record(Server $server, QueryResult $result): void
    {
        $now = now();

        ServerCheck::create([
            'server_id' => $server->id,
            'is_online' => $result->online,
            'players' => $result->players,
            'max_players' => $result->maxPlayers,
            'ping_ms' => $result->pingMs,
            'checked_at' => $now,
        ]);

        $server->forceFill([
            'is_online' => $result->online,
            'players' => $result->online ? $result->players : 0,
            'max_players' => $result->maxPlayers ?: $server->max_players,
            'checked_at' => $now,
            'offline_since' => $result->online ? null : ($server->offline_since ?? $now),
            'suspicious_online' => $this->isSuspicious($server, $result),
        ])->saveQuietly();
    }

    /**
     * Ознаки накрученого онлайну: гравців більше за слоти або різкий стрибок проти середнього за добу.
     */
    private function isSuspicious(Server $server, QueryResult $result): bool
    {
        if (! $result->online || $result->players === 0) {
            return false;
        }

        if ($result->maxPlayers > 0 && $result->players > $result->maxPlayers) {
            return true;
        }

        $dayAverage = (float) $server->checks()
            ->where('checked_at', '>=', now()->subDay())
            ->where('is_online', true)
            ->avg('players');

        return $dayAverage >= 10 && $result->players > $dayAverage * 5;
    }
}
