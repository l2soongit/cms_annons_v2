<?php

namespace App\Support;

/**
 * Тексти й перемикачі головної сторінки з config('graj.home'), куди Settings підставляє значення з адмінки.
 */
final class HomeContent
{
    public const SECTIONS = [
        'arena' => 'Топ місяця на першому екрані',
        'marquee' => 'Стрічка серверів онлайн',
        'games' => 'Ігри',
        'ranking' => 'Таблиця лідерів',
        'soon' => 'Скоро відкриття',
        'owners' => 'Власникам серверів',
        'plans' => 'Тарифи',
        'cta' => 'Заклик додати сервер',
    ];

    public const LOCALIZED = ['hero_title', 'hero_accent', 'hero_lead', 'owners_title', 'owners_lead', 'cta_title', 'cta_text'];

    /** Текст поточною мовою; порожній переклад замінюється текстом основною мовою */
    public static function text(string $key): string
    {
        $value = config("graj.home.{$key}");

        if (! is_array($value)) {
            return (string) $value;
        }

        return (string) (($value[app()->getLocale()] ?? null) ?: ($value[Locales::default()] ?? ''));
    }

    public static function shows(string $section): bool
    {
        return in_array($section, (array) config('graj.home.sections'), true);
    }

    /** @return list<string> */
    /**
     * Список мовою сторінки («Часто шукають», підказки пошуку): мова => пункти; порожній переклад — пункти основною мовою.
     * Старе збереження одним списком на всі мови — це список основної мови.
     */
    public static function list(string $key): array
    {
        return self::clean(self::byLocale($key)[app()->getLocale()] ?? []) ?: self::clean(self::byLocale($key)[Locales::default()] ?? []);
    }

    /** @return array<string, list<string>> */
    public static function byLocale(string $key): array
    {
        $value = (array) config("graj.home.{$key}");

        return array_is_list($value) ? [Locales::default() => $value] : $value;
    }

    /** @return list<string> */
    private static function clean(mixed $items): array
    {
        return array_values(array_filter(array_map('trim', array_map('strval', (array) $items))));
    }
}
