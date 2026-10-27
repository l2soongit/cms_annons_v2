# graj.club

Рейтинг ігрових серверів: Lineage 2, GTA, Minecraft, DayZ, Rust, Counter-Strike.

Стек: Laravel 12, Blade + Livewire, Tailwind CSS 4, Filament (адмінка), MySQL на продакшні, SQLite локально.

## Локальний запуск

Потрібні PHP 8.3+, Composer, Node.js 20+.

```
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

Сайт: http://127.0.0.1:8000, адмінка: http://127.0.0.1:8000/admin.

Демо-акаунти (лише для локального середовища):

| Роль | Email | Пароль |
| --- | --- | --- |
| Адміністратор | admin@graj.club | password |
| Власник серверів | owner@graj.club | password |

Під час розробки фронтенду замість `npm run build` запускайте `npm run dev`.

## Тести

```
php artisan test
vendor/bin/pint
```

## Структура

- `database/migrations` — повна схема БД з ТЗ (ігри, сервери, моніторинг, голоси, відгуки, біллінг, повідомлення, модерація)
- `database/seeders` — ігри з полями, тарифи, ролі; демо-сервери тільки для `APP_ENV=local`
- `resources/css/app.css` — дизайн-токени (темна і світла тема)
- `resources/views/components` — макет і рядок сервера

## Розгортання

Див. [DEPLOY.md](DEPLOY.md). Продакшн — віртуальний хостинг: без Redis і постійних процесів, черги через cron.
