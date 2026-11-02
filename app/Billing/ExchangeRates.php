<?php

namespace App\Billing;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Офіційні курси НБУ. У таблиці exchange_rates — гривень за одиницю валюти (як публікує НБУ);
 * курс між будь-якими двома валютами — через гривню. Оновлюються за розкладом (billing:rates);
 * якщо НБУ недоступний, діє останній збережений курс, доки він не застаріє (billing.rate_max_age_days).
 */
class ExchangeRates
{
    private const NBU_URL = 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange?json';

    /** @return list<string> валюти, у яких зараз можна поповнити баланс; основна валюта — завжди перша */
    public function depositCurrencies(): array
    {
        return array_values(array_filter(
            array_unique([Money::base(), ...(array) config('graj.billing.deposit_currencies')]),
            fn (string $c) => $this->rate($c) !== null,
        ));
    }

    /** Одиниць основної валюти за одиницю $currency; null — курсу немає або він застарів */
    public function rate(string $currency): ?float
    {
        if ($currency === Money::base()) {
            return 1.0;
        }

        $from = $this->hryvnias($currency);
        $base = $this->hryvnias(Money::base());

        return $from === null || $base === null ? null : $from / $base;
    }

    /**
     * Скільки гривень коштує одиниця валюти за курсом НБУ.
     * $fresh = false — підійде й застарілий курс (разовий перерахунок сум, коли НБУ недоступний).
     */
    public function hryvnias(string $currency, bool $fresh = true): ?float
    {
        if ($currency === 'UAH') {
            return 1.0;
        }

        $row = DB::table('exchange_rates')->where('currency', $currency)->first();
        if (! $row || (float) $row->rate <= 0) {
            return null;
        }
        if ($fresh && Carbon::parse($row->rate_date)->lt(today()->subDays((int) config('graj.billing.rate_max_age_days')))) {
            return null;
        }

        return (float) $row->rate;
    }

    /** Сума у валюті оплати (центи) → основна валюта (центи) */
    public function toBase(int $cents, float $rate): int
    {
        return (int) round($cents * $rate);
    }

    /** Завантажити курси з НБУ; повертає кількість оновлених валют */
    public function refresh(): int
    {
        $wanted = array_diff(array_unique([Money::base(), 'USD', ...(array) config('graj.billing.deposit_currencies')]), ['UAH']);
        $rows = Http::timeout(10)->retry(2, 1000)->get(self::NBU_URL)->throw()->json();

        $updated = 0;
        foreach ((array) $rows as $row) {
            if (! in_array($row['cc'] ?? null, $wanted, true) || ! is_numeric($row['rate'] ?? null)) {
                continue;
            }

            DB::table('exchange_rates')->updateOrInsert(['currency' => $row['cc']], [
                'rate' => round((float) $row['rate'], 6),
                'rate_date' => Carbon::createFromFormat('d.m.Y', (string) $row['exchangedate'])->toDateString(),
                'updated_at' => now(),
                'created_at' => now(),
            ]);
            $updated++;
        }

        return $updated;
    }
}
