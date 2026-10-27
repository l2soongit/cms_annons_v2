<?php

namespace App\Monitoring\Drivers;

/**
 * Мінімальна обгортка над UDP через stream-функції (розширення sockets не обов'язкове).
 */
final class UdpSocket
{
    /** @return resource|null */
    public static function open(string $host, int $port, float $timeout)
    {
        $socket = @stream_socket_client("udp://{$host}:{$port}", $errno, $errstr, $timeout);
        if (! $socket) {
            return null;
        }

        stream_set_timeout($socket, (int) $timeout, (int) (fmod($timeout, 1) * 1e6));

        return $socket;
    }

    /** @param resource $socket */
    public static function exchange($socket, string $payload, int $maxLength = 4096): ?string
    {
        if (@fwrite($socket, $payload) === false) {
            return null;
        }

        $response = @fread($socket, $maxLength);

        if ($response === false || $response === '' || stream_get_meta_data($socket)['timed_out']) {
            return null;
        }

        return $response;
    }
}
