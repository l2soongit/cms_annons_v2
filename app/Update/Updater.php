<?php

namespace App\Update;

use FilesystemIterator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Оновлення сайту на віртуальному хостингу з архівів GitHub Releases (або архіву, завантаженого вручну).
 *
 * Два етапи в різних процесах PHP, бо після заміни файлів поточний процес тримає в пам'яті старий код:
 *  1. stage()    — завантажити архів, перевірити, зробити резервну копію коду, замінити файли (.env і storage/ не чіпаються);
 *  2. finalize() — у новому процесі: міграції й кеш (optimize).
 * Між етапами в storage лежить позначка pending — cron (site:update --tick) або адмінка завершують оновлення.
 *
 * Стан (автооновлення, черга, історія) — у storage/app/private/updates/state.json: працює навіть без бази.
 */
final class Updater
{
    /** Шляхи, які оновлення ніколи не перезаписує */
    private const KEEP = ['.env', '.env.backup', 'storage', 'public/storage', 'public/install.php'];

    /** Що входить у резервну копію коду (для відкату) */
    private const CODE = ['app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes', 'vendor', 'artisan', 'composer.json', 'composer.lock', 'VERSION', '.htaccess', '.env.production.example'];

    private const BACKUPS_KEEP = 2;

    private const HISTORY_KEEP = 20;

    private readonly string $base;

    public function __construct(?string $base = null)
    {
        $this->base = rtrim(str_replace('\\', '/', $base ?? base_path()), '/');
    }

    // ── Стан ──

