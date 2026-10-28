<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Мова з адреси: /en/lineage2 → мова en, далі запит обробляється як /lineage2.
 * Усі посилання сайту (route(), url()) на цій сторінці отримують префікс /en автоматично.
 * Працює до маршрутизації (глобальний middleware), тож окремі маршрути під кожну мову не потрібні.
 */
class LocalizeFromUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        [$first, $rest] = array_pad(explode('/', ltrim($request->getPathInfo(), '/'), 2), 2, '');
        $locale = Locales::default();

        if (in_array($first, Locales::prefixed(), true)) {
            $locale = $first;
            $path = '/'.$rest;
            $query = $request->getQueryString();

            $server = $request->server->all();
            $server['REQUEST_URI'] = $request->getBaseUrl().$path.($query !== null ? '?'.$query : '');
            $request = $request->duplicate(null, null, null, null, null, $server);
            $request->attributes->set(Locales::PATH_ATTRIBUTE, $path);

            URL::forceRootUrl($request->getSchemeAndHttpHost().$request->getBaseUrl().'/'.$locale);
        } else {
            // Між запитами в одному процесі (тести, черги) корінь адрес скидається
            URL::forceRootUrl(null);
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }

    /** Після відповіді — основна мова й корінь адрес без префікса (черги, тести й наступні запити процесу) */
    public function terminate(Request $request, Response $response): void
    {
        URL::forceRootUrl(null);
        app()->setLocale(Locales::default());
        Carbon::setLocale(Locales::default());
    }
}
