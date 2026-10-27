# Розгортання graj.club на віртуальному хостингу

## Вимоги до хостингу

- PHP 8.2+ (рекомендовано 8.3) з розширеннями: mbstring, intl, pdo_mysql, gd, zip, fileinfo, openssl, curl, sockets
- MySQL 5.7+ або MariaDB 10.3+ (краще MySQL 8)
- Cron з інтервалом 1 хвилина
- Можливість вказати кореневу теку сайту (document root) на `public/`
- SSH бажаний (для `php artisan migrate`), але не обов'язковий
- Дозвіл вихідних UDP/TCP-з'єднань: потрібен для моніторингу серверів. Уточніть у хостера до етапу 2

## Перше встановлення

1. Створіть базу MySQL і користувача в панелі хостингу.
2. Вкажіть document root домену `graj.club` → `<тека проєкту>/public`.
   Якщо панель цього не дозволяє: покладіть проєкт поза `public_html`, а вміст `public/` — у `public_html`, і виправте шляхи в `public_html/index.php`.
3. Залийте код (GitHub Actions → «Deploy to hosting» або вручну через FTP).
4. Створіть `.env` на сервері з `.env.production.example`, заповніть доступи до БД і пошти.
5. Через SSH:
   ```
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --force
   php artisan storage:link
   php artisan optimize
   ```
6. Додайте cron-задачу:
   ```
   * * * * * cd /шлях/до/проєкту && php artisan schedule:run >> /dev/null 2>&1
   ```

## Зовнішні сервіси (необов'язкові)

Без ключів відповідні функції просто вимкнені. Усі змінні є в `.env.production.example`.

| Що | Де взяти | Змінні |
| --- | --- | --- |
| Вхід через Google | console.cloud.google.com → OAuth client, redirect `https://graj.club/auth/google/callback` | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` |
| Вхід через Discord | discord.com/developers → OAuth2, redirect `https://graj.club/auth/discord/callback` | `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET` |
| Telegram-сповіщення | @BotFather → новий бот | `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `TELEGRAM_WEBHOOK_SECRET` (будь-який довгий рядок) |
| Капча | dash.cloudflare.com → Turnstile | `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET` |
| Виявлення VPN | ipqualityscore.com | `IPQS_KEY` |

Після налаштування бота один раз зареєструйте вебхук (відкрийте в браузері):

```
https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/setWebhook?url=https://graj.club/api/telegram/webhook/<TELEGRAM_WEBHOOK_SECRET>
```

Модераторам і адміністраторам потрібна 2FA (`REQUIRE_STAFF_2FA=true`): перший вхід в `/admin` перенаправить на сторінку «Безпека» в кабінеті.

## Оновлення

GitHub → Actions → «Deploy to hosting» → Run workflow. Потім через SSH:

```
php artisan migrate --force
php artisan optimize
```

## Чому так

На віртуальному хостингу немає Docker, Redis і постійних процесів. Тому сесії, кеш і черги працюють через MySQL, а черги щохвилини розбирає `schedule:run` (див. `routes/console.php`). Чат і сповіщення оновлюються опитуванням (polling), а не через WebSocket.
