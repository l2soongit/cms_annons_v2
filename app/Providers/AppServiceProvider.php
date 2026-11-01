<?php

namespace App\Providers;

use App\Analytics\Tracker;
use App\Backup\BackupTarget;
use App\Backup\SftpTarget;
use App\Models\Game;
use App\Support\Settings;
use App\Support\Translations;
use Illuminate\Auth\Events\Registered;
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

        // Копія бекапу поза сервер — лише коли задано сховище
        if (filled(config('graj.backup.sftp.host'))) {
            $this->app->bind(BackupTarget::class, fn () => new SftpTarget(config('graj.backup.sftp')));
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        // Тимчасові завантаження Livewire: за замовчуванням до 12 МБ, а архів оновлення важить ~25 МБ
        config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:204800']]);

        // Аналітика: реєстрація (email і соцмережі) — крок воронки «відвідувач → власник сервера»
        Event::listen(Registered::class, fn () => app(Tracker::class)->event(request(), 'register'));

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

        // Головна мова, вибрана в адмінці («Налаштування → Мови сайту»), — якщо вона є в списку мов
        $main = config('graj.default_locale');
        if (is_string($main) && array_key_exists($main, (array) config('graj.locales'))) {
            config(['app.locale_default' => $main, 'app.locale' => $main]);
        }

        // Адміністратор має всі дозволи: керування ролями не можна втратити, змінивши їх
        Gate::before(fn ($user) => $user->hasRole('admin') ? true : null);

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('discord', DiscordProvider::class);
        });

        View::composer(['components.layouts.app', 'components.layouts.home', 'components.layouts.game', 'components.layouts.server'], function ($view) {
            $view->with('navGames', once(fn () => Game::active()->get(['id', 'slug', 'name'])));
        });
    }
}
