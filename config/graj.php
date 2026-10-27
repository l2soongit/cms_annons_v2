<?php

/*
 * Налаштування порталу. Значення за замовчуванням — з ТЗ; відкриті питання ТЗ
 * (інтервал голосування, формула рейтингу) змінюються тут або через .env.
 */
return [

    'voting' => [
        // Через скільки годин можна знову голосувати за той самий сервер
        'interval_hours' => (int) env('VOTE_INTERVAL_HOURS', 12),

        // Скільки голосів з однієї підмережі /24 за інтервал вважаються нормою
        'subnet_limit' => (int) env('VOTE_SUBNET_LIMIT', 5),

        // Спроби доставки vote-callback і паузи між ними, секунди
        'callback_backoff' => [60, 300, 900, 3600, 10800],
    ],

    'rating' => [
        // score = голоси місяця * votes + бонусні бали * bonus + онлайн-гравці * online + аптайм% * uptime
        'weights' => [
            'votes' => (float) env('RATING_W_VOTES', 1),
            'bonus' => (float) env('RATING_W_BONUS', 1),
            'online' => (float) env('RATING_W_ONLINE', 0),
            'uptime' => (float) env('RATING_W_UPTIME', 0),
        ],
    ],

    'monitoring' => [
        'interval_minutes' => (int) env('MONITOR_INTERVAL', 5),
        'timeout_seconds' => (float) env('MONITOR_TIMEOUT', 2.5),
        // Скільки серверів опитує одна хвилина cron на віртуальному хостингу
        'batch_size' => (int) env('MONITOR_BATCH', 200),
        'raw_retention_days' => 7,
        'hourly_retention_days' => 365,
        'archive_after_offline_days' => 30,
    ],

    'servers' => [
        'max_screenshots' => 10,
        // Поля, зміна яких повертає сервер на модерацію
        'moderated_fields' => ['name', 'host', 'port', 'game_id'],
    ],

    'reviews' => [
        'stop_words' => array_filter(explode(',', (string) env('REVIEW_STOP_WORDS', ''))),
    ],

];
