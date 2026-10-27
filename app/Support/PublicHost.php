<?php

namespace App\Support;

/**
 * Захист від SSRF: портал робить запити на адреси, які вказують власники
 * (моніторинг, callback, перевірка сайту). Дозволені лише публічні IP.
 */
final class PublicHost
{
    public static function isPublic(string $host): bool
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isPublicIp($host);
        }

        if (! preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $host)) {
            return false;
        }

        $ips = @gethostbynamel($host) ?: [];

        return $ips !== [] && collect($ips)->every(fn ($ip) => self::isPublicIp($ip));
    }

    public static function isPublicUrl(?string $url): bool
    {
        $parts = parse_url((string) $url);

        return in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            && isset($parts['host'])
            && self::isPublic($parts['host']);
    }

    private static function isPublicIp(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
