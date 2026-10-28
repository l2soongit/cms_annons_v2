<?php

namespace App\Support;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Переклади з адмінки (таблиця translations) поверх файлів lang/: обгортка над стандартним завантажувачем.
 * Порожнє значення в адмінці = текст з файлу; нова мова без перекладу бере резервну (fallback_locale).
 */
final class Translations implements Loader
{
    public function __construct(private Loader $files) {}

    public function load($locale, $group, $namespace = null): array
    {
        $lines = $this->files->load($locale, $group, $namespace);

        if (($namespace !== null && $namespace !== '*') || $group === '*') {
            return $lines;
        }

        foreach (self::overrides($locale) as $key => $value) {
            if (str_starts_with($key, $group.'.')) {
                Arr::set($lines, substr($key, strlen($group) + 1), $value);
            }
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->files->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->files->addJsonPath($path);
    }

    public function namespaces(): array
    {
        return $this->files->namespaces();
    }

    /** @return array<string, string> група.ключ => текст */
    public static function overrides(string $locale): array
    {
        try {
            return Cache::rememberForever("translations.{$locale}", fn () => Schema::hasTable('translations')
                ? DB::table('translations')->where('locale', $locale)->pluck('value', 'key')->all()
                : []);
        } catch (\Throwable) {
            // БД ще недоступна (встановлення, збірка) — лише файли
            return [];
        }
    }

    /** Зберегти переклад; порожній текст повертає значення з файлу */
    public static function save(string $locale, string $key, ?string $value): void
    {
        $value = trim((string) $value);
        if ($value === '') {
            DB::table('translations')->where(['locale' => $locale, 'key' => $key])->delete();
        } else {
            DB::table('translations')->updateOrInsert(['locale' => $locale, 'key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        }

        Cache::forget("translations.{$locale}");
        // Переклади кешуються перекладачем на час запиту — скидаємо, щоб зміна була видна одразу
        app('translator')->setLoaded([]);
    }

    /**
     * Рядки групи з файлу як група.ключ => текст (лише текстові значення, без списків).
     *
     * @return array<string, string>
     */
    public static function fileLines(string $locale, string $group): array
    {
        $path = lang_path("{$locale}/{$group}.php");
        $lines = is_file($path) ? (array) require $path : [];

        return collect(Arr::dot($lines))
            ->filter(fn ($v, $k) => is_string($v) && ! preg_match('/\.\d+(\.|$)/', (string) $k))
            ->mapWithKeys(fn ($v, $k) => ["{$group}.{$k}" => $v])
            ->all();
    }

    /** @return list<string> групи перекладів (файли основної мови) */
    public static function groups(): array
    {
        return collect(glob(lang_path(Locales::default().'/*.php')) ?: [])
            ->map(fn ($f) => basename($f, '.php'))
            ->reject(fn ($g) => in_array($g, ['validation', 'passwords'], true))
            ->values()->all();
    }
}
