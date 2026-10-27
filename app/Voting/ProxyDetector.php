<?php

namespace App\Voting;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Виявлення VPN / проксі / дата-центрів. Без IPQS_KEY перевірка вимкнена (пропускає всіх).
 * Результат кешується на добу, щоб не витрачати ліміт сервісу.
 */
class ProxyDetector
{
    public function isProxy(string $ip): bool
    {
        $key = config('services.ipqs.key');

        if (! $key || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        return Cache::remember('proxy:'.hash('sha256', $ip), now()->addDay(), function () use ($key, $ip) {
            try {
                $data = Http::timeout(3)
                    ->get("https://ipqualityscore.com/api/json/ip/{$key}/{$ip}", ['strictness' => 1])
                    ->json();

                return (bool) (($data['vpn'] ?? false) || ($data['proxy'] ?? false) || ($data['tor'] ?? false));
            } catch (\Throwable) {
                return false; // сервіс недоступний — не блокуємо голос
            }
        });
    }
}
