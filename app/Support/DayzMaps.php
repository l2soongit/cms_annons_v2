<?php

namespace App\Support;

/**
 * Мапи DayZ для сторінки гри: колір мітки, колір паперу топографічної карти й самі контури висот.
 * Варіанти поля «Мапа» задає адмін; невідомі мапи отримують кольори за порядком.
 */
final class DayzMaps
{
    /** значення варіанта => [колір мітки, колір паперу] */
    private const MAPS = [
        'chernarus' => ['#7da35a', '#c9c39c'],
        'livonia' => ['#c98a3a', '#d2c39a'],
        'sakhal' => ['#8fb3c9', '#d9dde0'],
        'deer-isle' => ['#5aa3a3', '#c7cba6'],
        'namalsk' => ['#9fb2d9', '#d6dadf'],
        'esseker' => ['#b07a5a', '#cfc0a0'],
    ];

    private const FALLBACK = [['#a3a35a', '#cdc8a0'], ['#8a7ab0', '#d3cfe0'], ['#5a8a6a', '#c3cbb0'], ['#b05a5a', '#d6c4b8']];

    /** Колір мітки мапи (точка в рядку, смуга популярності) */
    public static function color(?string $value, int $index = 0): string
    {
        return self::MAPS[self::key($value)][0] ?? self::FALLBACK[$index % count(self::FALLBACK)][0];
    }

    public static function paper(?string $value, int $index = 0): string
    {
        return self::MAPS[self::key($value)][1] ?? self::FALLBACK[$index % count(self::FALLBACK)][1];
    }

    /**
     * Топографічна карта: контури висот навколо двох вершин, річка й дорога. Та сама мапа — той самий малюнок.
     */
    public static function contours(string $value, string $ink = '#3d3a22'): string
    {
        $seed = crc32($value) ?: 17;
        $rnd = function () use (&$seed): float {
            $seed = ($seed * 1103515245 + 12345) & 0xFFFFFFFF;

            return $seed / 4294967296;
        };

        $paths = '';
        foreach ([[$rnd() * 50 + 15, $rnd() * 40 + 20], [$rnd() * 40 + 45, $rnd() * 40 + 45]] as [$cx, $cy]) {
            $ph = $rnd() * 6;
            for ($k = 1; $k <= 6; $k++) {
                $base = $k * 6.5;
                $d = '';
                for ($a = 0; $a <= 36; $a++) {
                    $t = $a / 36 * M_PI * 2;
                    $r = $base * (1 + .18 * sin(3 * $t + $ph) + .1 * sin(5 * $t + $ph * 2));
                    $d .= ($a ? 'L' : 'M').round($cx + cos($t) * $r, 1).' '.round($cy + sin($t) * $r * .8, 1);
                }
                $paths .= '<path d="'.$d.'Z" fill="none" stroke="'.$ink.'" stroke-opacity="'.($k === 3 ? .6 : .32).'" stroke-width="'.($k === 3 ? .9 : .6).'"/>';
            }
        }
        $ry = round($rnd() * 60 + 20, 1);
        $paths .= '<path d="M-5 '.$ry.' C 30 '.($ry - 20).', 55 '.($ry + 25).', 105 '.($ry - 5).'" fill="none" stroke="#4f8fb8" stroke-width="2.2" stroke-opacity=".7"/>';
        $paths .= '<path d="M'.round($rnd() * 30, 1).' 105 L '.round(40 + $rnd() * 20, 1).' 55 L 105 '.round($rnd() * 40, 1).'" fill="none" stroke="#8a3a2a" stroke-width="1.2" stroke-dasharray="3 2" stroke-opacity=".7"/>';

        return '<svg viewBox="0 0 100 100" preserveAspectRatio="xMidYMid slice" aria-hidden="true">'.$paths.'</svg>';
    }

    /**
     * Стан сервера за аптаймом — як стан предметів у грі.
     *
     * @return array{0: int, 1: string, 2: string} кількість поділок (1–5), ключ перекладу, CSS-колір
     */
    public static function condition(?float $uptime): array
    {
        $uptime ??= 0;

        return match (true) {
            $uptime >= 99 => [5, 'pristine', 'var(--c5)'],
            $uptime >= 97 => [4, 'worn', 'var(--c4)'],
            $uptime >= 93 => [3, 'damaged', 'var(--c3)'],
            $uptime >= 85 => [2, 'badly', 'var(--c2)'],
            default => [1, 'ruined', 'var(--c1)'],
        };
    }

    private static function key(?string $value): string
    {
        return str((string) $value)->lower()->slug()->value();
    }
}
