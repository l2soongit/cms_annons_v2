<?php

namespace App\Monitoring\Drivers;

use App\Monitoring\Contracts\ServerQueryDriver;
use App\Monitoring\QueryResult;

/**
 * Minecraft Java Edition: Server List Ping (handshake + status request).
 */
class MinecraftDriver implements ServerQueryDriver
{
    public function query(string $host, int $port, float $timeout): QueryResult
    {
        $start = hrtime(true);
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (! $socket) {
            return QueryResult::offline();
        }

        stream_set_timeout($socket, (int) ceil($timeout));

        try {
            $handshake = "\x00".self::varInt(767).self::string($host).pack('n', $port).self::varInt(1);
            fwrite($socket, self::varInt(strlen($handshake)).$handshake);
            fwrite($socket, "\x01\x00");

            self::readVarInt($socket);                  // довжина пакета
            if (self::readVarInt($socket) !== 0) {      // id пакета
                return new QueryResult(true);
            }

            $length = self::readVarInt($socket);
            $json = '';
            while (strlen($json) < $length && ! feof($socket)) {
                $chunk = fread($socket, $length - strlen($json));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $json .= $chunk;
            }

            $data = json_decode($json, true) ?: [];
            $motd = $data['description'] ?? null;

            return new QueryResult(
                true,
                (int) ($data['players']['online'] ?? 0),
                (int) ($data['players']['max'] ?? 0),
                is_array($motd) ? self::flattenChat($motd) : $motd,
                (int) ((hrtime(true) - $start) / 1e6),
            );
        } catch (\Throwable) {
            return new QueryResult(true);
        } finally {
            fclose($socket);
        }
    }

    private static function varInt(int $value): string
    {
        $out = '';
        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            $out .= chr($value ? $byte | 0x80 : $byte);
        } while ($value);

        return $out;
    }

    private static function string(string $value): string
    {
        return self::varInt(strlen($value)).$value;
    }

    private static function readVarInt($socket): int
    {
        $value = 0;
        for ($i = 0; $i < 5; $i++) {
            $char = fgetc($socket);
            if ($char === false) {
                throw new \RuntimeException('Unexpected end of stream');
            }
            $byte = ord($char);
            $value |= ($byte & 0x7F) << (7 * $i);
            if (! ($byte & 0x80)) {
                return $value;
            }
        }

        throw new \RuntimeException('VarInt too long');
    }

    private static function flattenChat(array $component): string
    {
        $text = $component['text'] ?? '';
        foreach ($component['extra'] ?? [] as $part) {
            $text .= is_array($part) ? self::flattenChat($part) : $part;
        }

        return $text;
    }
}
