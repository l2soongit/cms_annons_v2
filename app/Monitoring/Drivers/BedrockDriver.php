<?php

namespace App\Monitoring\Drivers;

use App\Monitoring\Contracts\ServerQueryDriver;
use App\Monitoring\QueryResult;
use App\Support\Motd;

/**
 * Minecraft Bedrock Edition: RakNet Unconnected Ping по UDP.
 */
class BedrockDriver implements ServerQueryDriver
{
    private const MAGIC = "\x00\xff\xff\x00\xfe\xfe\xfe\xfe\xfd\xfd\xfd\xfd\x12\x34\x56\x78";

    public function query(string $host, int $port, float $timeout): QueryResult
    {
        $socket = UdpSocket::open($host, $port, $timeout);
        if (! $socket) {
            return QueryResult::offline();
        }

        $start = hrtime(true);
        $response = UdpSocket::exchange($socket, "\x01".pack('J', time()).self::MAGIC.pack('J', 0));
        fclose($socket);

        if ($response === null || $response[0] !== "\x1c") {
            return QueryResult::offline();
        }

        // MCPE;MOTD;protocol;version;online;max;...
        $parts = explode(';', substr($response, 35));
        $motd = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null;

        return new QueryResult(
            true,
            (int) ($parts[4] ?? 0),
            (int) ($parts[5] ?? 0),
            $motd === null ? null : Motd::plain($motd),
            (int) ((hrtime(true) - $start) / 1e6),
            motd: $motd === null ? null : Motd::clip($motd),
        );
    }
}
