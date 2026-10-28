<?php

namespace App\Monitoring;

/**
 * Ключові слова Steam A2S, які DayZ передає про свій світ:
 * «battleye,no3rd,external,privHive,shard,lqs3,etm4.000000,entm8.000000,mod,14:20».
 * lqs — черга входу, etm — прискорення часу вдень, останнє «гг:хх» — поточний ігровий час.
 */
final class SteamKeywords
{
    /** @return array{queue: ?int, time: ?string, accel: ?float} */
    public static function parse(?string $keywords): array
    {
        $result = ['queue' => null, 'time' => null, 'accel' => null];
        if ($keywords === null || $keywords === '') {
            return $result;
        }

        foreach (explode(',', $keywords) as $word) {
            $word = trim($word);
            if (preg_match('/^lqs(\d{1,4})$/', $word, $m)) {
                $result['queue'] = (int) $m[1];
            } elseif (preg_match('/^etm(\d+(?:\.\d+)?)$/', $word, $m)) {
                $result['accel'] = round(min(64, max(0, (float) $m[1])), 2);
            } elseif (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $word, $m)) {
                $result['time'] = sprintf('%02d:%s', $m[1], $m[2]);
            }
        }

        return $result;
    }
}
