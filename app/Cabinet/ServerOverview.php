<?php

namespace App\Cabinet;

use App\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Дані «Огляду» кабінету для одного сервера: місце й сусіди в рейтингу, відрив до наступного місця,
 * показники з динамікою, голоси й онлайн за 30 днів, список справ, останні голоси й активні послуги.
 */
final class ServerOverview
{
    private const DAYS = 30;

    public function __construct(private Server $server) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        // Медіа — повністю: у списку серверів кабінету завантажено лише логотипи
        $this->server->loadMissing('game')->load('media');
        $days = $this->days();

        return [
            'race' => $this->race(),
            'kpi' => $this->kpi($days),
            'days' => $days,
            'todo' => $this->todo(),
            'votes' => $this->server->votes()->latest('created_at')->limit(6)->get(['nickname', 'status', 'callback_status', 'created_at']),
            'services' => $this->server->orders()->with('tariff')->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->orderBy('ends_at')->get(),
        ];
    }

    /**
     * Місце в рейтингу гри: до двох сусідів вище й один нижче, відрив у балах до наступного місця.
     *
     * @return array<string, mixed>|null
     */
    private function race(): ?array
    {
        if ($this->server->status !== ServerStatus::Published || ! $this->server->rank) {
            return null;
        }

        $ranked = Server::where('game_id', $this->server->game_id)->ranked()->get(['id', 'name', 'score', 'votes_month', 'rank']);
        $index = $ranked->search(fn (Server $s) => $s->id === $this->server->id);
        if ($index === false) {
            return null;
        }

        $from = max(0, $index - 2);
        $ladder = $ranked->slice($from, $index - $from + 2)->values();
        $top = max(1.0, (float) $ranked->first()->score);
        $above = $index > 0 ? $ranked[$index - 1] : null;
        $below = $ranked[$index + 1] ?? null;

        return [
            'rank' => $index + 1,
            'total' => $ranked->count(),
            'ladder' => $ladder->map(fn (Server $s, $i) => [
                'place' => $from + $i + 1,
                'name' => $s->name,
                'score' => (int) round($s->score),
                'width' => max(4, (int) round($s->score / $top * 100)),
                'me' => $s->id === $this->server->id,
            ]),
            // Скільки балів бракує до місця вище (бали ≈ голоси за стандартних ваг рейтингу)
            'gap_up' => $above ? (int) max(1, ceil($above->score - $this->server->score) + 1) : null,
            'lead' => ! $above && $below ? (int) round($this->server->score - $below->score) : null,
            'month_left' => now()->diff(now()->endOfMonth()),
        ];
    }

    /**
     * Голоси й середній онлайн по днях за останні 30 днів.
     *
     * @return Collection<int, array{date: Carbon, votes: int, online: int, uptime: ?float}>
     */
    private function days(): Collection
    {
        $from = today()->subDays(self::DAYS - 1);

        $votes = $this->server->votes()->where('status', 'counted')->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as n')->groupBy('d')->pluck('n', 'd');
        $stats = DB::table('server_stats_hourly')->where('server_id', $this->server->id)->where('hour', '>=', $from)
            ->selectRaw('DATE(hour) as d, AVG(players_avg) as online, AVG(uptime) as uptime')->groupBy('d')->get()->keyBy('d');

        return collect(range(0, self::DAYS - 1))->map(function (int $i) use ($from, $votes, $stats) {
            $date = $from->copy()->addDays($i);
            $key = $date->toDateString();

            return [
                'date' => $date,
                'votes' => (int) ($votes[$key] ?? 0),
                'online' => (int) round($stats[$key]->online ?? 0),
                'uptime' => isset($stats[$key]) ? round((float) $stats[$key]->uptime, 1) : null,
            ];
        });
    }

    /**
     * @param  Collection<int, array{date: Carbon, votes: int, online: int, uptime: ?float}>  $days
     * @return array<string, mixed>
     */
    private function kpi(Collection $days): array
    {
        $last = $days->slice(-15)->sum('votes');
        $prev = $days->slice(0, 15)->sum('votes');
        $uptimes = $days->pluck('uptime')->filter(fn ($u) => $u !== null);

        return [
            'votes_month' => (int) $this->server->votes_month,
            // Динаміка: останні 15 днів проти попередніх 15
            'votes_trend' => $prev > 0 ? (int) round(($last - $prev) / $prev * 100) : null,
            'votes_day' => (int) $this->server->votes()->where('status', 'counted')->where('created_at', '>=', now()->subDay())->count(),
            'online' => $this->server->is_online ? (int) $this->server->players : null,
            'max_players' => (int) $this->server->max_players,
            'peak' => (int) DB::table('server_stats_hourly')->where('server_id', $this->server->id)->where('hour', '>=', now()->subDay())->max('players_max'),
            'uptime' => $uptimes->isNotEmpty() ? round($uptimes->avg(), 1) : null,
        ];
    }

    /** @return list<array{done: bool, key: string, url: string}> */
    private function todo(): array
    {
        $s = $this->server;
        $screens = $s->media->where('type', 'screenshot')->count();

        return [
            ['done' => $s->status === ServerStatus::Published, 'key' => 'published', 'url' => route('cabinet.servers.edit', $s)],
            ['done' => (bool) $s->verified_at, 'key' => 'verified', 'url' => route('cabinet.servers.show', $s).'#s-verify'],
            ['done' => filled($s->callback_url), 'key' => 'callback', 'url' => route('cabinet.servers.show', $s).'#s-callback'],
            ['done' => $s->media->contains('type', 'logo'), 'key' => 'logo', 'url' => route('cabinet.servers.edit', $s)],
            ['done' => $screens >= 3, 'key' => 'screenshots', 'url' => route('cabinet.servers.edit', $s)],
            ['done' => mb_strlen(strip_tags((string) $s->description)) >= 300, 'key' => 'description', 'url' => route('cabinet.servers.edit', $s)],
        ];
    }
}
