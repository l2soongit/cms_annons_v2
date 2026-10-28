<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Мови сайту (ТЗ 4: мова в URL /en/…). Основна мова — без префікса, решта — з префіксом /<код>.
 * Список задається в адмінці (Налаштування → Мови), тож нова мова додається без зміни коду.
 */
final class Locales
{
    /** Атрибут запиту зі шляхом без мовного префікса */
    public const PATH_ATTRIBUTE = 'unlocalized_path';

    /** @return array<string, string> код => назва; основна мова завжди перша */
    public static function all(): array
    {
        $default = self::default();
        $list = array_filter((array) config('graj.locales'), fn ($name, $code) => is_string($code) && preg_match('/^[a-z]{2}$/', $code), ARRAY_FILTER_USE_BOTH);

        return [$default => $list[$default] ?? strtoupper($default)] + $list;
    }

    public static function default(): string
    {
        return (string) config('app.locale_default', config('app.locale'));
    }

    /** @return list<string> мови з префіксом в адресі */
    public static function prefixed(): array
    {
        return array_values(array_diff(array_keys(self::all()), [self::default()]));
    }

    public static function isSupported(?string $code): bool
    {
        return $code !== null && array_key_exists($code, self::all());
    }

    /** Шлях поточної сторінки без мовного префікса, напр. /lineage2 */
    public static function currentPath(?Request $request = null): string
    {
        $request ??= request();

        return (string) ($request->attributes->get(self::PATH_ATTRIBUTE) ?? $request->getPathInfo());
    }

    /** Абсолютна адреса основною мовою → та сама адреса іншою мовою (для sitemap) */
    public static function localize(string $url, string $locale): string
    {
        if ($locale === self::default()) {
            return $url;
        }

        $parts = parse_url($url);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
        $path = rtrim($parts['path'] ?? '', '/');

        return $origin.'/'.$locale.$path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /** Адреса поточної сторінки іншою мовою (для hreflang і перемикача), без параметрів запиту, крім page */
    public static function urlFor(string $locale, ?Request $request = null): string
    {
        $request ??= request();
        $path = rtrim(self::currentPath($request), '/');
        $prefix = $locale === self::default() ? '' : '/'.$locale;
        $query = $request->query('page') ? '?page='.(int) $request->query('page') : '';

        return $request->getSchemeAndHttpHost().$request->getBaseUrl().(($prefix.$path) ?: '/').$query;
    }
}
