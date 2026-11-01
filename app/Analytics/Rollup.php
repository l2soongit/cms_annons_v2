<?php

namespace App\Analytics;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Зводить сирі перегляди й події за добу в analytics_stats: увесь сайт (server_id = 0) і кожен сервер окремо.
 * Перерахунок дня повністю замінює його рядки, тож команду можна запускати скільки завгодно разів.
 */
final class Rollup
{
    /** Виміри, що показують, звідки прийшли (рахуються лише за першими переглядами візитів) */
    private const ARRIVAL = ['source' => 'source', 'referrer' => 'referrer', 'landing' => 'path'];

    /** Виміри аудиторії й вмісту: [вимір => стовпець] */
    private const PLAIN = ['country' => 'country', 'lang' => 'lang', 'device' => 'device', 'browser' => 'browser', 'os' => 'os', 'locale' => 'locale', 'page' => 'path', 'route' => 'route'];

    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    private string $date;

    public function day(Carbon $day): int
    {
        $this->date = $day->toDateString();
        $this->rows = [];
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();

        $views = fn () => DB::table('analytics_views')->whereBetween('created_at', [$from, $to]);
        $human = fn () => $views()->where('device', '!=', 'bot');
        $events = fn () => DB::table('analytics_events')->whereBetween('created_at', [$from, $to]);

        // ── Увесь сайт ──
        $this->sessions($human());
        foreach (self::ARRIVAL as $dim => $column) {
            $this->grouped($human()->where('entry', true)->whereNotNull($column), $dim, $column);
        }
        $this->grouped($human()->where('entry', true)->whereNotNull('utm_source'), 'utm', DB::raw($this->concat(['utm_source', 'utm_medium', 'utm_campaign'])));
        foreach (self::PLAIN as $dim => $column) {
            $this->grouped($human(), $dim, $column);
        }
        $this->grouped($human()->whereNotNull('game_id'), 'game', 'game_id');
        $this->grouped($human()->whereNotNull('server_id'), 'server', 'server_id');
        $this->grouped($views()->where('device', 'bot'), 'bot', 'browser');
        $this->grouped($events(), 'event', 'name');
        foreach (['outbound' => 'outbound', 'search' => 'search', 'banner' => 'banner_click'] as $dim => $name) {
            $this->grouped($events()->where('name', $name)->whereNotNull('label'), $dim, 'label');
        }
        $this->funnel(0, $human(), $events());

        // ── Кожен сервер: як знаходять його сторінку й що роблять далі ──
        $this->grouped($human()->whereNotNull('server_id'), 'total', DB::raw("''"), true);
        // Для власника «внутрішній» перехід — це відвідувач, який знайшов сервер у каталозі graj.club
        $this->grouped($human()->whereNotNull('server_id')->whereNotNull('referrer'), 'referrer', 'referrer', true);
        foreach (['source' => 'source', 'country' => 'country', 'lang' => 'lang', 'device' => 'device', 'browser' => 'browser', 'os' => 'os', 'route' => 'route'] as $dim => $column) {
            $this->grouped($human()->whereNotNull('server_id'), $dim, $column, true);
        }
        $this->grouped($events()->whereNotNull('server_id'), 'event', 'name', true);
        foreach ($this->serverIds($human(), $events()) as $id) {
            $this->funnel($id, $human()->where('server_id', $id), $events()->where('server_id', $id));
        }

        DB::transaction(function () {
            DB::table('analytics_stats')->where('date', $this->date)->delete();
            foreach (array_chunk(array_values($this->rows), 500) as $chunk) {
                DB::table('analytics_stats')->insert($chunk);
            }
        });

        return count($this->rows);
    }

    /** Видалити сирі дані старші за термін зберігання (зведення лишаються) */
    public function prune(): int
    {
        $before = now()->subDays(max(1, (int) config('graj.analytics.raw_retention_days')))->startOfDay();

        return DB::table('analytics_views')->where('created_at', '<', $before)->delete()
            + DB::table('analytics_events')->where('created_at', '<', $before)->delete();
    }

    private function grouped($query, string $dim, mixed $column, bool $perServer = false): void
    {
        $query->selectRaw('COUNT(*) as views')->selectRaw('COUNT(DISTINCT visitor) as visitors');
        if ($query->from === 'analytics_views') {
            $query->selectRaw('SUM(CASE WHEN entry THEN 1 ELSE 0 END) as entries');
        }
        $query->addSelect([is_string($column) ? "{$column} as k" : DB::raw($column->getValue(DB::connection()->getQueryGrammar()).' as k')])
            ->groupBy(is_string($column) ? $column : 'k');
        if ($perServer) {
            $query->addSelect('server_id')->groupBy('server_id');
        }

        foreach ($query->get() as $row) {
            $this->add($perServer ? (int) $row->server_id : 0, $dim, (string) ($row->k ?? ''), (int) $row->views, (int) $row->visitors, (int) ($row->entries ?? 0));
        }
    }

