<?php

namespace App\Monitoring\Drivers;

use App\Monitoring\Contracts\ServerQueryDriver;
use App\Monitoring\QueryResult;

/**
 * Steam A2S_INFO (Counter-Strike, Rust, DayZ та інші Source/Steam-сервери).
 */
class SourceDriver implements ServerQueryDriver
{
    private const REQUEST = "\xFF\xFF\xFF\xFFTSource Engine Query\x00";

    public function query(string $host, int $port, float $timeout): QueryResult
    {
        $socket = UdpSocket::open($host, $port, $timeout);
        if (! $socket) {
            return QueryResult::offline();
        }

        $start = hrtime(true);
        $response = UdpSocket::exchange($socket, self::REQUEST);

        // Нові сервери спершу вимагають challenge (0x41)
        if ($response !== null && strlen($response) >= 9 && $response[4] === "\x41") {
            $response = UdpSocket::exchange($socket, self::REQUEST.substr($response, 5, 4));
        }

        fclose($socket);

        if ($response === null || strlen($response) < 6) {
            return QueryResult::offline();
        }

        $ping = (int) ((hrtime(true) - $start) / 1e6);

        return match ($response[4]) {
            "\x49" => $this->parseSource($response, $ping),
            "\x6D" => $this->parseGoldSource($response, $ping),
            default => new QueryResult(true, pingMs: $ping),
        };
    }

    private function parseSource(string $data, int $ping): QueryResult
    {
        $offset = 6; // заголовок + протокол
        $name = self::readString($data, $offset);
        self::readString($data, $offset); // map
        self::readString($data, $offset); // folder
        self::readString($data, $offset); // game
        $offset += 2; // app id

        return new QueryResult(true, ord($data[$offset] ?? "\0"), ord($data[$offset + 1] ?? "\0"), $name, $ping);
    }

    private function parseGoldSource(string $data, int $ping): QueryResult
    {
        $offset = 5;
        self::readString($data, $offset); // address
        $name = self::readString($data, $offset);
        self::readString($data, $offset); // map
        self::readString($data, $offset); // folder
        self::readString($data, $offset); // game

        return new QueryResult(true, ord($data[$offset] ?? "\0"), ord($data[$offset + 1] ?? "\0"), $name, $ping);
    }

    private static function readString(string $data, int &$offset): string
    {
        $end = strpos($data, "\0", $offset);
        if ($end === false) {
            $end = strlen($data);
        }
        $value = substr($data, $offset, $end - $offset);
        $offset = $end + 1;

        return $value;
    }
}
