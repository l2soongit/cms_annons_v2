<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Читання логів Laravel (storage/logs/*.log) для сторінки «Логи» в адмінці.
 * Великі файли читаються лише з кінця (TAIL_BYTES) — сторінка не завантажує весь лог у пам'ять.
 */
final class LogReader
{
    /** Скільки байтів з кінця файлу читати */
    public const TAIL_BYTES = 2 * 1024 * 1024;

    /** Рівні Monolog від найважливішого */
    public const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    public function __construct(private ?string $dir = null)
    {
        $this->dir ??= storage_path('logs');
    }

    /**
     * Файли логів, найновіші першими.
     *
     * @return list<array{name: string, size: int, modified: int}>
     */
    public function files(): array
    {
        if (! is_dir($this->dir)) {
            return [];
        }

        return collect(File::files($this->dir))
            ->filter(fn ($f) => $f->getExtension() === 'log')
            ->map(fn ($f) => ['name' => $f->getFilename(), 'size' => $f->getSize(), 'modified' => $f->getMTime()])
            ->sortByDesc('modified')
            ->values()->all();
    }

    /** Повний шлях до файлу логу або null, якщо такого файлу немає (захист від ../ у назві) */
    public function path(?string $name): ?string
    {
        if (! $name || $name !== basename($name) || ! str_ends_with($name, '.log')) {
            return null;
        }

        $path = $this->dir.DIRECTORY_SEPARATOR.$name;

        return is_file($path) ? $path : null;
    }

    /** Чи файл більший за прочитаний шматок (на сторінці — «показано останні 2 МБ») */
    public function truncated(string $name): bool
    {
        $path = $this->path($name);

        return $path !== null && filesize($path) > self::TAIL_BYTES;
    }

    /**
     * Записи логу, найновіші першими.
     *
     * @return array{entries: list<array{time: string, env: string, level: string, message: string, details: string}>, counts: array<string, int>}
     */
    public function read(string $name, ?string $level = null, string $search = '', int $limit = 100): array
    {
        $path = $this->path($name);
        if ($path === null) {
            return ['entries' => [], 'counts' => []];
        }

        $size = filesize($path);
        $handle = fopen($path, 'rb');
        if ($size > self::TAIL_BYTES) {
            fseek($handle, -self::TAIL_BYTES, SEEK_END);
        }
        $text = (string) stream_get_contents($handle);
        fclose($handle);

        // Кожен запис починається з рядка «[2026-11-02 16:12:01] production.ERROR: …»
        $parts = preg_split('/^(?=\[\d{4}-\d{2}-\d{2}[ T][^\]]*\] [\w.-]+\.[A-Z]+: )/m', $text);
        $entries = [];
        $counts = [];
        foreach (array_reverse($parts) as $raw) {
            if (! preg_match('/^\[([^\]]+)\] ([\w.-]+)\.([A-Z]+): (.*)$/s', $raw, $m)) {
                continue;
            }
            $lvl = strtolower($m[3]);
            $counts[$lvl] = ($counts[$lvl] ?? 0) + 1;

            if ($level !== null && $level !== '' && $lvl !== $level) {
                continue;
            }
            if ($search !== '' && mb_stripos($m[4], $search) === false) {
                continue;
            }
            if (count($entries) >= $limit) {
                continue;
            }

            // Перший рядок без JSON-контексту — коротке повідомлення; решта (контекст, стек) — деталі
            $body = rtrim($m[4]);
            $first = strtok($body, "\n");
            $message = preg_replace('/\s+\{.*\}\s*(\[\])?$/s', '', $first) ?: $first;
            $entries[] = [
                'time' => $m[1],
                'env' => $m[2],
                'level' => $lvl,
                'message' => mb_strimwidth($message, 0, 600, '…'),
                'details' => trim($body) === trim($message) ? '' : $body,
            ];
        }

        return ['entries' => $entries, 'counts' => $counts];
    }

    /** Очистити файл (не видаляючи, щоб логер і далі писав у нього) */
    public function clear(string $name): bool
    {
        $path = $this->path($name);

        return $path !== null && file_put_contents($path, '') !== false;
    }
}
