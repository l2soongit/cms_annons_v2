<?php

namespace App\Monitoring\Drivers;

use App\Monitoring\Contracts\ServerQueryDriver;
use App\Monitoring\QueryResult;

/**
 * Перевірка відкритого TCP-порту. Для ігор без query-протоколу (Lineage 2):
 * знає лише онлайн/офлайн, кількість гравців надсилає власник через API.
 */
class TcpDriver implements ServerQueryDriver
{
    public function query(string $host, int $port, float $timeout): QueryResult
    {
        $start = hrtime(true);
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (! $socket) {
            return QueryResult::offline();
        }

        fclose($socket);

        return new QueryResult(true, pingMs: (int) ((hrtime(true) - $start) / 1e6));
    }
}
