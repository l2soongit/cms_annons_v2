<?php

namespace App\Install;

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use PDO;
use Throwable;

/**
 * Встановлення через браузер на віртуальному хостингу без SSH (public/install.php):
 * перевірка сервера, перевірка доступу до MySQL, запис .env, міграції й наповнення, перший адміністратор.
 * Після успіху створюється storage/installed — інсталятор більше не відкривається.
 */
final class Installer
{
    public const PHP_MIN = '8.2.0';

    /** Розширення PHP: обов'язкові й бажані (без sockets моніторинг серверів не працюватиме) */
    public const EXTENSIONS = ['pdo_mysql', 'mbstring', 'intl', 'openssl', 'fileinfo', 'gd', 'curl', 'zip', 'tokenizer', 'xml', 'dom', 'ctype', 'bcmath'];

    public const EXTENSIONS_OPTIONAL = ['sockets' => 'моніторинг серверів (UDP-запити)'];

    public function __construct(private readonly string $base) {}

    public function lockFile(): string
    {
        return $this->base.'/storage/installed';
    }

    public function isInstalled(): bool
    {
        return is_file($this->lockFile());
    }

    /**
     * Перевірка сервера: [назва, гаразд?, обов'язкова?, підказка]
     *
     * @return list<array{string, bool, bool, string}>
     */
    public function requirements(): array
    {
        $checks = [['PHP '.self::PHP_MIN.' або новіший (зараз '.PHP_VERSION.')', version_compare(PHP_VERSION, self::PHP_MIN, '>='), true, 'змініть версію PHP у панелі хостингу']];

        foreach (self::EXTENSIONS as $ext) {
            $checks[] = ["Розширення {$ext}", extension_loaded($ext), true, 'увімкніть у панелі хостингу (Вибір версії PHP → розширення)'];
        }
        foreach (self::EXTENSIONS_OPTIONAL as $ext => $why) {
            $checks[] = ["Розширення {$ext}", extension_loaded($ext), false, "потрібне для: {$why}"];
        }

        foreach (['storage', 'storage/framework', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $checks[] = ["Тека {$dir} доступна для запису", is_writable($this->base.'/'.$dir), true, 'права 755 або 775 на теку'];
        }
        $checks[] = ['Можна створити файл .env у корені проєкту', is_writable($this->base), true, 'права на запис для кореневої теки проєкту'];
        $checks[] = ['Зібрані стилі й скрипти (public/build)', is_file($this->base.'/public/build/manifest.json'), true, 'архів пошкоджено — завантажте його ще раз'];
        $checks[] = ['Залежності (vendor)', is_file($this->base.'/vendor/autoload.php'), true, 'архів пошкоджено — завантажте його ще раз'];
        $checks[] = ['Функція symlink для public/storage', function_exists('symlink'), false, 'без неї логотипи й скріншоти серверів не відкриватимуться; попросіть хостера дозволити symlink'];

        return $checks;
    }

    /** @param  list<array{string, bool, bool, string}>  $checks */
    public static function passes(array $checks): bool
    {
        foreach ($checks as [, $ok, $required]) {
            if ($required && ! $ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * Перевіряє доступ до MySQL. Повертає текст помилки або null.
     *
     * @param  array{host: string, port: string, database: string, username: string, password: string}  $db
     */
    public function testDatabase(array $db): ?string
    {
        try {
            $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset=utf8mb4", $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
            if ($tables > 0 && (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'migrations'")->fetchColumn() === 0) {
                return "У базі вже є {$tables} таблиць іншого сайту. Створіть порожню базу для graj.club.";
            }
        } catch (Throwable $e) {
            return 'Не вдалося підключитися до бази: '.$e->getMessage();
        }

        return null;
    }

    /**
     * Валідація форми. Повертає список помилок.
     *
     * @param  array<string, string>  $in
     * @return list<string>
     */
    public static function validate(array $in): array
    {
        $errors = [];
        if (! filter_var($in['app_url'] ?? '', FILTER_VALIDATE_URL) || ! preg_match('#^https?://#', $in['app_url'])) {
            $errors[] = 'Адреса сайту має починатися з https:// (або http://, якщо SSL ще немає).';
        }
        foreach (['db_host' => 'хост бази', 'db_database' => 'назву бази', 'db_username' => 'користувача бази'] as $key => $label) {
            if (trim($in[$key] ?? '') === '') {
                $errors[] = "Вкажіть {$label}.";
            }
        }
        if (! ctype_digit($in['db_port'] ?? '')) {
            $errors[] = 'Порт бази — число, зазвичай 3306.';
        }
        if (! filter_var($in['admin_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Вкажіть коректний email адміністратора.';
        }
        $password = $in['admin_password'] ?? '';
        if (mb_strlen($password) < 12) {
            $errors[] = 'Пароль адміністратора — щонайменше 12 символів.';
        } elseif ($password !== ($in['admin_password2'] ?? '')) {
            $errors[] = 'Паролі адміністратора не збігаються.';
        }
        if (($in['mail_host'] ?? '') !== '' && ! filter_var($in['mail_from'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Вкажіть адресу відправника листів, наприклад noreply@ваш-домен.';
        }

        return $errors;
    }

    /**
     * Вміст .env: шаблон .env.production.example із підставленими значеннями.
     * Ключі, яких немає в шаблоні, дописуються в кінець.
     *
     * @param  array<string, string>  $values
     */
    public static function envContents(string $template, array $values): string
    {
        // Не /\R/ без «u»: він розрізає кирилицю в коментарях (байт 0x85 у «х» — це NEL)
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $template));
        $done = [];
        foreach ($lines as &$line) {
            if (preg_match('/^([A-Z0-9_]+)=/', $line, $m) && array_key_exists($m[1], $values)) {
                $line = $m[1].'='.self::envValue($values[$m[1]]);
                $done[$m[1]] = true;
            }
        }
        unset($line);
        foreach (array_diff_key($values, $done) as $key => $value) {
            $lines[] = $key.'='.self::envValue($value);
        }

        return rtrim(implode("\n", $lines))."\n";
    }

    /** Значення в лапках, якщо в ньому пробіли, #, лапки чи $ (інакше .env прочитає його неправильно) */
    public static function envValue(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+=\-,]+$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    /**
     * Значення .env з форми.
     *
     * @param  array<string, string>  $in
     * @return array<string, string>
     */
    public static function envValues(array $in): array
    {
        $url = rtrim(trim($in['app_url']), '/');
        $https = str_starts_with($url, 'https://');
        $smtp = trim($in['mail_host'] ?? '') !== '';
        $port = trim($in['mail_port'] ?? '') ?: '465';

        return [
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $url,
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => trim($in['db_host']),
            'DB_PORT' => trim($in['db_port']),
            'DB_DATABASE' => trim($in['db_database']),
            'DB_USERNAME' => trim($in['db_username']),
            'DB_PASSWORD' => $in['db_password'] ?? '',
            // Без HTTPS захищене cookie сесії не дійде до сервера — вхід не працюватиме
            'SESSION_SECURE_COOKIE' => $https ? 'true' : 'false',
            'MAIL_MAILER' => $smtp ? 'smtp' : 'log',
            'MAIL_SCHEME' => $smtp && $port === '465' ? 'smtps' : 'smtp',
            'MAIL_HOST' => trim($in['mail_host'] ?? ''),
            'MAIL_PORT' => $port,
            'MAIL_USERNAME' => trim($in['mail_username'] ?? ''),
            'MAIL_PASSWORD' => $in['mail_password'] ?? '',
            'MAIL_FROM_ADDRESS' => trim($in['mail_from'] ?? '') ?: 'noreply@'.(parse_url($url, PHP_URL_HOST) ?: 'localhost'),
        ];
    }

    /**
     * Записує .env (старий зберігається як .env.backup).
     *
     * @param  array<string, string>  $values
     */
    public function writeEnv(array $values): void
    {
        $env = $this->base.'/.env';
        if (is_file($env)) {
            copy($env, $this->base.'/.env.backup');
        }
        $template = (string) file_get_contents($this->base.'/.env.production.example');
        file_put_contents($env, self::envContents($template, $values), LOCK_EX);
    }

    /**
     * Встановлення на вже записаному .env: міграції, наповнення, адміністратор, кеш. Повертає журнал кроків.
     *
     * @return list<array{string, bool, string}> [крок, успіх, деталі]
     */
    public function finish(string $adminEmail, string $adminPassword, string $adminName = ''): array
    {
        @set_time_limit(0);
        $log = [];

        // Свіжий застосунок, що читає щойно записаний .env
        $app = require $this->base.'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        $step = function (string $name, callable $fn, bool $critical = true) use (&$log): bool {
            try {
                $out = $fn();
                $log[] = [$name, true, is_string($out) ? trim($out) : ''];

                return true;
            } catch (Throwable $e) {
                $log[] = [$name, false, $e->getMessage()];
                if ($critical) {
                    throw new InstallFailed($log);
                }

                return false;
            }
        };

        $artisan = function (string $command, array $params = []): string {
            $code = Artisan::call($command, $params);
            if ($code !== 0) {
                throw new \RuntimeException(trim(Artisan::output()) ?: "{$command}: код {$code}");
            }

            return Artisan::output();
        };

        $step('Таблиці бази даних', fn () => $artisan('migrate', ['--force' => true]));
        $step('Ролі, ігри, тарифи й переклади', fn () => $artisan('db:seed', ['--force' => true]));
        $step('Адміністратор', function () use ($adminEmail, $adminPassword, $adminName) {
            $email = mb_strtolower(trim($adminEmail));
            $user = User::where('email', $email)->first() ?? User::forceCreate([
                'name' => $adminName !== '' ? $adminName : strstr($email, '@', true),
                'email' => $email,
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
            ]);
            $user->assignRole('admin');

            return $email;
        });
        $step('Посилання public/storage', fn () => $artisan('storage:link'), false);
        $step('Курси валют НБУ', fn () => $artisan('billing:rates'), false);
        $step('Кеш конфігурації й маршрутів', fn () => $artisan('optimize'), false);

        file_put_contents($this->lockFile(), date('c')."\n");

        return $log;
    }

    /** Рядок для cron у панелі хостингу */
    public function cronLine(): string
    {
        $php = is_file(PHP_BINDIR.'/php') ? PHP_BINDIR.'/php' : 'php';

        return "* * * * * cd {$this->base} && {$php} artisan schedule:run >> /dev/null 2>&1";
    }
}
