<?php

namespace App\Providers;

use App\Models\Game;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        // Старі версії MySQL на віртуальних хостингах обмежують довжину індексу
        Schema::defaultStringLength(191);

        try {
            Settings::apply();
        } catch (\Throwable) {
            // БД ще недоступна (встановлення, збірка) — працюємо зі значеннями config/graj.php
        }

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('discord', DiscordProvider::class);
        });

        View::composer(['components.layouts.app', 'components.layouts.home'], function ($view) {
            $view->with('navGames', once(fn () => Game::active()->get(['id', 'slug', 'name'])));
        });
    }
}
