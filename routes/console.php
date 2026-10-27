<?php

use App\Rating\RatingCalculator;
use Illuminate\Support\Facades\Artisan;
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
Schedule::command('queue:prune-failed --hours=168')->daily();

Artisan::command('rating:recalculate', function (RatingCalculator $rating) {
    $rating->recalculate();
    $this->info('Рейтинг перераховано');
})->purpose('Перерахувати бали та місця серверів');

Artisan::command('rating:close-month', function (RatingCalculator $rating) {
    $rating->closeMonth();
    $this->info('Переможців місяця збережено, рейтинг обнулено');
})->purpose('Закрити місяць: архів топ-3 і новий відлік');
