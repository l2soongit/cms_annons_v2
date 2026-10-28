<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Заголовки безпеки (ТЗ 4, OWASP): заборона вбудовування сайту в чужі фрейми (clickjacking),
 * вгадування типу файлів, зайвих даних у Referer, доступу до камери/мікрофона/геолокації;
 * HSTS — лише на робочому сайті через HTTPS.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self)', false);
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups', false);

        // Віджети (SVG-картинки) вставляють на сайти серверів як <img> — фрейми їх не стосуються
        if (! $headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        if (app()->isProduction() && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        return $response;
    }
}
