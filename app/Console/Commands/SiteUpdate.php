<?php

namespace App\Console\Commands;

use App\Update\Updater;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Оновлення сайту з GitHub Releases.
 *
 *   php artisan site:update            — оновити до останнього релізу зараз (SSH)
 *   php artisan site:update --check    — лише показати, чи є оновлення
 *   php artisan site:update --rollback — повернути код з останньої резервної копії
 *   php artisan site:update --finalize — міграції й кеш після заміни файлів (запускається сам)
 *   php artisan site:update --tick     — щохвилини з cron: завершити оновлення, виконати чергу з адмінки, автооновлення
 */
class SiteUpdate extends Command
{
    protected $signature = 'site:update {--check} {--rollback} {--finalize} {--tick}';

    protected $description = 'Оновити сайт до останнього релізу з GitHub (або відкотити)';

    public function handle(Updater $updater): int
    {
        if ($this->option('finalize')) {
            foreach ($updater->finalize() as $line) {
                $this->line($line);
            }

            return self::SUCCESS;
        }

        if ($this->option('tick')) {
            return $this->tick($updater);
        }

        // Одночасно оновлюватися може лише один процес
        $lock = Cache::lock('site:update', 1800);
        if (! $lock->get()) {
            $this->warn('Оновлення вже виконується.');

            return self::FAILURE;
        }

        try {
            if ($this->option('check')) {
                $latest = $updater->latest();
                $this->line("Зараз: {$updater->currentVersion()}, останній реліз: {$latest['tag']}");
                $this->line($updater->hasUpdate($latest) ? 'Є оновлення.' : 'Оновлень немає.');

                return self::SUCCESS;
            }

            $version = $this->option('rollback') ? $updater->rollback() : $updater->updateToLatest('manual');
            if ($version === null) {
                $this->info("Оновлень немає ({$updater->currentVersion()}).");

                return self::SUCCESS;
            }
            $this->info("Файли замінено на {$version}. Міграції й кеш…");
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }

        if (! $updater->finalizeInNewProcess()) {
            $this->warn('Міграції виконає наступний запуск cron (site:update --tick).');
        }

        return self::SUCCESS;
    }

    /** Щохвилинний запуск із планувальника */
    private function tick(Updater $updater): int
    {
        if ($updater->isGitCheckout()) {
            return self::SUCCESS;
        }

        // 1. Файли вже замінено попереднім запуском — у цьому процесі вже новий код
        if ($updater->pending()) {
            foreach ($updater->finalize() as $line) {
                $this->line($line);
            }

            return self::SUCCESS;
        }

        $state = $updater->state();
        $queued = $state['queued'] ?? null;
        $due = $updater->autoEnabled() && (int) ($state['last_check'] ?? 0) < time() - config('graj.update.check_minutes') * 60;
        if (! $queued && ! $due) {
            return self::SUCCESS;
        }

        $lock = Cache::lock('site:update', 1800);
        if (! $lock->get()) {
            return self::SUCCESS;
        }

        try {
            $updater->setState(['queued' => null, 'error' => null]);
            $version = match (true) {
                ($queued['type'] ?? null) === 'rollback' => $updater->rollback(),
                ($queued['type'] ?? null) === 'file' => $this->fromFile($updater, (string) $queued['file']),
                default => $updater->updateToLatest($queued ? 'manual' : 'auto'),
            };
        } catch (Throwable $e) {
            $updater->setState(['error' => $e->getMessage(), 'error_at' => time()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }

        if ($version !== null) {
            $this->info("Файли замінено на {$version}.");
            $updater->finalizeInNewProcess();
        }

        return self::SUCCESS;
    }

    private function fromFile(Updater $updater, string $file): string
    {
        try {
            return $updater->stage($file, 'upload');
        } finally {
            @unlink($file);
        }
    }
}
