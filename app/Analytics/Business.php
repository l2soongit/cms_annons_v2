<?php

namespace App\Analytics;

use Illuminate\Support\Facades\DB;

/**
 * Бізнес-показники порталу за період — з робочих таблиць (не з аналітики, тож доступні й за минулі місяці):
 * реєстрації, нові й опубліковані сервери, голоси, відгуки, поповнення, оплачені послуги.
 */
final class Business
{
    public function __construct(private Report $report) {}

    /** @return array<string, float|int> */
    public function totals(?Report $report = null): array
    {
        $r = $report ?? $this->report;
        [$from, $to] = [$r->from, $r->to->copy()->endOfDay()];
        $between = fn (string $table, string $column = 'created_at') => DB::table($table)->whereBetween($column, [$from, $to]);

        return [
            'users' => $between('users')->count(),
            'servers' => $between('servers')->whereNull('deleted_at')->count(),
            'votes' => $between('votes')->where('status', 'counted')->count(),
            'suspicious' => $between('votes')->where('status', 'suspicious')->count(),
            'reviews' => $between('reviews')->count(),
            'deposits' => (float) $between('transactions')->where('type', 'deposit')->where('status', 'succeeded')->sum('amount'),
            'payers' => $between('transactions')->where('type', 'deposit')->where('status', 'succeeded')->distinct()->count('user_id'),
            'orders' => $between('orders')->whereNotIn('status', ['refunded'])->count(),
            'spent' => (float) $between('orders')->whereNotIn('status', ['refunded'])->sum('amount'),
        ];
    }

    /**
     * По днях: реєстрації, нові сервери, голоси, поповнення.
     *
     * @return list<array{label: string, users: int, servers: int, votes: int, deposits: float}>
     */
    public function series(): array
    {
        $r = $this->report;
        [$from, $to] = [$r->from, $r->to->copy()->endOfDay()];
        $daily = fn ($query, string $agg = 'COUNT(*)') => $query->whereBetween('created_at', [$from, $to])
            ->selectRaw("DATE(created_at) as d, {$agg} as n")->groupBy('d')->pluck('n', 'd');

        $users = $daily(DB::table('users'));
        $servers = $daily(DB::table('servers')->whereNull('deleted_at'));
        $votes = $daily(DB::table('votes')->where('status', 'counted'));
        $deposits = $daily(DB::table('transactions')->where('type', 'deposit')->where('status', 'succeeded'), 'SUM(amount)');

        $points = [];
        for ($d = $from->copy(); $d <= $r->to; $d->addDay()) {
            $key = $d->toDateString();
            $points[] = [
                'label' => $d->format('d.m'),
                'users' => (int) ($users[$key] ?? 0),
                'servers' => (int) ($servers[$key] ?? 0),
                'votes' => (int) ($votes[$key] ?? 0),
                'deposits' => (float) ($deposits[$key] ?? 0),
            ];
        }

        return $points;
    }

    /** @return list<array{label: string, count: int, total: float}> виручка за послугами за період */
    public function services(): array
    {
        $rows = DB::table('orders')->join('tariffs', 'tariffs.id', '=', 'orders.tariff_id')
            ->whereBetween('orders.created_at', [$this->report->from, $this->report->to->copy()->endOfDay()])
            ->whereNotIn('orders.status', ['refunded'])
            ->groupBy('tariffs.service')
            ->selectRaw('tariffs.service as service, COUNT(*) as n, SUM(orders.amount) as total')
            ->orderByDesc('total')->get();

        return $rows->map(fn ($r) => ['label' => __("billing.services.{$r->service}"), 'count' => (int) $r->n, 'total' => (float) $r->total])->all();
    }

    /** @return list<array{name: string, votes: int}> ігри за голосами */
    public function games(): array
    {
        return DB::table('votes')->join('servers', 'servers.id', '=', 'votes.server_id')->join('games', 'games.id', '=', 'servers.game_id')
            ->whereBetween('votes.created_at', [$this->report->from, $this->report->to->copy()->endOfDay()])->where('votes.status', 'counted')
            ->groupBy('games.id', 'games.name')->selectRaw('games.name as name, COUNT(*) as n')->orderByDesc('n')->get()
            ->map(fn ($r) => ['name' => self::name($r->name), 'votes' => (int) $r->n])->all();
    }

    /** Назва з перекладного JSON-поля поточною мовою */
    public static function name(?string $json): string
    {
        $value = json_decode((string) $json, true);
        if (! is_array($value)) {
            return (string) $json;
        }

        return (string) ($value[app()->getLocale()] ?? $value['uk'] ?? reset($value) ?: '');
    }
}
