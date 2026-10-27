<?php

namespace App\Monitoring\Drivers;

use App\Monitoring\Contracts\ServerQueryDriver;
use App\Monitoring\QueryResult;

/**
 * SA-MP / open.mp query, пакет 'i' (info).
 */
class SampDriver implements ServerQueryDriver
{
    public function query(string $host, int $port, float $timeout): QueryResult
    {
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return QueryResult::offline();
        }

        $socket = UdpSocket::open($ip, $port, $timeout);
        if (! $socket) {
            return QueryResult::offline();
        }

        $packet = 'SAMP';
        foreach (explode('.', $ip) as $octet) {
            $packet .= chr((int) $octet);
        }
        $packet .= pack('v', $port).'i';

        $start = hrtime(true);
        $response = UdpSocket::exchange($socket, $packet);
        fclose($socket);

        if ($response === null || strlen($response) < 16) {
            return QueryResult::offline();
        }

        $offset = 11 + 1; // заголовок + пароль
        $players = unpack('v', substr($response, $offset, 2))[1];
        $max = unpack('v', substr($response, $offset + 2, 2))[1];
        $nameLength = unpack('V', substr($response, $offset + 4, 4))[1];
        $name = substr($response, $offset + 8, $nameLength);

        return new QueryResult(true, $players, $max, mb_convert_encoding($name, 'UTF-8', 'Windows-1251'), (int) ((hrtime(true) - $start) / 1e6));
    }
}
