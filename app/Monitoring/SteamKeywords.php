<?php

namespace App\Monitoring;

use Illuminate\Support\Carbon;

/**
 * Ключові слова Steam A2S, у яких сервери передають стан свого світу.
 * DayZ: «battleye,no3rd,external,privHive,shard,lqs3,etm4.000000,entm8.000000,mod,14:20» —
 * lqs — черга входу, etm — прискорення часу вдень, останнє «гг:хх» — поточний ігровий час.
 * Rust: «mp200,cp150,qp12,v2591,born1730389200,gm_rust,oxide,modded» — qp — черга, born — момент вайпу,
 * oxide / modded — плагіни; без них Rust-сервер вважаємо ваніллою.
 */
final class SteamKeywords
{
    /** @return array{queue: ?int, time: ?string, accel: ?float, wiped: ?Carbon, modded: ?bool} */
    public static function parse(?string $keywords): array
    {
        $result = ['queue' => null, 'time' => null, 'accel' => null, 'wiped' => null, 'modded' => null];
        if ($keywords === null || $keywords === '') {
            return $result;
        }

        $rust = false;
        $mods = false;
        foreach (explode(',', $keywords) as $word) {
            $word = trim($word);
            if (preg_match('/^(?:lqs|qp)(\d{1,4})$/', $word, $m)) {
                $result['queue'] = (int) $m[1];
                $rust = $rust || str_starts_with($word, 'qp');
            } elseif (preg_match('/^etm(\d+(?:\.\d+)?)$/', $word, $m)) {
                $result['accel'] = round(min(64, max(0, (float) $m[1])), 2);
            } elseif (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $word, $m)) {
                $result['time'] = sprintf('%02d:%s', $m[1], $m[2]);
            } elseif (preg_match('/^born(\d{9,11})$/', $word, $m)) {
                // Майбутня дата чи зовсім давня — збій сервера, не показуємо
                $born = Carbon::createFromTimestamp((int) $m[1]);
                $result['wiped'] = $born->isFuture() || $born->lt(now()->subYear()) ? null : $born;
                $rust = true;
            } elseif (in_array(strtolower($word), ['oxide', 'carbon', 'modded'], true)) {
                $mods = true;
            } elseif (preg_match('/^(?:mp|cp)\d+$/', $word) || $word === 'gm_rust') {
                $rust = true;
            }
        }
        if ($rust) {
            $result['modded'] = $mods;
        }

        return $result;
    }
}
