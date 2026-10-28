<?php

namespace App\Console\Commands;

use App\Billing\GatewayRegistry;
use App\Models\Game;
use App\Models\Page;
use App\Models\Server;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Передстартова перевірка робочого сервера (ТЗ 4, реліз): усе, що можна перевірити автоматично.
 * ✗ — блокує запуск, ! — бажано виправити, ✓ — гаразд. Ручні кроки — у RELEASE.md.
 */
class ReleaseCheck extends Command
{
    protected $signature = 'release:check';

    protected $description = 'Перевірити готовність сервера до запуску (ключі, cron, база, пошта, оплата, наповнення)';

    /** @var list<array{string, string, string}> [рівень, перевірка, пояснення] */
    private array $results = [];

    public function handle(GatewayRegistry $gateways): int
    {
        $this->environment();
        $this->database();
        $this->filesystem();
        $this->scheduler();
        $this->mail();
        $this->payments($gateways);
        $this->protection();
        $this->content();

        $icons = ['ok' => '<fg=green>✓</>', 'warn' => '<fg=yellow>!</>', 'fail' => '<fg=red>✗</>'];
        foreach ($this->results as [$level, $name, $note]) {
            $this->line(" {$icons[$level]} {$name}".($note !== '' ? " <fg=gray>— {$note}</>" : ''));
        }

        $count = fn (string $level) => count(array_filter($this->results, fn ($r) => $r[0] === $level));
        $this->newLine();
        $this->line(sprintf('Гаразд: %d, бажано виправити: %d, блокує запуск: %d', $count('ok'), $count('warn'), $count('fail')));

        return $count('fail') ? self::FAILURE : self::SUCCESS;
    }

    private function check(bool $ok, string $name, string $fix, string $level = 'fail'): void
    {
        $this->results[] = [$ok ? 'ok' : $level, $name, $ok ? '' : $fix];
    }

    private function environment(): void
    {
        $this->check(app()->isProduction(), 'APP_ENV=production', 'інакше robots.txt закриває сайт від пошукових систем, а тестова оплата доступна');
        $this->check(! config('app.debug'), 'APP_DEBUG=false', 'з увімкненим налагодженням відвідувачі бачать стек помилок і ключі');
        $this->check(filled(config('app.key')), 'APP_KEY задано', 'php artisan key:generate');
        $this->check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL з https://', 'посилання в листах, sitemap і canonical беруться звідси');
        $this->check((bool) config('session.secure'), 'SESSION_SECURE_COOKIE=true', 'cookie сесії мають ходити лише через HTTPS', 'warn');
        $this->check((bool) config('graj.security.require_staff_2fa'), 'REQUIRE_STAFF_2FA=true', 'модератори й адміністратори мають входити з 2FA', 'warn');
        $this->check(app()->configurationIsCached(), 'Конфігурацію закешовано', 'php artisan optimize після кожного оновлення', 'warn');
    }

    private function database(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->check(false, 'Підключення до бази', $e->getMessage());

            return;
        }

