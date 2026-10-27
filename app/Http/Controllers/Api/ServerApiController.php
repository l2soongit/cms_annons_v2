<?php

namespace App\Http\Controllers\Api;

use App\Enums\ServerStatus;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Server;
use App\Monitoring\QueryResult;
use App\Monitoring\ServerMonitor;
use App\Voting\VoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerApiController extends Controller
{
    public function show(Server $server): JsonResponse
    {
        abort_unless($server->status === ServerStatus::Published, 404);

        return response()->json(['data' => self::present($server)]);
    }

    public function top(Game $game): JsonResponse
    {
        abort_unless($game->is_active, 404);

        $servers = $game->servers()->ranked()->limit(100)->get();

        return response()->json(['data' => $servers->map(fn (Server $s) => self::present($s))]);
    }

    public function votes(Request $request, Server $server): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $votes = $server->votes()
            ->where('status', 'counted')
            ->when($data['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($data['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->latest('created_at')
            ->limit(1000)
            ->get(['id', 'nickname', 'created_at']);

        return response()->json(['data' => $votes]);
    }

    /**
     * Pull-режим для збірок без callback: чи голосував нік або IP за останній інтервал.
     */
    public function checkVote(Request $request, Server $server): JsonResponse
    {
        $data = $request->validate([
            'nickname' => ['required_without:ip', 'nullable', 'string', 'max:32'],
            'ip' => ['required_without:nickname', 'nullable', 'ip'],
        ]);

        $voted = $server->votes()
            ->where('status', 'counted')
            ->where('created_at', '>', now()->subHours(config('graj.voting.interval_hours')))
            ->where(fn ($q) => $q
                ->when($data['nickname'] ?? null, fn ($q, $nick) => $q->orWhere('nickname', $nick))
                ->when($data['ip'] ?? null, fn ($q, $ip) => $q->orWhere('ip_hash', VoteService::hashIp($ip))))
            ->latest('created_at')
            ->first(['id', 'created_at']);

        return response()->json([
            'voted' => (bool) $voted,
            'vote_id' => $voted?->id,
            'voted_at' => $voted?->created_at?->toIso8601String(),
        ]);
    }

    /**
     * Push онлайну від сервера (Lineage 2 та інші ігри без query-протоколу).
     */
    public function pushOnline(Request $request, Server $server, ServerMonitor $monitor): JsonResponse
    {
        $data = $request->validate([
            'online' => ['required', 'boolean'],
            'players' => ['required', 'integer', 'min:0', 'max:100000'],
            'max_players' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $monitor->record($server, new QueryResult(
            (bool) $data['online'],
            (int) $data['players'],
            (int) ($data['max_players'] ?? $server->max_players),
        ));

        return response()->json(['ok' => true]);
    }

    private static function present(Server $server): array
    {
        return [
            'id' => $server->id,
            'name' => $server->name,
            'url' => route('servers.show', $server),
            'address' => $server->address(),
            'online' => $server->is_online,
            'players' => $server->players,
            'max_players' => $server->max_players,
            'uptime' => (float) $server->uptime,
            'votes_month' => $server->votes_month,
            'rank' => $server->rank,
            'checked_at' => $server->checked_at?->toIso8601String(),
        ];
    }
}
