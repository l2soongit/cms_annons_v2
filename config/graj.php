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

    // Мови сайту: код => назва. Перша — основна (без префікса в адресі), решта — /<код>/…
    'locales' => [
        'uk' => 'Українська',
        'en' => 'English',
    ],

    'billing' => [
        // Баланс ведеться в гривнях; платіжні системи списують у цій валюті
        'currency' => 'UAH',
        'min_deposit' => 50,
        'max_deposit' => 50000,
        // За скільки днів до кінця послуги нагадати власнику
        'remind_days' => 3,
        // На скільки днів наперед можна забронювати банер
        'banner_booking_days' => 60,
        // Платіжні системи для поповнення балансу, у порядку показу: ключ => клас App\Billing\Gateways\*
        'gateways' => ['liqpay', 'stripe'],
    ],

    'messages' => [
        // Антиспам: скільки нових діалогів користувач може почати за добу
        'daily_new_conversations' => 10,
        // Як часто сторінка діалогу перевіряє нові повідомлення, с (на хостингу немає WebSocket)
        'poll_seconds' => 10,
    ],

    'notifications' => [
        // Подія => канали за замовчуванням; користувач змінює їх у кабінеті
        'events' => [
            'moderation' => ['mail'],
            'server_offline' => ['mail'],
            'new_review' => ['mail'],
            'billing' => ['mail'],
            'messages' => ['mail'],
        ],
        // Скільки хвилин сервер має бути офлайн, щоб власник отримав сповіщення
        'offline_after_minutes' => 15,
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        // Секрет у URL вебхука, щоб чужі запити не приймалися
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    'security' => [
        'require_staff_2fa' => (bool) env('REQUIRE_STAFF_2FA', true),
    ],

    'privacy' => [
        // Хеші IP голосів старші за цей термін видаляються (ТЗ: не довше 90 днів)
        'vote_ip_retention_days' => 90,
    ],

    'reviews' => [
        'stop_words' => array_filter(explode(',', (string) env('REVIEW_STOP_WORDS', ''))),
    ],

    // Головна сторінка; тексти змінюються в адмінці («Головна сторінка»), тут — значення за замовчуванням
    'home' => [
        // Порядок фіксований у шаблоні, тут лише які секції показувати
        'sections' => ['arena', 'marquee', 'games', 'ranking', 'soon', 'owners', 'plans', 'cta'],
        'hero_title' => ['uk' => 'Знайди сервер, де справді', 'en' => 'Find a server where people actually'],
        'hero_accent' => ['uk' => 'грають', 'en' => 'play'],
        'hero_lead' => [
            'uk' => 'Чесний рейтинг ігрових серверів. Онлайн перевіряємо кожні 5 хвилин, голоси — без накрутки.',
            'en' => 'Honest game server rankings. Player counts are checked every 5 minutes, votes are protected from cheating.',
        ],
        // Посилання «Часто шукають» під пошуком
        'quick_links' => ['Interlude x50', 'FiveM RP', 'Анархія 1.21', 'Rust Solo'],
        // Підказки, що «друкуються» в полі пошуку
        'search_hints' => ['Interlude x50 PvP', 'FiveM RP з економікою', 'Minecraft анархія 1.21', 'Rust Solo, вайп щотижня', 'CS2 Retake 128 tick'],
        'owners_title' => ['uk' => 'Від реєстрації до першого місця', 'en' => 'From sign-up to first place'],
        'owners_lead' => [
            'uk' => 'Додавання безкоштовне. Портал сам перевіряє онлайн і приводить гравців, які голосують саме за вас.',
            'en' => 'Listing is free. We check your server automatically and bring players who vote for you.',
        ],
        'cta_title' => ['uk' => 'Твій сервер заслуговує на гравців', 'en' => 'Your server deserves players'],
        'cta_text' => ['uk' => 'Додай його за 3 хвилини. Модерація зазвичай займає до доби.', 'en' => 'Add it in 3 minutes. Moderation usually takes under a day.'],
        'ranking_size' => 10,
        // Власні зображення (шлях на диску public); без них — анімований фон із демо
        'hero_image' => null,
        'hero_image_opacity' => 35,
        'cta_image' => null,
        // Логотип у шапці й підвалі головної та картинка для прев'ю посилання в соцмережах (1200×630)
        'logo_image' => null,
        'og_image' => null,
        // Сповіщення «щойно проголосували» внизу екрана
        'live_feed' => true,
    ],

];
