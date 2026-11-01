<?php

namespace App\Analytics;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Звіт за період із денних зведень (analytics_stats): увесь сайт або один сервер.
 * Порівняння — з попереднім періодом такої ж довжини.
 */
final class Report
{
    public readonly Carbon $from;

    public readonly Carbon $to;

    public function __construct(Carbon $from, Carbon $to, public readonly int $server = 0)
    {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->startOfDay();
    }

    /** Період за назвою: today, yesterday, 7, 30, 90, 365, month, custom (з датами) */
    public static function period(string $period, ?string $from = null, ?string $to = null, int $server = 0): self
    {
        $today = today();

        return match ($period) {
            'today' => new self($today, $today, $server),
            'yesterday' => new self($today->copy()->subDay(), $today->copy()->subDay(), $server),
            'month' => new self($today->copy()->startOfMonth(), $today, $server),
            'custom' => new self(self::date($from) ?? $today->copy()->subDays(29), self::date($to) ?? $today, $server),
            default => new self($today->copy()->subDays(max(1, min(730, (int) $period)) - 1), $today, $server),
        };
    }

    private static function date(?string $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    public function previous(): self
    {
        $days = $this->days();

        return new self($this->from->copy()->subDays($days), $this->from->copy()->subDay(), $this->server);
    }

    private function query(string $dim)
    {
        return DB::table('analytics_stats')->where('server_id', $this->server)->where('dim', $dim)
            ->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()]);
    }

    /** @return array{views: int, visitors: int, entries: int, sessions: int, bounces: int, duration: int, bounce_rate: ?float, avg_duration: ?int, per_session: ?float} */
    public function totals(): array
    {
        $row = $this->query('total')->selectRaw('SUM(views) as views, SUM(visitors) as visitors, SUM(entries) as entries, SUM(sessions) as sessions, SUM(bounces) as bounces, SUM(duration) as duration')->first();
        $t = array_map('intval', (array) $row);

        return $t + [
            'bounce_rate' => $t['sessions'] ? round($t['bounces'] / $t['sessions'] * 100, 1) : null,
            'avg_duration' => $t['sessions'] ? (int) round($t['duration'] / $t['sessions']) : null,
            'per_session' => $t['sessions'] ? round($t['views'] / $t['sessions'], 2) : null,
        ];
    }

    /** Зміна у відсотках проти попереднього періоду; null — порівнювати нема з чим */
    public static function delta(int|float|null $now, int|float|null $before): ?int
    {
        if ($now === null || ! $before) {
            return null;
        }

        return (int) round(($now - $before) / $before * 100);
    }

    /**
     * По днях (або по місяцях, якщо період довший за 120 днів): перегляди й відвідувачі.
     *
     * @return Collection<int, array{date: Carbon, label: string, views: int, visitors: int}>
     */
    public function series(string $dim = 'total', ?string $key = null): Collection
    {
        $query = $this->query($dim)->groupBy('date')->selectRaw('date, SUM(views) as views, SUM(visitors) as visitors');
        if ($key !== null) {
            $query->where('key', $key);
        }
        $rows = $query->get()->keyBy(fn ($r) => Carbon::parse($r->date)->toDateString());

        $monthly = $this->days() > 120;
        $points = collect();
        for ($d = $this->from->copy(); $d <= $this->to; $d->addDay()) {
            $bucket = $monthly ? $d->format('Y-m') : $d->toDateString();
            $row = $rows[$d->toDateString()] ?? null;
            $point = $points->get($bucket, ['date' => $d->copy(), 'label' => $monthly ? $d->translatedFormat('M Y') : $d->format('d.m'), 'views' => 0, 'visitors' => 0]);
            $point['views'] += (int) ($row->views ?? 0);
            $point['visitors'] += (int) ($row->visitors ?? 0);
            $points->put($bucket, $point);
        }

        return $points->values();
    }

    /**
     * Найбільші значення виміру з часткою від суми.
     *
     * @return Collection<int, array{key: string, views: int, visitors: int, entries: int, share: float}>
     */
    public function top(string $dim, int $limit = 10, string $order = 'views'): Collection
    {
        $rows = $this->query($dim)->groupBy('key')
            ->selectRaw(self::key().' as k, SUM(views) as views, SUM(visitors) as visitors, SUM(entries) as entries')
            ->orderByDesc($order)->limit($limit > 0 ? $limit : PHP_INT_MAX)->get();
        $sum = max(1, (int) $this->query($dim)->sum($order));

        return $rows->map(fn ($r) => [
            'key' => (string) $r->k,
            'views' => (int) $r->views,
            'visitors' => (int) $r->visitors,
            'entries' => (int) $r->entries,
            'share' => round((int) $r->{$order} / $sum * 100, 1),
        ]);
    }

    /** @return array<string, int> сума за ключами (події — кількість, воронка — відвідувачі) */
    public function sums(string $dim, string $field = 'views'): array
    {
        return $this->query($dim)->groupBy('key')->selectRaw(self::key()." as k, SUM({$field}) as n")->pluck('n', 'k')->map(fn ($n) => (int) $n)->all();
    }

    /** @return array<int, int> перегляди за годинами доби 0–23 */
    public function hours(): array
    {
        $rows = $this->sums('hour');

        return collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => $rows[sprintf('%02d', $h)] ?? 0])->all();
    }

    /**
     * Зараз на сайті — із сирих даних: відвідувачі за 5 хвилин, перегляди за 30 хвилин похвилинно, популярні сторінки.
     *
     * @return array<string, mixed> now — відвідувачі за 5 хв, views, minutes — 30 чисел, pages і sources — колекції {key, views, visitors}
     */
    public static function realtime(int $server = 0): array
    {
        $base = fn () => DB::table('analytics_views')->where('device', '!=', 'bot')
            ->when($server, fn ($q) => $q->where('server_id', $server));
        $recent = $base()->where('created_at', '>=', now()->subMinutes(30))->get(['visitor', 'path', 'source', 'referrer', 'created_at']);

        $minutes = array_fill(0, 30, 0);
        foreach ($recent as $row) {
            $ago = (int) floor(now()->diffInSeconds(Carbon::parse($row->created_at), true) / 60);
            if ($ago < 30) {
                $minutes[29 - $ago]++;
            }
        }

        return [
            'now' => $base()->where('created_at', '>=', now()->subMinutes(5))->distinct()->count('visitor'),
            'views' => $recent->count(),
            'minutes' => $minutes,
            'pages' => $recent->groupBy('path')->map(fn ($g, $path) => (object) ['key' => $path, 'views' => $g->count(), 'visitors' => $g->unique('visitor')->count()])
                ->sortByDesc('visitors')->take(10)->values(),
            'sources' => $recent->where('source', '!=', 'internal')->groupBy(fn ($r) => $r->referrer ?: $r->source)
                ->map(fn ($g, $key) => (object) ['key' => $key, 'views' => $g->count(), 'visitors' => $g->unique('visitor')->count()])
                ->sortByDesc('visitors')->take(8)->values(),
        ];
    }

    /** Стовпець `key` — зарезервоване слово MySQL */
    private static function key(): string
    {
        return DB::connection()->getQueryGrammar()->wrap('key');
    }
}
