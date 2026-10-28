<?php

namespace App\Console\Commands;

use App\Backup\BackupTarget;
use App\Backup\DatabaseDumper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Щоденний бекап бази (ТЗ 4): дамп у storage/app/private/backups, ротація N днів,
 * копія поза сервером по SFTP, якщо вона налаштована (BACKUP_SFTP_HOST).
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--no-upload : Лише локальна копія}';

    protected $description = 'Дамп бази з ротацією і копією поза сервер';

    public function handle(DatabaseDumper $dumper): int
    {
        $dir = storage_path('app/private/backups');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/db-'.now()->format('Y-m-d-His').'.sql.gz';
        $before = now()->subDays((int) config('graj.backup.keep_days'));

        $dumper->dump($file);
        $this->info('Дамп: '.basename($file).' ('.number_format(filesize($file) / 1024, 1).' KB)');

        $pruned = collect(File::glob($dir.'/db-*.sql.gz'))
            ->filter(fn ($old) => File::lastModified($old) < $before->getTimestamp())
            ->each(fn ($old) => File::delete($old))
            ->count();
        $this->info("Видалено старих локальних копій: {$pruned}");

        if ($this->option('no-upload') || ! app()->bound(BackupTarget::class)) {
            $this->warn('Копія поза сервер не налаштована (BACKUP_SFTP_HOST)');

            return self::SUCCESS;
        }

        try {
            $target = app(BackupTarget::class);
            $target->upload($file);
            $this->info('Завантажено поза сервер; видалено старих віддалених копій: '.$target->prune($before));
        } catch (Throwable $e) {
            // Локальна копія вже є — помилку віддаємо в Sentry/лог і позначаємо запуск як невдалий
            report($e);
            $this->error('Не вдалося завантажити копію: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
