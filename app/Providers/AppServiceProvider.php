<?php

namespace App\Providers;

use App\Models\Game;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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

        View::composer('components.layouts.app', function ($view) {
            $view->with('navGames', once(fn () => Game::active()->get(['id', 'slug', 'name'])));
        });
    }
}
