<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Кеш готових сторінок для гостей. Сторінка однакова для всіх гостей, крім CSRF-токена сесії:
 * у збереженому HTML він замінений міткою, яку при видачі підставляє токен поточного відвідувача.
 */
final class PageCache
{
    public const TOKEN_MARK = '__graj_page_csrf__';

    /** Довші адреси (випадкові параметри) не кешуються — щоб ніхто не забив диск варіантами однієї сторінки */
    private const MAX_QUERY = 256;

    public static function enabled(): bool
    {
        return (bool) config('graj.page_cache.enabled') && self::ttl() > 0;
    }

    public static function ttl(): int
    {
        return max(0, (int) config('graj.page_cache.ttl'));
    }

    public static function store(): Repository
    {
        return Cache::store(config('graj.page_cache.store') ?: 'pages');
    }

    /** Чи можна віддати цьому запиту спільну сторінку (і зберегти його відповідь) */
    public static function applies(Request $request): bool
    {
        if (! self::enabled() || ! $request->isMethodCacheable() || $request->user() !== null) {
            return false;
        }

        if (strlen((string) $request->getQueryString()) > self::MAX_QUERY) {
            return false;
        }

        // Повідомлення після дії («Дякуємо…», помилки форми) — особисті, такі сторінки не кешуються
        if ($request->hasSession()) {
            $session = $request->session();
            if ($session->get('_flash.old') || $session->get('_flash.new') || $session->has('errors')) {
                return false;
            }
        }

        return true;
    }

    public static function key(Request $request): string
    {
        $parts = [
            app()->getLocale(),
            $request->getSchemeAndHttpHost().$request->getRequestUri(),
            // Повідомлення про cookies показується, поки гість його не закрив
            $request->cookie('cookie_notice') ? 'cn' : '',
            // Часткове оновлення списку серверів (фільтри без перезавантаження) — інший HTML
            $request->header('X-Partial') ? 'p' : '',
        ];

        return 'page:'.sha1(implode('|', $parts));
    }

    /** Скинути всі збережені сторінки: після змін ігор, серверів, відгуків, налаштувань */
    public static function flush(): void
    {
        try {
            self::store()->flush();
        } catch (Throwable) {
            // Кеш сторінок — лише прискорення; збій очищення не має ламати збереження в адмінці
        }
    }
}
