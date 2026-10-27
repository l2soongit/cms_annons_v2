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

## Оновлення

GitHub → Actions → «Deploy to hosting» → Run workflow. Потім через SSH:

```
php artisan migrate --force
php artisan optimize
```

## Чому так

На віртуальному хостингу немає Docker, Redis і постійних процесів. Тому сесії, кеш і черги працюють через MySQL, а черги щохвилини розбирає `schedule:run` (див. `routes/console.php`). Чат і сповіщення оновлюються опитуванням (polling), а не через WebSocket.