    /**
     * Візити: перегляди одного відвідувача з паузами до session_minutes; відмова — візит з одного перегляду.
     * Заодно — розподіл переглядів за годинами доби.
     */
    private function sessions($query): void
    {
        $gap = (int) config('graj.analytics.session_minutes', 30) * 60;
        $views = 0;
        $visitors = [];
        $entries = 0;
        $sessions = 0;
        $bounces = 0;
        $duration = 0;
        $hours = array_fill(0, 24, [0, []]);
        /** @var list<array{0: int, 1: int, 2: int}> $visits [початок, останній перегляд, переглядів] */
        $visits = [];
        $visitor = null;

        foreach ($query->orderBy('visitor')->orderBy('created_at')->select(['visitor', 'created_at', 'entry'])->cursor() as $row) {
            $at = Carbon::parse($row->created_at);
            $t = $at->getTimestamp();
            $views++;
            $visitors[$row->visitor] = true;
            $entries += (int) $row->entry;
            $hour = (int) $at->format('G');
            $hours[$hour][0]++;
            $hours[$hour][1][$row->visitor] = true;

            $current = array_key_last($visits);
            if ($current === null || $visitor !== $row->visitor || $t - $visits[$current][1] > $gap) {
                $visits[] = [$t, $t, 1];
            } else {
                $visits[$current][1] = $t;
                $visits[$current][2]++;
            }
            $visitor = $row->visitor;
        }
        foreach ($visits as [$begin, $end, $count]) {
            $sessions++;
            $bounces += $count === 1 ? 1 : 0;
            $duration += $end - $begin;
        }

        $this->add(0, 'total', '', $views, count($visitors), $entries, $sessions, $bounces, $duration);
        foreach ($hours as $hour => [$n, $who]) {
            if ($n > 0) {
                $this->add(0, 'hour', sprintf('%02d', $hour), $n, count($who));
            }
        }
    }

    /** Воронка: скільки різних відвідувачів дійшли до кожного кроку */
    private function funnel(int $server, $views, $events): void
    {
        $steps = $server === 0
            ? ['visit' => (clone $views), 'server_view' => (clone $views)->whereNotNull('server_id')]
            : ['visit' => (clone $views)->where('route', 'servers.show'), 'server_view' => (clone $views)];

        foreach ($steps as $key => $query) {
            $this->add($server, 'funnel', $key, 0, (int) $query->distinct()->count('visitor'));
        }
        foreach (['vote_click', 'vote', 'website', 'register', 'server_add'] as $name) {
            $n = (int) (clone $events)->where('name', $name)->distinct()->count('visitor');
            if ($n > 0) {
                $this->add($server, 'funnel', $name, 0, $n);
            }
        }
    }

    /** @return list<int> */
    private function serverIds($views, $events): array
    {
        return $views->whereNotNull('server_id')->distinct()->pluck('server_id')
            ->merge($events->whereNotNull('server_id')->distinct()->pluck('server_id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** @param  list<string>  $columns */
    private function concat(array $columns): string
    {
        $parts = array_map(fn ($c) => "COALESCE({$c}, '')", $columns);

        return DB::connection()->getDriverName() === 'sqlite' || DB::connection()->getDriverName() === 'pgsql'
            ? implode(" || ' / ' || ", $parts)
            : 'CONCAT_WS(\' / \', '.implode(', ', $parts).')';
    }

    private function add(int $server, string $dim, string $key, int $views, int $visitors, int $entries = 0, int $sessions = 0, int $bounces = 0, int $duration = 0): void
    {
        $key = mb_substr($key, 0, 191);
        // Ключі, що збігаються без урахування регістру чи після обрізання, — один рядок (унікальний індекс MySQL)
        $id = $server.'|'.$dim.'|'.mb_strtolower($key);
        if (isset($this->rows[$id])) {
            foreach (['views', 'visitors', 'entries', 'sessions', 'bounces', 'duration'] as $field) {
                $this->rows[$id][$field] += $$field;
            }

            return;
        }
        $this->rows[$id] = [
            'date' => $this->date, 'server_id' => $server, 'dim' => $dim, 'key' => $key,
            'views' => $views, 'visitors' => $visitors, 'entries' => $entries,
            'sessions' => $sessions, 'bounces' => $bounces, 'duration' => $duration,
        ];
    }
}