    public function dir(): string
    {
        $dir = $this->base.'/storage/app/private/updates';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /** @return array<string, mixed> */
    public function state(): array
    {
        $file = $this->dir().'/state.json';

        return is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    }

    /** @param  array<string, mixed>  $changes */
    public function setState(array $changes): void
    {
        $state = array_merge($this->state(), $changes);
        file_put_contents($this->dir().'/state.json', json_encode(array_filter($state, fn ($v) => $v !== null), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    public function currentVersion(): string
    {
        $file = $this->base.'/VERSION';

        return is_file($file) ? trim((string) file_get_contents($file)) : 'dev';
    }

    /** Робоча копія git (розробка): оновлюватися треба через git pull, а не архівом */
    public function isGitCheckout(): bool
    {
        return is_dir($this->base.'/.git');
    }

    public function autoEnabled(): bool
    {
        return (bool) ($this->state()['auto'] ?? false);
    }

    public function setAuto(bool $on): void
    {
        $this->setState(['auto' => $on]);
    }

    public function pending(): ?array
    {
        return $this->state()['pending'] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function history(): array
    {
        return $this->state()['history'] ?? [];
    }

    // ── Реліз на GitHub ──

    /**
     * Останній реліз: tag, name, notes, published_at, url (архів), size. Кешується в стані.
     *
     * @return array{tag: string, name: string, notes: string, published_at: string, url: string, size: int}
     */
    public function latest(): array
    {
        $repo = (string) config('graj.update.repo');
        $res = $this->github()->get("https://api.github.com/repos/{$repo}/releases/latest");
        if ($res->status() === 404) {
            throw new RuntimeException("У репозиторії {$repo} ще немає релізів (або він приватний і потрібен UPDATE_TOKEN).");
        }
        $res->throw();

        $asset = collect($res->json('assets', []))->first(fn ($a) => str_ends_with($a['name'] ?? '', '.zip'));
        if (! $asset) {
            throw new RuntimeException('У релізі '.$res->json('tag_name').' немає архіву .zip.');
        }

        $latest = [
            'tag' => (string) $res->json('tag_name'),
            'name' => (string) ($res->json('name') ?: $res->json('tag_name')),
            'notes' => (string) $res->json('body'),
            'published_at' => (string) $res->json('published_at'),
            // Для приватного репозиторію архів віддає API-адреса з Accept: application/octet-stream
            'url' => config('graj.update.token') ? (string) $asset['url'] : (string) $asset['browser_download_url'],
            'size' => (int) ($asset['size'] ?? 0),
        ];
        $this->setState(['latest' => $latest, 'last_check' => time()]);

        return $latest;
    }

    public function hasUpdate(?array $latest = null): bool
    {
        $latest ??= $this->state()['latest'] ?? null;

        return $latest && $latest['tag'] !== $this->currentVersion();
    }

    private function github(): PendingRequest
    {
        $http = Http::withHeaders(['User-Agent' => 'graj.club-updater', 'Accept' => 'application/vnd.github+json'])->timeout(30);

        return ($token = config('graj.update.token')) ? $http->withToken((string) $token) : $http;
    }

    public function download(array $release): string
    {
        $file = $this->dir().'/'.preg_replace('/[^A-Za-z0-9.\-]/', '_', $release['tag']).'.zip';
        $this->github()->withHeaders(['Accept' => 'application/octet-stream'])->timeout(600)->sink($file)->get($release['url'])->throw();

        return $file;
    }

    // ── Етап 1: заміна файлів ──

    /**
     * Перевіряє архів, робить резервну копію поточного коду й замінює файли. Повертає версію з архіву.
     */
    public function stage(string $zipFile, string $reason = 'manual'): string
    {
        if ($this->isGitCheckout()) {
            throw new RuntimeException('Це робоча копія git — оновлюйтеся через git pull.');
        }
        if ($this->pending()) {
            throw new RuntimeException('Попереднє оновлення ще не завершено.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipFile) !== true) {
            throw new RuntimeException('Архів пошкоджено або це не zip.');
        }
        foreach (['artisan', 'vendor/autoload.php', 'public/index.php', 'VERSION'] as $required) {
            if ($zip->locateName($required) === false) {
                $zip->close();
                throw new RuntimeException("Це не архів graj.club: немає {$required}.");
            }
        }
        $version = trim((string) $zip->getFromName('VERSION'));

        $extract = $this->dir().'/extract';
        $this->remove($extract);
        if (! $zip->extractTo($extract)) {
            $zip->close();
            throw new RuntimeException('Не вдалося розпакувати архів — перевірте вільне місце на хостингу.');
        }
        $zip->close();

        $from = $this->currentVersion();
        $backup = $this->backup($from);

        $this->setState(['pending' => ['from' => $from, 'to' => $version, 'backup' => $backup, 'reason' => $reason, 'at' => time()]]);
        $this->copyTree($extract, $this->base);
        $this->remove($extract);

        // Кеш конфігурації й маршрутів зі старим кодом більше не годиться
        foreach (glob($this->base.'/bootstrap/cache/*.php') ?: [] as $cached) {
            if (! in_array(basename($cached), ['packages.php', 'services.php'], true)) {
                @unlink($cached);
            }
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return $version;
    }

    /** Резервна копія коду (без .env, storage і завантажень) для відкату */
    private function backup(string $version): string
    {
        $file = $this->dir().'/backup-'.preg_replace('/[^A-Za-z0-9.\-]/', '_', $version).'-'.date('Ymd-His').'.zip';
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach (self::CODE as $path) {
            $full = $this->base.'/'.$path;
            if (is_file($full)) {
                $zip->addFile($full, $path);
            } elseif (is_dir($full)) {
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));
                foreach ($it as $item) {
                    $rel = substr(str_replace('\\', '/', $item->getPathname()), strlen($this->base) + 1);
                    if ($item->isFile() && ! $this->kept($rel) && ! preg_match('#^bootstrap/cache/(?!packages|services)#', $rel)) {
                        $zip->addFile($item->getPathname(), $rel);
                    }
                }
            }
        }
        $zip->close();

        // Тримаємо лише кілька останніх копій: кожна важить десятки мегабайтів
        $all = glob($this->dir().'/backup-*.zip') ?: [];
        usort($all, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($all, self::BACKUPS_KEEP) as $old) {
            @unlink($old);
        }

        return basename($file);
    }

    private function kept(string $rel): bool
    {
        foreach (self::KEEP as $keep) {
            if ($rel === $keep || str_starts_with($rel, $keep.'/')) {
                return true;
            }
        }

        return false;
    }

    private function copyTree(string $from, string $to): void
    {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $item) {
            $rel = substr(str_replace('\\', '/', $item->getPathname()), strlen(str_replace('\\', '/', $from)) + 1);
            if ($this->kept($rel)) {
                continue;
            }
            $target = $to.'/'.$rel;
            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } elseif (! @copy($item->getPathname(), $target)) {
                throw new RuntimeException("Не вдалося записати {$rel} — перевірте права на файли.");
            }
        }
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }
        if (! is_dir($path)) {
            return;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }

    // ── Етап 2: міграції й кеш (у новому процесі) ──

    /** @return list<string> журнал */
    public function finalize(): array
    {
        $pending = $this->pending();
        if (! $pending) {
            return [];
        }

        $log = [];
        $ok = true;
        $message = '';
        foreach ([['migrate', ['--force' => true]], ['optimize:clear', []], ['optimize', []]] as [$command, $params]) {
            try {
                $code = Artisan::call($command, $params);
                $log[] = "{$command}: ".($code === 0 ? 'гаразд' : "код {$code}");
                if ($code !== 0) {
                    throw new RuntimeException(trim(Artisan::output()));
                }
            } catch (Throwable $e) {
                $ok = false;
                $message = "{$command}: ".$e->getMessage();
                $log[] = $message;
                break;
            }
        }

        $history = $this->history();
        array_unshift($history, ['from' => $pending['from'], 'to' => $pending['to'], 'reason' => $pending['reason'] ?? 'manual', 'at' => time(), 'ok' => $ok, 'message' => $message, 'backup' => $pending['backup'] ?? null]);
        $this->setState(['pending' => null, 'history' => array_slice($history, 0, self::HISTORY_KEEP)]);

        return $log;
    }

    /**
     * Завершити оновлення в окремому процесі PHP одразу (cron/SSH). Якщо proc_open заборонено —
     * завершить наступний запуск site:update --tick (протягом хвилини).
     */
    public function finalizeInNewProcess(): bool
    {
        if (! function_exists('proc_open') || PHP_SAPI !== 'cli') {
            return false;
        }
        try {
            $process = new Process([PHP_BINARY, $this->base.'/artisan', 'site:update', '--finalize'], $this->base, null, null, 600);

            return $process->run() === 0;
        } catch (Throwable) {
            return false;
        }
    }

    // ── Сценарії ──

    /** Оновити до останнього релізу. Повертає нову версію або null, якщо оновлень немає */
    public function updateToLatest(string $reason = 'manual'): ?string
    {
        $latest = $this->latest();
        if (! $this->hasUpdate($latest)) {
            return null;
        }

        $zip = $this->download($latest);
        try {
            return $this->stage($zip, $reason);
        } finally {
            @unlink($zip);
        }
    }

    /** Відкат до коду з останньої резервної копії */
    public function rollback(): string
    {
        $last = collect($this->history())->first(fn ($h) => ! empty($h['backup']) && is_file($this->dir().'/'.$h['backup']));
        if (! $last) {
            throw new RuntimeException('Немає резервної копії для відкату.');
        }

        // Копія відкату сама стане резервною — переносимо її, щоб backup() її не видалив
        $file = $this->dir().'/rollback.zip';
        copy($this->dir().'/'.$last['backup'], $file);
        try {
            return $this->stage($file, 'rollback');
        } finally {
            @unlink($file);
        }
    }

    /** Поставити оновлення в чергу для cron (кнопка в адмінці): tag або шлях до завантаженого архіву */
    public function queue(array $job): void
    {
        $this->setState(['queued' => $job + ['at' => time()]]);
    }

    /** Чи працює cron (той самий «пульс», що перевіряє release:check) */
    public static function cronAlive(): bool
    {
        $beat = cache('schedule:heartbeat');

        return $beat && $beat > now()->subMinutes(3)->getTimestamp();
    }
}
