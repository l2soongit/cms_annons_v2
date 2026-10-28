<?php

use App\Billing\Billing;
use App\Billing\ExchangeRates;
use App\Rating\RatingCalculator;
use App\Servers\ServerModeration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
 * На віртуальному хостингу один cron-запис щохвилини запускає `schedule:run`.
 * Черги розбираються тут же короткими сесіями замість постійного воркера.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('servers:monitor')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

Schedule::command('rating:recalculate')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('rating:close-month')->monthlyOn(1, '00:05');
Schedule::command('servers:aggregate')->hourlyAt(2);
Schedule::command('servers:release-blocks')->everyFifteenMinutes();
Schedule::command('billing:expire')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('billing:remind')->dailyAt('10:00');
// НБУ встановлює курс на наступний день близько 15:30; ранковий запуск — на випадок, якщо вечірній не вдався
Schedule::command('billing:rates')->twiceDaily(6, 16)->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('db:backup')->dailyAt('03:30')->withoutOverlapping(60);

// «Пульс» планувальника: release:check перевіряє, що cron справді запускає schedule:run
Schedule::call(fn () => Cache::forever('schedule:heartbeat', now()->getTimestamp()))->everyMinute()->name('schedule-heartbeat');

Artisan::command('rating:recalculate', function (RatingCalculator $rating) {
    $rating->recalculate();
    $this->info('Рейтинг перераховано');
})->purpose('Перерахувати бали та місця серверів');

Artisan::command('rating:close-month', function (RatingCalculator $rating) {
    $rating->closeMonth();
    $this->info('Переможців місяця збережено, рейтинг обнулено');
})->purpose('Закрити місяць: архів топ-3 і новий відлік');

Artisan::command('servers:release-blocks', function (ServerModeration $moderation) {
    $this->info('Розблоковано серверів: '.$moderation->releaseExpiredBlocks());
})->purpose('Повернути в рейтинг сервери, чий термін блокування минув');

Artisan::command('billing:expire', function (Billing $billing) {
    $this->info('Оброблено послуг, що закінчилися: '.$billing->expire());
})->purpose('Вимкнути платні послуги після терміну; з автопродовженням — оплатити наступний період');

Artisan::command('billing:rates', function (ExchangeRates $rates) {
    $this->info('Оновлено курсів НБУ: '.$rates->refresh());
})->purpose('Завантажити курси валют НБУ для поповнення в USD/EUR');

Artisan::command('billing:remind', function (Billing $billing) {
    $this->info('Надіслано нагадувань: '.$billing->remindExpiring());
})->purpose('Нагадати власникам про послуги, що скоро закінчаться');
