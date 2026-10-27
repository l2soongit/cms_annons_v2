<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Назви й кольори відомих карт Counter-Strike; невідомі карти показуються з технічної назви.
 */
final class CsMaps
{
    /** технічна назва => [назва, колір, код] */
    private const MAPS = [
        'de_dust2' => ['Dust II', '#d9a55b', 'D2'],
        'de_mirage' => ['Mirage', '#c9906b', 'MRG'],
        'de_inferno' => ['Inferno', '#c2553a', 'INF'],
        'de_nuke' => ['Nuke', '#7fa7b5', 'NUK'],
        'de_ancient' => ['Ancient', '#5f8f5a', 'ANC'],
        'de_anubis' => ['Anubis', '#c7b07a', 'ANB'],
        'de_vertigo' => ['Vertigo', '#8aa0b8', 'VRT'],
        'de_overpass' => ['Overpass', '#8fb3a0', 'OVP'],
        'de_train' => ['Train', '#9c8f7a', 'TRN'],
        'cs_office' => ['Office', '#9aa0a6', 'OFC'],
        'cs_italy' => ['Italy', '#c99a6b', 'ITA'],
    ];

    public static function name(string $map): string
    {
        return self::MAPS[$map][0] ?? Str::of($map)->after('_')->replace('_', ' ')->title()->value();
    }

    public static function color(string $map): string
    {
        return self::MAPS[$map][1] ?? '#4fc3f7';
    }

    public static function code(string $map): string
    {
        return self::MAPS[$map][2] ?? Str::of($map)->after('_')->upper()->substr(0, 3)->value();
    }
}
