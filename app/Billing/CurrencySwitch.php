<?php

namespace App\Billing;

use App\Models\ModerationLog;
use App\Support\HomePageData;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Зміна основної валюти сайту: усі суми (баланси, ціни тарифів, замовлення, історія операцій,
 * межі поповнення) перераховуються за курсом НБУ, тож ні в кого не стає більше чи менше грошей.
 */
final class CurrencySwitch
{
    /** Валюти, які можна зробити основними */
    public const CURRENCIES = ['USD' => 'Долар США ($)', 'EUR' => 'Євро (€)', 'UAH' => 'Гривня (₴)'];

    /** Запасний курс гривні до долара — лише для сайту без грошей користувачів, коли НБУ недоступний */
    private const FALLBACK_UAH_PER_USD = 41.5;

    public function __construct(private ExchangeRates $rates) {}

    /**
     * Скільки одиниць $to за одиницю $from. Курс береться свіжий з НБУ; якщо НБУ недоступний —
     * останній збережений. Без курсу кидає виняток, щоб не перерахувати гроші навмання.
     */
    public function factor(string $from, string $to, bool $allowFallback = false): float
    {
        if ($from === $to) {
            return 1.0;
        }

        try {
            if (! app()->runningUnitTests()) {
                $this->rates->refresh();
            }
        } catch (Throwable $e) {
            Log::warning('Курси НБУ недоступні під час зміни валюти: '.$e->getMessage());
        }

        $a = $this->rates->hryvnias($from, fresh: false);
        $b = $this->rates->hryvnias($to, fresh: false);

        if (($a === null || $b === null) && $allowFallback) {
            $a ??= $from === 'USD' ? self::FALLBACK_UAH_PER_USD : null;
            $b ??= $to === 'USD' ? self::FALLBACK_UAH_PER_USD : null;
        }

        if ($a === null || $b === null) {
            throw new RuntimeException("Немає курсу НБУ для {$from} чи {$to}. Спробуйте пізніше, коли курси оновляться (php artisan billing:rates).");
        }

        return $a / $b;
    }

    /** Чи є на сайті гроші користувачів (тоді перерахунок лише за справжнім курсом) */
    public function hasMoney(): bool
    {
        return DB::table('users')->where('balance', '!=', 0)->exists() || DB::table('transactions')->exists();
    }

    /** @return float використаний курс (одиниць $to за одиницю $from) */
    public function run(string $to, ?int $moderatorId = null): float
    {
        $from = Money::base();
        if (! array_key_exists($to, self::CURRENCIES)) {
            throw new RuntimeException("Валюта {$to} не підтримується.");
        }
        if ($from === $to) {
            return 1.0;
        }

        $f = $this->factor($from, $to, allowFallback: ! $this->hasMoney());

        DB::transaction(function () use ($from, $to, $f) {
            // Поповнення в старій основній валюті тепер — оплата «в іноземній»: зберігаємо, скільки сплачено насправді.
            // Окремими запитами: MySQL у UPDATE бачить уже змінені значення попередніх полів
            DB::table('transactions')->whereNull('paid_currency')->where('type', 'deposit')
                ->update(['paid_amount' => DB::raw('amount'), 'paid_currency' => $from, 'rate' => $f]);
            DB::table('transactions')->whereNotNull('rate')->where('paid_currency', '!=', $from)
                ->update(['rate' => DB::raw('ROUND(rate * '.$f.', 6)')]);
            DB::table('transactions')->update(['amount' => DB::raw('ROUND(amount * '.$f.', 2)'), 'currency' => $to]);

            DB::table('users')->where('balance', '!=', 0)->update(['balance' => DB::raw('ROUND(balance * '.$f.', 2)')]);
            DB::table('tariffs')->update(['price' => DB::raw('ROUND(price * '.$f.', 2)')]);
            DB::table('orders')->update(['amount' => DB::raw('ROUND(amount * '.$f.', 2)')]);

            Settings::save([
                'billing.currency' => $to,
                'billing.min_deposit' => max(1, (int) round((float) config('graj.billing.min_deposit') * $f)),
                'billing.max_deposit' => max(10, (int) round((float) config('graj.billing.max_deposit') * $f)),
            ]);
        });

        HomePageData::forget();

        if ($moderatorId !== null) {
            ModerationLog::create([
                'moderator_id' => $moderatorId,
                'subject_type' => 'settings',
                'subject_id' => 0,
                'action' => 'currency_switch',
                'changes' => ['from' => $from, 'to' => $to, 'rate' => round($f, 8)],
            ]);
        }

        return $f;
    }
}
