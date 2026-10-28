<?php

namespace App\Support;

/**
 * Оформлення сторінки Rust: колір ресурсу для рейту, фігурки людей для ліміту групи, короткий розмір мапи.
 */
final class RustLook
{
    private const RATES = ['vanilla' => '#9a8f7f', 'x1' => '#9a8f7f', 'x2' => '#c8a14a', 'x3' => '#c28a3a', 'x5' => '#d0702f', 'x10' => '#e2553f'];

    private const GROUPS = ['solo' => 1, 'duo' => 2, 'trio' => 3, 'quad' => 4, 'max-4' => 4, 'max-5' => 5];

    public static function rateColor(?string $value): string
    {
        return self::RATES[(string) $value] ?? '#b58a4a';
    }

    /** Скільки людей у групі; 0 — без ліміту (клан). Невідомі варіанти — за числом у назві («Max 6») */
    public static function groupSize(array $option): int
    {
        return self::GROUPS[$option['value'] ?? ''] ?? (preg_match('/(\d+)/', (string) ($option['label'] ?? ''), $m) ? min(6, (int) $m[1]) : 0);
    }

    /** Фігурки людей; без ліміту — три фігурки з «+» */
    public static function people(int $n): string
    {
        $person = '<svg viewBox="0 0 8 18" fill="currentColor" aria-hidden="true"><circle cx="4" cy="3" r="2.6"/><path d="M1 7h6l-.6 6H6v5H2v-5h-.4z"/></svg>';

        return $n > 0
            ? str_repeat($person, $n)
            : str_repeat($person, 3).'<svg viewBox="0 0 8 18" aria-hidden="true"><text x="0" y="13" font-size="10" fill="currentColor">+</text></svg>';
    }

    /** «4500» → «4,5к» (англійською «4.5k»), «5000+» → «5к+» */
    public static function size(string $label): string
    {
        if (! preg_match('/^(\d{4,5})(\+?)$/', trim($label), $m)) {
            return $label;
        }

        // «к» — тисячі українською й російською, «k» — іншими мовами
        $k = in_array(app()->getLocale(), ['uk', 'ru'], true) ? 'к' : 'k';
        $dot = app()->getLocale() === 'en' ? '.' : ',';

        return rtrim(rtrim(number_format((int) $m[1] / 1000, 1, $dot, ''), '0'), $dot).$k.$m[2];
    }
}
