<?php

namespace App\Support;

/**
 * Опис сервера Minecraft (MOTD) з кодами форматування §: колір 0–f, §l жирний, §k «зашифрований», §r скидання.
 */
final class Motd
{
    public const COLORS = [
        '0' => '#000000', '1' => '#0000aa', '2' => '#00aa00', '3' => '#00aaaa', '4' => '#aa0000', '5' => '#aa00aa', '6' => '#ffaa00', '7' => '#aaaaaa',
        '8' => '#555555', '9' => '#5555ff', 'a' => '#55ff55', 'b' => '#55ffff', 'c' => '#ff5555', 'd' => '#ff55ff', 'e' => '#ffff55', 'f' => '#ffffff',
    ];

    /** Назви кольорів у JSON-компонентах чату (Java) */
    private const NAMES = [
        'black' => '0', 'dark_blue' => '1', 'dark_green' => '2', 'dark_aqua' => '3', 'dark_red' => '4', 'dark_purple' => '5', 'gold' => '6', 'gray' => '7',
        'dark_gray' => '8', 'blue' => '9', 'green' => 'a', 'aqua' => 'b', 'red' => 'c', 'light_purple' => 'd', 'yellow' => 'e', 'white' => 'f',
    ];

    private const DEFAULT_COLOR = '#aaaaaa';

    public static function colorCode(?string $name): ?string
    {
        return $name === null ? null : (self::NAMES[$name] ?? null);
    }

    /** Без кодів форматування — для назви й пошуку */
    public static function plain(string $motd): string
    {
        return trim(preg_replace('/§./u', '', $motd));
    }

    /** Не більше двох рядків і довжини колонки */
    public static function clip(string $motd): string
    {
        $lines = array_slice(preg_split('/\R/u', $motd), 0, 2);

        return mb_substr(implode("\n", array_map('rtrim', $lines)), 0, 300);
    }

    /** Кольорові шматки тексту; рядки розділені <br>. Текст екранується. */
    public static function html(?string $motd): string
    {
        if ($motd === null || self::plain($motd) === '') {
            return '';
        }

        return collect(preg_split('/\R/u', self::clip($motd)))->map(function (string $line) {
            $out = '';
            $color = self::DEFAULT_COLOR;
            $bold = $obfuscated = false;

            foreach (preg_split('/(§[0-9a-fk-or])/iu', $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $part) {
                if (preg_match('/^§([0-9a-fk-or])$/iu', $part, $m)) {
                    $code = strtolower($m[1]);
                    if (isset(self::COLORS[$code])) {
                        [$color, $bold, $obfuscated] = [self::COLORS[$code], false, false];
                    } elseif ($code === 'l') {
                        $bold = true;
                    } elseif ($code === 'k') {
                        $obfuscated = true;
                    } elseif ($code === 'r') {
                        [$color, $bold, $obfuscated] = [self::DEFAULT_COLOR, false, false];
                    }

                    continue;
                }

                $out .= '<span'.($obfuscated ? ' class="obf"' : '').' style="color:'.$color.($bold ? ';font-weight:700' : '').'">'.e($part).'</span>';
            }

            return $out;
        })->implode('<br>');
    }
}
