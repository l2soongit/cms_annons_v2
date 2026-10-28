<?php

namespace App\Providers;

use App\Models\Game;
use App\Support\Settings;
use App\Support\Translations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Discord\Provider as DiscordProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Переклади з адмінки поверх файлів lang/
        $this->app->extend('translation.loader', fn ($loader) => new Translations($loader));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        // Основна мова сайту (без префікса в адресі); app.locale далі змінюється під мову запиту
        config(['app.locale_default' => config('app.locale_default', config('app.locale'))]);

        // Збірка Vite — від кореня сайту, а не від /en, який додається до посилань англійських сторінок
        Vite::createAssetPathsUsing(fn (string $path) => '/'.ltrim($path, '/'));

        // Старі версії MySQL на віртуальних хостингах обмежують довжину індексу
        Schema::defaultStringLength(191);

        try {
            Settings::apply();
        } catch (\Throwable) {
            // БД ще недоступна (встановлення, збірка) — працюємо зі значеннями config/graj.php
        }

        // Адміністратор має всі дозволи: керування ролями не можна втратити, змінивши їх
        Gate::before(fn ($user) => $user->hasRole('admin') ? true : null);

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('discord', DiscordProvider::class);
        });

        View::composer(['components.layouts.app', 'components.layouts.home', 'components.layouts.game'], function ($view) {
            $view->with('navGames', once(fn () => Game::active()->get(['id', 'slug', 'name'])));
        });
    }
}
