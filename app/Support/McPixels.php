<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Піксельна графіка 8×8 для сторінки Minecraft: іконка сервера без логотипа, значки режимів і кубок залу слави.
 */
final class McPixels
{
    /** Палітри іконок: колір обирається стабільно за назвою сервера */
    private const PALETTES = [
        ['#3e8a2a', '#6cc644', '#8b5a2b'], ['#ffaa00', '#ff5555', '#5555ff'], ['#55ffff', '#2a8a8a', '#ffffff'], ['#aa0000', '#333333', '#ff5555'],
        ['#ffff55', '#78b6ff', '#ffffff'], ['#ff55ff', '#aa00aa', '#ffff55'], ['#ffaa00', '#aa5500', '#555555'], ['#4ee6d6', '#7d7d7d', '#ffd84a'],
    ];

    /** Значки режимів за підписом опції; невідомі — блок трави */
    private const MODES = [
        'Виживання' => '<rect x="1" y="1" width="1" height="1" fill="#ccc"/><rect x="2" y="2" width="1" height="1" fill="#ccc"/><rect x="3" y="3" width="1" height="1" fill="#ccc"/><rect x="4" y="4" width="1" height="1" fill="#ccc"/><rect x="5" y="5" width="1" height="1" fill="#8b5a2b"/><rect x="6" y="6" width="1" height="1" fill="#8b5a2b"/><rect x="5" y="3" width="1" height="1" fill="#8b5a2b"/><rect x="3" y="5" width="1" height="1" fill="#8b5a2b"/>',
        'SkyBlock' => '<rect x="1" y="3" width="6" height="1" fill="#6cc644"/><rect x="2" y="4" width="4" height="1" fill="#8b5a2b"/><rect x="3" y="5" width="2" height="1" fill="#7d7d7d"/><rect x="4" y="1" width="1" height="2" fill="#6b4a2b"/>',
        'Анархія' => '<rect x="2" y="1" width="4" height="1" fill="#e5332b"/><rect x="1" y="2" width="6" height="4" fill="#e5332b"/><rect x="2" y="6" width="4" height="1" fill="#e5332b"/><rect x="3" y="3" width="2" height="2" fill="#fff"/>',
        'Міні-ігри' => '<rect x="1" y="1" width="6" height="6" fill="#fff"/><rect x="2" y="2" width="1" height="1" fill="#000"/><rect x="5" y="2" width="1" height="1" fill="#000"/><rect x="2" y="5" width="1" height="1" fill="#000"/><rect x="5" y="5" width="1" height="1" fill="#000"/><rect x="3" y="3" width="2" height="2" fill="#e5332b"/>',
        'Творчий' => '<rect x="1" y="1" width="3" height="3" fill="#ff55ff"/><rect x="4" y="1" width="3" height="3" fill="#55ffff"/><rect x="1" y="4" width="3" height="3" fill="#ffff55"/><rect x="4" y="4" width="3" height="3" fill="#55ff55"/>',
    ];

    private const GRASS = '<rect x="1" y="2" width="6" height="5" fill="#8b5a2b"/><rect x="1" y="2" width="6" height="2" fill="#6cc644"/>';

    /** Симетрична іконка 8×8 з палітри; та сама назва — та сама картинка */
    public static function icon(string $name): string
    {
        $seed = 7;
        foreach (mb_str_split($name) as $char) {
            $seed = ($seed * 31 + mb_ord($char)) & 0xFFFFFFFF;
        }
        $palette = self::PALETTES[crc32($name) % count(self::PALETTES)];
        $next = function () use (&$seed): float {
            $seed = ($seed * 1103515245 + 12345) & 0xFFFFFFFF;

            return $seed / 4294967296;
        };

        $rects = '';
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 4; $x++) {
                $v = $next();
                if ($v < .25) {
                    continue;
                }
                $c = $palette[(int) floor($v * 3) % 3];
                $rects .= '<rect x="'.$x.'" y="'.$y.'" width="1" height="1" fill="'.$c.'"/><rect x="'.(7 - $x).'" y="'.$y.'" width="1" height="1" fill="'.$c.'"/>';
            }
        }

        return '<svg class="icon" viewBox="0 0 8 8" shape-rendering="crispEdges" aria-hidden="true"><rect width="8" height="8" fill="#1b1b1b"/>'.$rects.'</svg>';
    }

    /** @param  string  $mode  значення варіанта (vizivannia, skyblock…) або основний підпис */
    public static function mode(string $mode, int $size = 20): string
    {
        $rects = self::MODES[$mode] ?? collect(self::MODES)->first(fn ($svg, $label) => Str::slug($label) === $mode) ?? self::GRASS;

        return self::svg($rects, $size);
    }

    public static function trophy(string $color): string
    {
        return self::svg('<rect x="1" y="1" width="6" height="3" fill="'.$color.'"/><rect x="2" y="4" width="4" height="1" fill="'.$color.'"/><rect x="3" y="5" width="2" height="1" fill="'.$color.'"/><rect x="2" y="6" width="4" height="1" fill="#8b5a2b"/><rect x="0" y="1" width="1" height="2" fill="'.$color.'"/><rect x="7" y="1" width="1" height="2" fill="'.$color.'"/>', 22);
    }

    /** Значок анонсу відкриття: за режимом сервера, якщо він відомий */
    public static function soon(?string $mode): string
    {
        return self::mode($mode ?? '', 32);
    }

    private static function svg(string $rects, int $size): string
    {
        return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 8 8" shape-rendering="crispEdges" aria-hidden="true">'.$rects.'</svg>';
    }
}
