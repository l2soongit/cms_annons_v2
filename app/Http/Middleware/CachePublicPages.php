<?php

namespace App\Http\Middleware;

use App\Support\PageCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Головна, сторінки ігор і серверів для гостей: перший гість чекає на побудову сторінки,
 * наступні протягом ttl секунд отримують готовий HTML. Залогінені бачать сторінку наживо.
 */
class CachePublicPages
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PageCache::applies($request)) {
            return $next($request);
        }

        $key = PageCache::key($request);

        try {
            $hit = PageCache::store()->get($key);
        } catch (Throwable) {
            $hit = null;
        }

        if (is_array($hit) && isset($hit['body'], $hit['type'])) {
            return new IlluminateResponse(
                str_replace(PageCache::TOKEN_MARK, (string) csrf_token(), $hit['body']),
                200,
                ['Content-Type' => $hit['type'], 'X-Page-Cache' => 'HIT'],
            );
        }

        $response = $next($request);

        if ($this->storable($request, $response)) {
            $body = (string) $response->getContent();
            $token = (string) csrf_token();
            if ($token !== '') {
                $body = str_replace($token, PageCache::TOKEN_MARK, $body);
            }

            try {
                PageCache::store()->put($key, ['body' => $body, 'type' => $response->headers->get('Content-Type')], PageCache::ttl());
                $response->headers->set('X-Page-Cache', 'MISS');
            } catch (Throwable) {
                // Немає місця чи прав на теку кешу — сторінка просто віддається без кешу
            }
        }

        return $response;
    }

    private function storable(Request $request, Response $response): bool
    {
        return $response instanceof IlluminateResponse
            && $response->getStatusCode() === 200
            && str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')
            && $response->headers->getCookies() === []
            // Контролер міг увійти користувача чи записати повідомлення — така відповідь особиста
            && PageCache::applies($request);
    }
}
