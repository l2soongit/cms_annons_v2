<?php

namespace App\Support;

use App\Models\Game;
use App\Models\Server;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Вайпи Rust: форс-вайп (перший четвер місяця, 19:00 за Лондоном), наступний вайп сервера за частотою з поля «Вайп»
 * і моментом останнього вайпу з моніторингу, календар вайпів на тиждень.
 */
final class RustWipes
{
    private const FORCED_TZ = 'Europe/London';

    /** Найближчий форс-вайп після $from */
    public static function forced(?Carbon $from = null): Carbon
    {
        $from = ($from ?? now())->copy();
        for ($m = 0; $m < 3; $m++) {
            $day = $from->copy()->setTimezone(self::FORCED_TZ)->startOfMonth()->addMonthsNoOverflow($m);
            $thursday = $day->copy()->next(Carbon::THURSDAY);
            if ($day->isThursday()) {
                $thursday = $day;
            }
            $at = $thursday->setTime(19, 0);
            if ($at->gt($from)) {
                return $at->setTimezone(config('app.timezone'));
            }
        }

        return $from->addMonth();
    }

    /** Скільки днів між вайпами сервера; null — частота невідома. Потрібні завантажені fieldValues.field */
    public static function period(Server $server): ?int
    {
        $option = $server->fieldOption('wipe');
        if (! $option) {
            return null;
        }
        if (! empty($option['days'])) {
            return (int) $option['days'];
        }

        return preg_match('/(\d+)/', (string) $option['label'], $m) ? (int) $m[1] * 7 : null;
    }

    /**
     * Наступний вайп: щомісячні — у форс-вайп; інші — від останнього відомого вайпу через період;
     * без даних моніторингу щотижневі вважаємо четверговими (найпоширеніший розклад).
     */
    public static function next(Server $server): ?Carbon
    {
        $days = self::period($server);
        if (! $days) {
            return null;
        }
        if ($days >= 28) {
            return self::forced();
        }
        if ($server->last_wiped_at) {
            $next = $server->last_wiped_at->copy();
            while ($next->lte(now())) {
                $next->addDays($days);
            }

            return Carbon::instance($next);
        }

        if ($days !== 7) {
            return null;
        }
        $local = now()->setTimezone(self::FORCED_TZ);
        $at = $local->copy()->setTime(19, 0);
        if (! $local->isThursday() || $at->lte($local)) {
            $at = $local->copy()->next(Carbon::THURSDAY)->setTime(19, 0);
        }

        return $at->setTimezone(config('app.timezone'));
    }

    /**
     * Календар вайпів: 7 днів від сьогодні, у кожному — сервери, що вайпаються того дня.
     *
     * @return Collection<int, array{date: Carbon, servers: Collection<int, array{server: Server, at: Carbon}>}>
     */
    public static function week(Game $game): Collection
    {
        $wipes = $game->servers()->published()->with('fieldValues.field')->get()
            ->map(fn (Server $s) => ['server' => $s, 'at' => self::next($s)])
            ->filter(fn ($w) => $w['at'] && $w['at']->lt(today()->addDays(7)))
            ->sortBy(fn ($w) => $w['at']->getTimestamp());

        return collect(range(0, 6))->map(fn (int $i) => [
            'date' => today()->addDays($i),
            'servers' => $wipes->filter(fn ($w) => $w['at']->isSameDay(today()->addDays($i)))->values(),
        ]);
    }
}