        $this->check(in_array(DB::getDriverName(), ['mysql', 'mariadb'], true), 'База MySQL/MariaDB', 'SQLite лише для розробки', 'warn');

        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]);
        $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());
        $this->check(! $pending, 'Усі міграції виконано', 'php artisan migrate --force (очікують: '.count($pending).')');
    }

    private function filesystem(): void
    {
        $this->check(is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')), 'storage/ і bootstrap/cache доступні для запису', 'права 775 на ці теки');
        $this->check(file_exists(public_path('storage')), 'Посилання public/storage', 'php artisan storage:link — інакше не видно логотипів і скріншотів');
        $this->check(file_exists(public_path('build/manifest.json')), 'Зібрані стилі й скрипти (public/build)', 'npm run build або деплой через GitHub Actions');

        $last = collect(File::glob(storage_path('app/private/backups/db-*.sql.gz')))->map(fn ($f) => File::lastModified($f))->max();
        $this->check($last && $last > now()->subHours(26)->getTimestamp(), 'Свіжий бекап бази (до 26 год)', $last ? 'останній '.date('Y-m-d H:i', $last).' — перевірте cron' : 'ще не було: php artisan db:backup', 'warn');
        $this->check(filled(config('graj.backup.sftp.host')), 'Копія бекапу поза сервером', 'BACKUP_SFTP_* у .env — інакше бекап зникне разом із хостингом', 'warn');
    }

    private function scheduler(): void
    {
        $beat = Cache::get('schedule:heartbeat');
        $this->check($beat && $beat > now()->subMinutes(3)->getTimestamp(), 'Cron запускає schedule:run', $beat ? 'останній запуск '.date('Y-m-d H:i', $beat) : 'ще жодного запуску: додайте cron-задачу з DEPLOY.md');

        try {
            $stuck = DB::table('jobs')->where('created_at', '<', now()->subMinutes(10)->getTimestamp())->count();
            $failed = DB::table('failed_jobs')->where('failed_at', '>', now()->subDay())->count();
            $this->check($stuck === 0, 'Черга розбирається', "завдань старших за 10 хв: {$stuck}", 'warn');
            $this->check($failed === 0, 'Без збоїв черги за добу', "збоїв: {$failed} — php artisan queue:failed", 'warn');
        } catch (Throwable) {
            // таблиць ще немає — про це вже сказали міграції
        }
    }

    private function mail(): void
    {
        $this->check(! in_array(config('mail.default'), ['log', 'array'], true), 'Пошта надсилається (MAIL_MAILER)', 'без SMTP не прийдуть листи підтвердження email і скидання пароля');
        $this->check(! str_ends_with((string) config('mail.from.address'), '@example.com'), 'Адреса відправника', 'MAIL_FROM_ADDRESS, наприклад noreply@graj.club');
    }

    private function payments(GatewayRegistry $gateways): void
    {
        $available = array_keys(array_diff_key($gateways->available(), ['test' => true]));
        $this->check((bool) $available, 'Платіжна система налаштована', 'ключі LiqPay або Stripe — без них платні послуги не купити', 'warn');
        if (filled(config('services.liqpay.public_key'))) {
            $this->check(! config('services.liqpay.sandbox'), 'LiqPay у бойовому режимі', 'LIQPAY_SANDBOX=false, інакше гроші не списуються, а послуги видаються');
        }
        if (filled(config('services.stripe.secret'))) {
            $this->check(! str_starts_with((string) config('services.stripe.secret'), 'sk_test_') && ! str_starts_with((string) config('services.stripe.secret'), 'rk_test_'), 'Stripe: бойовий ключ', 'зараз тестовий ключ');
        }
    }

    private function protection(): void
    {
        $this->check(filled(config('services.turnstile.secret')), 'Капча Turnstile', 'без неї голоси накручують ботами', 'warn');
        $this->check(filled(config('services.ipqs.key')), 'Виявлення VPN (IPQS)', 'без нього голоси через VPN не відсіюються', 'warn');
        $this->check(filled(config('sentry.dsn')), 'Sentry для звітів про помилки', 'SENTRY_LARAVEL_DSN — інакше помилки видно лише в логах', 'warn');
    }

    private function content(): void
    {
        try {
            $this->check(Game::where('is_active', true)->exists(), 'Є активні ігри', 'php artisan db:seed --force');
            $this->check(Tariff::exists(), 'Тарифи платних послуг', 'php artisan db:seed --force', 'warn');
            foreach (['rules' => 'Правила', 'privacy' => 'Політика конфіденційності'] as $slug => $title) {
                $this->check(Page::where('slug', $slug)->where('is_published', true)->exists(), "Сторінка «{$title}»", "опублікуйте /page/{$slug} в адмінці");
            }
            $this->check(User::role('admin')->exists(), 'Є адміністратор', 'php artisan user:make-admin email@домен');
            $this->check(! Server::where('host', 'like', '%.example.net')->exists(), 'Немає демо-серверів', 'видаліть сервери з адресою *.example.net');
        } catch (Throwable $e) {
            $this->check(false, 'Наповнення', $e->getMessage());
        }
    }
}
