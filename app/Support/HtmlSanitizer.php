<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Обмежений HTML для описів серверів: лише те, що дає редактор (жирний, курсив, списки,
 * заголовки, цитати, посилання). Скрипти, стилі, iframe та атрибути подій вирізаються.
 */
final class HtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        return trim(self::purifier()->purify($html));
    }

    /**
     * Видимий текст без розмітки — для перевірки мінімальної довжини і meta description.
     */
    public static function text(?string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '</div>', '</p>', '</li>'], ' ', (string) $html)))));
    }

    /**
     * HTML для показу: старі описи без розмітки отримують переноси рядків.
     */
    public static function render(?string $stored): string
    {
        if (blank($stored)) {
            return '';
        }

        return $stored === strip_tags($stored) ? nl2br(e($stored)) : $stored;
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier) {
            return self::$purifier;
        }

        $cache = storage_path('framework/cache/htmlpurifier');
        if (! is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', $cache);
        $config->set('HTML.Allowed', 'div,p,br,strong,b,em,i,del,s,u,ul,ol,li,h1,h2,h3,blockquote,pre,a[href]');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true]);
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.Nofollow', true);
        $config->set('AutoFormat.RemoveEmpty', true);

        return self::$purifier = new HTMLPurifier($config);
    }
}
