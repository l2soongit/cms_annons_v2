<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Налаштування з адмінки (таблиця settings) поверх config/graj.php.
 * Ключ — шлях у конфігу без префікса graj, напр. 'voting.interval_hours'.
 */
final class Settings
{
    public const EDITABLE = [
        'voting.interval_hours',
        'voting.subnet_limit',
        'rating.weights.votes',
        'rating.weights.bonus',
        'rating.weights.online',
        'rating.weights.uptime',
        'monitoring.interval_minutes',
        'monitoring.archive_after_offline_days',
        'reviews.stop_words',
    ];

    /**
     * Застосувати збережені значення до config() — викликається при старті застосунку.
     */
    public static function apply(): void
    {
        foreach (self::all() as $key => $value) {
            config(["graj.{$key}" => $value]);
        }
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $cached = Cache::get('graj.settings');
        if (is_array($cached)) {
            return $cached;
        }

        // Не кешуємо порожнє значення, поки міграції ще не створили таблицю
        if (! Schema::hasTable('settings')) {
            return [];
        }

        $values = DB::table('settings')
            ->whereIn('key', self::EDITABLE)
            ->pluck('value', 'key')
            ->map(fn ($v) => json_decode($v, true))
            ->all();

        Cache::forever('graj.settings', $values);

        return $values;
    }

    /** @param array<string, mixed> $values */
    public static function save(array $values): void
    {
        foreach (array_intersect_key($values, array_flip(self::EDITABLE)) as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value, JSON_UNESCAPED_UNICODE)]);
        }

        Cache::forget('graj.settings');
        self::apply();
    }
}
