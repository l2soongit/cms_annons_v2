<?php

namespace App\Monitoring\Drivers;

use App\Monitoring\Contracts\ServerQueryDriver;
use App\Monitoring\QueryResult;
use Illuminate\Support\Facades\Http;

/**
 * FiveM (GTA V): HTTP-ендпоінт /dynamic.json на порту сервера.
 */
class FiveMDriver implements ServerQueryDriver
{
    public function query(string $host, int $port, float $timeout): QueryResult
    {
        try {
            $start = hrtime(true);
            $response = Http::timeout($timeout)->connectTimeout($timeout)->get("http://{$host}:{$port}/dynamic.json");

            if (! $response->ok()) {
                return QueryResult::offline();
            }

            return new QueryResult(
                true,
                (int) $response->json('clients', 0),
                (int) $response->json('sv_maxclients', 0),
                $response->json('hostname'),
                (int) ((hrtime(true) - $start) / 1e6),
            );
        } catch (\Throwable) {
            return QueryResult::offline();
        }
    }
}
