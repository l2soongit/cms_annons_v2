<?php

namespace App\Billing;

/**
 * Гроші рахуються в центах (копійках) цілими числами (без bcmath, якого може не бути на хостингу);
 * у базі — decimal(…, 2). Усі суми сайту — в основній валюті (graj.billing.currency, за замовчуванням USD).
 */
final class Money
{
    public const SYMBOLS = ['UAH' => '₴', 'USD' => '$', 'EUR' => '€', 'RUB' => '₽'];

    public static function cents(string|int|float|null $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** Основна валюта сайту: баланс, ціни тарифів, історія операцій */
    public static function base(): string
    {
        return (string) (config('graj.billing.currency') ?: 'USD');
    }

    /** «$», «₴»; невідома валюта — кодом */
    public static function symbol(?string $currency = null): string
    {
        $currency ??= self::base();

        return self::SYMBOLS[$currency] ?? $currency;
    }

    /** Сума в основній валюті: «1 250,00 $» */
    public static function format(int|string|float|null $amount, bool $isCents = false): string
    {
        $cents = $isCents ? (int) $amount : self::cents($amount);

        return self::formatIn($cents, self::base());
    }

    /** «12,50 $», «1 250,00 ₴» */
    public static function formatIn(int $cents, string $currency): string
    {
        return number_format($cents / 100, 2, ',', "\u{00A0}")."\u{00A0}".self::symbol($currency);
    }

    /** Ціна коротко: «7 $», а з центами — «7,25 $» */
    public static function short(int|float|string|null $amount): string
    {
        $cents = self::cents($amount);

        return number_format($cents / 100, $cents % 100 === 0 ? 0 : 2, ',', "\u{00A0}")."\u{00A0}".self::symbol();
    }

    /** Ціла сума без копійок для зведень: «12 500 $» */
    public static function whole(int|float|string|null $amount): string
    {
        return number_format((float) $amount, 0, ',', "\u{00A0}")."\u{00A0}".self::symbol();
    }
}
