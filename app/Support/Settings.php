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
    /** Сторінка «Налаштування порталу» */
    public const PORTAL = [
        'voting.interval_hours',
        'voting.subnet_limit',
        'rating.weights.votes',
        'rating.weights.bonus',
        'rating.weights.online',
        'rating.weights.uptime',
        'monitoring.interval_minutes',
        'monitoring.archive_after_offline_days',
        'reviews.stop_words',
        'locales',
        'default_locale',
        'billing.currency',
        'billing.min_deposit',
        'billing.max_deposit',
        'page_cache.enabled',
        'page_cache.ttl',
    ];

    /** Сторінка «Головна сторінка» */
    public const HOME = [
        'home.sections',
        'home.hero_title',
        'home.hero_accent',
        'home.hero_lead',
        'home.quick_links',
        'home.search_hints',
        'home.owners_title',
        'home.owners_lead',
        'home.cta_title',
        'home.cta_text',
        'home.ranking_size',
        'home.games_style',
        'home.live_feed',
        'home.hero_image',
        'home.hero_image_opacity',
        'home.cta_image',
        'home.logo_image',
        'home.og_image',
    ];

    /** Сторінка «Спільнота → Налаштування» */
    public const COMMUNITY = [
        'community.enabled',
        'community.moderate_replies',
        'community.newbie_posts',
        'community.edit_requires_review',
        'community.stop_words',
        'community.flag_links_for_newbies',
        'community.banned_domains',
        'community.topics_per_day',
        'community.posts_per_hour',
        'community.bonus.enabled',
        'community.bonus.topic',
        'community.bonus.server_news',
        'community.bonus.best_answer',
        'community.bonus.like',
        'community.bonus.daily_vote',
        'community.bonus.streak_7',
        'community.bonus.owner_reply',
        'community.bonus.daily_cap',
        'community.bonus.like_min_account_days',
        'community.bonus.point_value',
        'community.bonus.max_pay_share',
        'community.levels',
    ];

    public const EDITABLE = [...self::PORTAL, ...self::HOME, ...self::COMMUNITY];

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
        PageCache::flush();
    }
}
