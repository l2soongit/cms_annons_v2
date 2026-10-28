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
        $map = self::readString($data, $offset);
        self::readString($data, $offset); // folder
        self::readString($data, $offset); // game
        $offset += 2; // app id

        // players, max players, bots, server type, environment, visibility, VAC
        $vac = isset($data[$offset + 6]) ? ord($data[$offset + 6]) === 1 : null;

        return new QueryResult(true, ord($data[$offset] ?? "\0"), ord($data[$offset + 1] ?? "\0"), $name, $ping, $map ?: null, $vac,
            keywords: self::keywords($data, $offset + 7));
    }

    /**
     * Ключові слова з розширеного блоку відповіді (Extra Data Flag): після версії йде байт прапорців,
     * далі — порт, SteamID, SourceTV, і лише тоді рядок ключових слів (прапорець 0x20).
     */
    private static function keywords(string $data, int $offset): ?string
    {
        self::readString($data, $offset); // version
        if (! isset($data[$offset])) {
            return null;
        }
        $edf = ord($data[$offset++]);
        if ($edf & 0x80) {
            $offset += 2; // game port
        }
        if ($edf & 0x10) {
            $offset += 8; // SteamID
        }
        if ($edf & 0x40) {
            $offset += 2; // SourceTV port
            self::readString($data, $offset); // SourceTV name
        }
        if (! ($edf & 0x20) || ! isset($data[$offset])) {
            return null;
        }

        return self::readString($data, $offset) ?: null;
    }

    private function parseGoldSource(string $data, int $ping): QueryResult
    {
        $offset = 5;
        self::readString($data, $offset); // address
        $name = self::readString($data, $offset);
        $map = self::readString($data, $offset);
        self::readString($data, $offset); // folder
        self::readString($data, $offset); // game

        $players = ord($data[$offset] ?? "\0");
        $max = ord($data[$offset + 1] ?? "\0");

        // protocol, server type, environment, visibility, mod; для модів — ще блок з описом, потім VAC
        $cursor = $offset + 6;
        if (ord($data[$cursor] ?? "\0") === 1) {
            $cursor++;
            self::readString($data, $cursor); // link
            self::readString($data, $cursor); // download link
            $cursor += 1 + 4 + 4 + 1 + 1; // null, version, size, type, dll
        } else {
            $cursor++;
        }
        $vac = isset($data[$cursor]) ? ord($data[$cursor]) === 1 : null;

        return new QueryResult(true, $players, $max, $name, $ping, $map ?: null, $vac);
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
