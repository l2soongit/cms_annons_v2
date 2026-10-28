<?php

use App\Http\Middleware\LocalizeFromUrl;
use App\Http\Middleware\RememberUserLocale;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Платіжна система повертає покупця POST-запитом зі свого домену; сторінка лише перенаправляє в кабінет
        $middleware->validateCsrfTokens(except: ['billing/return']);
        // Мова з адреси (/en/…) — до маршрутизації; мова користувача для листів — після сесії
        $middleware->prepend(LocalizeFromUrl::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [RememberUserLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Помилки йдуть у Sentry, лише якщо задано SENTRY_LARAVEL_DSN
        Integration::handles($exceptions);
    })->create();
