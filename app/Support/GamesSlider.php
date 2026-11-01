<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Дрібна розмітка для варіантів блоку «Обери свою гру» (resources/views/home/games/*): логотип, «сервери · онлайн», кольори.
 */
final class GamesSlider
{
    private static function num(int $n): string
    {
        return number_format($n, 0, ',', "\u{00A0}");
    }

    /** Логотип гри або кольоровий значок з першою літерою */
    public static function logo(array $g, string $class = 'gs-logo'): HtmlString
    {
        return new HtmlString($g['logo']
            ? '<img class="'.e($class).'" src="'.e($g['logo']).'" alt="" loading="lazy">'
            : '<span class="'.e($class).' gs-mono">'.e(mb_substr($g['short'], 0, 1)).'</span>');
    }

    /** «11 серверів · ● 313 онлайн» з виділеними числами */
    public static function stats(array $g): HtmlString
    {
        $part = function (string $text, int $n) {
            $num = self::num($n);

            return str_replace(e($num), '<b>'.e($num).'</b>', e($text));
        };

        return new HtmlString('<span class="gs-stats"><span>'.$part(trans_choice('ui.home.servers_n', $g['servers'], ['n' => self::num($g['servers'])]), $g['servers']).'</span>'
            .'<span><i class="gs-live"></i>'.$part(__('ui.home.online', ['n' => self::num($g['online'])]), $g['online']).'</span></span>');
    }

    /** CSS-змінні кольорів гри для style="" */
    public static function vars(array $g): string
    {
        return '--c1:'.$g['c1'].';--c2:'.$g['c2'];
    }
}
