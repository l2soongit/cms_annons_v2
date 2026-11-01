<?php

/**
 * Встановлення graj.club через браузер (віртуальний хостинг без SSH).
 * Відкрийте https://ваш-домен/install.php, заповніть форму — інсталятор запише .env, створить таблиці,
 * наповнить базу й створить адміністратора. Після успіху він блокується (storage/installed) і видаляє себе.
 * Логіка — у App\Install\Installer.
 */

use App\Install\Installer;
use App\Install\InstallFailed;

$base = dirname(__DIR__);

if (! is_file($base.'/vendor/autoload.php')) {
    http_response_code(500);
    exit('Немає теки vendor: архів розпаковано не повністю. Завантажте всі файли ще раз.');
}
require $base.'/vendor/autoload.php';

$installer = new Installer($base);
if ($installer->isInstalled()) {
    http_response_code(404);
    exit('Сайт уже встановлено. Видаліть public/install.php з хостингу.');
}

session_start();
$_SESSION['install_token'] ??= bin2hex(random_bytes(16));

$h = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$checks = $installer->requirements();
$ready = Installer::passes($checks);
$scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
$in = [
    'app_url' => $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost'),
    'db_host' => 'localhost', 'db_port' => '3306', 'db_database' => '', 'db_username' => '', 'db_password' => '',
    'admin_email' => '', 'admin_name' => '', 'admin_password' => '', 'admin_password2' => '',
    'mail_host' => '', 'mail_port' => '465', 'mail_username' => '', 'mail_password' => '', 'mail_from' => '',
];
$errors = [];
$log = null;
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    foreach ($in as $key => $default) {
        $in[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : $default;
    }
    if (! hash_equals($_SESSION['install_token'], (string) ($_POST['_token'] ?? ''))) {
        $errors[] = 'Сторінка застаріла — оновіть її й надішліть форму ще раз.';
    }
    $errors = [...$errors, ...Installer::validate($in)];
    if (! $errors && ($dbError = $installer->testDatabase(['host' => trim($in['db_host']), 'port' => trim($in['db_port']), 'database' => trim($in['db_database']), 'username' => trim($in['db_username']), 'password' => $in['db_password']]))) {
        $errors[] = $dbError;
    }
    if (! $errors) {
        try {
            $installer->writeEnv(Installer::envValues($in));
            $log = $installer->finish($in['admin_email'], $in['admin_password'], trim($in['admin_name']));
            $done = true;
            // Інсталятор більше не потрібен
            @unlink(__FILE__);
        } catch (InstallFailed $e) {
            $log = $e->log;
            $errors[] = 'Встановлення зупинилося на кроці «'.$e->log[array_key_last($e->log)][0].'». Виправте причину й надішліть форму ще раз — виконані кроки повторяться безпечно.';
        } catch (Throwable $e) {
            $errors[] = 'Помилка: '.$e->getMessage();
        }
    }
    $in['admin_password'] = $in['admin_password2'] = '';
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Встановлення graj.club</title>
<style>
:root { --bg: #09090b; --card: #18181b; --line: #3f3f46; --text: #f4f4f5; --muted: #a1a1aa; --gold: #fbbf24; --ok: #34d399; --bad: #f87171; --warn: #fbbf24; }
* { box-sizing: border-box; }
body { margin: 0; background: radial-gradient(900px 500px at 80% -10%, #f9731622, transparent 60%), var(--bg); color: var(--text); font: 15px/1.6 system-ui, -apple-system, 'Segoe UI', sans-serif; }
.wrap { max-width: 760px; margin: 0 auto; padding: 32px 16px 64px; }
.logo { display: flex; align-items: center; gap: 12px; font-weight: 800; font-size: 20px; }
.logo i { width: 34px; height: 34px; border-radius: 9px; background: linear-gradient(90deg, #fbbf24, #f97316); display: grid; place-items: center; }
h1 { font-size: 28px; margin: 24px 0 4px; }
h2 { font-size: 17px; margin: 0 0 12px; }
p.lead { color: var(--muted); margin: 0 0 24px; }
.card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 20px; margin-bottom: 16px; }
.checks { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
.checks li { display: flex; gap: 10px; align-items: baseline; }
.checks b { width: 18px; flex: none; }
.checks .ok b { color: var(--ok); } .checks .bad b { color: var(--bad); } .checks .warn b { color: var(--warn); }
.checks small { color: var(--muted); }
.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.grid .full { grid-column: 1 / -1; }
label { display: grid; gap: 4px; font-size: 13px; font-weight: 600; color: #d4d4d8; }
label small { font-weight: 400; color: var(--muted); }
input { width: 100%; padding: 10px 12px; border-radius: 9px; border: 1px solid var(--line); background: #09090b; color: var(--text); font: inherit; }
input:focus { outline: none; border-color: var(--gold); }
details summary { cursor: pointer; font-weight: 700; }
details[open] summary { margin-bottom: 12px; }
.btn { display: inline-block; padding: 12px 22px; border: 0; border-radius: 10px; background: linear-gradient(90deg, #fbbf24, #f97316); color: #1c1003; font: 800 15px system-ui, sans-serif; cursor: pointer; text-decoration: none; }
.errors { border-color: #7f1d1d; background: #1f0f10; }
.errors li { color: #fecaca; }
code, pre { font: 13px/1.5 ui-monospace, Consolas, monospace; }
pre { white-space: pre-wrap; word-break: break-all; padding: 12px; border-radius: 9px; background: #09090b; border: 1px solid var(--line); margin: 8px 0 0; }
.note { color: var(--muted); font-size: 13px; }
@media (max-width: 560px) { .grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<div class="wrap">
  <div class="logo"><i><svg width="18" height="18" viewBox="0 0 24 24" fill="#1c1003" aria-hidden="true"><path d="M7 3h10v3h4v3a5 5 0 0 1-5 5h-.3A5 5 0 0 1 13 16.9V19h3v2H8v-2h3v-2.1A5 5 0 0 1 8.3 14H8a5 5 0 0 1-5-5V6h4z"/></svg></i>graj.club</div>

<?php if ($done) { ?>
  <h1>Готово! Сайт встановлено</h1>
  <p class="lead">Залишилося два кроки в панелі хостингу.</p>

  <div class="card">
    <h2>1. Додайте cron-задачу (щохвилини)</h2>
    <p class="note">Без неї не оновлюватиметься моніторинг серверів, рейтинг, черга листів і бекапи. У панелі: «Планувальник завдань» / «Cron».</p>
    <pre><?= $h($installer->cronLine()) ?></pre>
    <p class="note">Якщо в панелі кілька версій PHP, замініть <code>php</code> на шлях до PHP 8.2+ (наприклад <code>/usr/local/bin/php83</code>).</p>
  </div>

  <div class="card">
    <h2>2. Перевірте, що <code>install.php</code> видалено</h2>
    <p class="note"><?= is_file(__FILE__) ? 'Інсталятор не зміг видалити себе — видаліть файл public/install.php вручну через файловий менеджер.' : 'Інсталятор уже видалив себе.' ?> Повторно він не відкриється, бо створено storage/installed.</p>
  </div>

  <div class="card">
    <h2>Що зроблено</h2>
    <ul class="checks">
      <?php foreach ($log as [$name, $ok, $note]) { ?>
        <li class="<?= $ok ? 'ok' : 'warn' ?>"><b><?= $ok ? '✓' : '!' ?></b><span><?= $h($name) ?><?php if (! $ok) { ?> <small>— <?= $h($note) ?></small><?php } ?></span></li>
      <?php } ?>
    </ul>
  </div>

  <p><a class="btn" href="/admin">Увійти в адмінку</a></p>
  <p class="note">Під час першого входу адмінка попросить налаштувати двофакторний вхід. Ключі оплати, капчі, входу через Google/Discord і Telegram додаються в .env — список у DEPLOY.md.</p>

<?php } else { ?>
  <h1>Встановлення</h1>
  <p class="lead">Перевіримо сервер, підключимо базу MySQL і створимо адміністратора. Займе хвилину.</p>

  <div class="card">
    <h2>Сервер</h2>
    <ul class="checks">
      <?php foreach ($checks as [$name, $ok, $required, $fix]) { ?>
        <li class="<?= $ok ? 'ok' : ($required ? 'bad' : 'warn') ?>"><b><?= $ok ? '✓' : ($required ? '✗' : '!') ?></b><span><?= $h($name) ?><?php if (! $ok) { ?> <small>— <?= $h($fix) ?></small><?php } ?></span></li>
      <?php } ?>
    </ul>
    <?php if (! $ready) { ?><p class="note" style="margin-top:12px">Виправте пункти з ✗ і оновіть сторінку.</p><?php } ?>
  </div>

  <?php if ($errors) { ?>
    <div class="card errors"><ul><?php foreach ($errors as $e) { ?><li><?= $h($e) ?></li><?php } ?></ul>
      <?php if ($log) { ?><ul class="checks" style="margin-top:12px"><?php foreach ($log as [$name, $ok, $note]) { ?><li class="<?= $ok ? 'ok' : 'bad' ?>"><b><?= $ok ? '✓' : '✗' ?></b><span><?= $h($name) ?><?php if (! $ok) { ?> <small>— <?= $h($note) ?></small><?php } ?></span></li><?php } ?></ul><?php } ?>
    </div>
  <?php } ?>

  <?php if ($ready) { ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="_token" value="<?= $h($_SESSION['install_token']) ?>">
    <div class="card">
      <h2>Сайт</h2>
      <label>Адреса сайту <small>з https://, без скісної риски в кінці</small><input name="app_url" value="<?= $h($in['app_url']) ?>" required></label>
    </div>
    <div class="card">
      <h2>База даних MySQL</h2>
      <p class="note" style="margin-top:-6px">Створіть порожню базу й користувача в панелі хостингу («Бази даних MySQL») і вкажіть їх тут.</p>
      <div class="grid">
        <label>Хост<input name="db_host" value="<?= $h($in['db_host']) ?>" required></label>
        <label>Порт<input name="db_port" value="<?= $h($in['db_port']) ?>" inputmode="numeric" required></label>
        <label class="full">Назва бази<input name="db_database" value="<?= $h($in['db_database']) ?>" required></label>
        <label>Користувач<input name="db_username" value="<?= $h($in['db_username']) ?>" required></label>
        <label>Пароль<input name="db_password" type="password" value="<?= $h($in['db_password']) ?>"></label>
      </div>
    </div>
    <div class="card">
      <h2>Адміністратор</h2>
      <div class="grid">
        <label>Email<input name="admin_email" type="email" value="<?= $h($in['admin_email']) ?>" required></label>
        <label>Нік <small>необов'язково</small><input name="admin_name" value="<?= $h($in['admin_name']) ?>"></label>
        <label>Пароль <small>щонайменше 12 символів</small><input name="admin_password" type="password" minlength="12" required></label>
        <label>Повторіть пароль<input name="admin_password2" type="password" minlength="12" required></label>
      </div>
    </div>
    <div class="card">
      <details <?= $in['mail_host'] !== '' ? 'open' : '' ?>>
        <summary>Пошта (SMTP) — можна налаштувати пізніше</summary>
        <p class="note">Без пошти не приходитимуть листи підтвердження email і скидання пароля. Дані — у панелі хостингу, розділ «Пошта».</p>
        <div class="grid">
          <label>SMTP-сервер<input name="mail_host" value="<?= $h($in['mail_host']) ?>" placeholder="mail.ваш-домен"></label>
          <label>Порт<input name="mail_port" value="<?= $h($in['mail_port']) ?>" inputmode="numeric"></label>
          <label>Логін<input name="mail_username" value="<?= $h($in['mail_username']) ?>"></label>
          <label>Пароль<input name="mail_password" type="password" value="<?= $h($in['mail_password']) ?>"></label>
          <label class="full">Адреса відправника<input name="mail_from" type="email" value="<?= $h($in['mail_from']) ?>" placeholder="noreply@ваш-домен"></label>
        </div>
      </details>
    </div>
    <p><button class="btn" type="submit">Встановити</button></p>
    <p class="note">Якщо в корені вже є .env, він збережеться як .env.backup.</p>
  </form>
  <?php } ?>
<?php } ?>
</div>
</body>
</html>
