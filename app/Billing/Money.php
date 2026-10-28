<?php

namespace App\Billing;

/**
 * Гроші рахуються в копійках цілими числами (без bcmath, якого може не бути на хостингу);
 * у базі — decimal(…, 2).
 */
final class Money
{
    public static function cents(string|int|float|null $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** «1 250,00 ₴» */
    public static function format(int|string|float|null $amount, bool $isCents = false): string
    {
        $cents = $isCents ? (int) $amount : self::cents($amount);

        return self::formatIn($cents, 'UAH');
    }

    /** «12,50 $», «1 250,00 ₴»; невідома валюта — кодом */
    public static function formatIn(int $cents, string $currency): string
    {
        $symbol = ['UAH' => '₴', 'USD' => '$', 'EUR' => '€', 'RUB' => '₽'][$currency] ?? $currency;

        return number_format($cents / 100, 2, ',', "\u{00A0}")."\u{00A0}".$symbol;
    }
}
