<?php

use Illuminate\Support\Facades\Schedule;

/*
 * На віртуальному хостингу один cron-запис щохвилини запускає `schedule:run`.
 * Черги розбираються тут же короткими сесіями замість постійного воркера.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('queue:prune-failed --hours=168')->daily();
