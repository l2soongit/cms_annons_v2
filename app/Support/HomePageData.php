<?php

namespace App\Support;

use App\Models\Game;
use App\Models\Server;
use App\Models\Tariff;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Дані головної у вигляді простих масивів: кешуються на хвилину й без змін ідуть у JSON для скрипта.
 */
final class HomePageData
{
    private const ARENA_SIZE = 6;

    private const ARENA_GAME_TABS = 3;

    public static function get(): array
    {
        return Cache::remember('home.data.'.app()->getLocale(), 60, fn () => (new self)->build());
    }

    public static function forget(): void
    {
        foreach (['uk', 'en'] as $locale) {
            Cache::forget("home.data.{$locale}");
        }
    }

    private function build(): array
    {
        $games = Game::active()
            ->withCount(['servers' => fn ($q) => $q->published()])
            ->withSum(['servers as online_sum' => fn ($q) => $q->published()->where('is_online', true)], 'players')
            ->get();

        $rankingSize = max(3, (int) config('graj.home.ranking_size', 10));
        // Логотип і банер сервера завантажує власник у кабінеті; банер замінює сцену гри в картці топу
        $with = ['game', 'fieldValues.field', 'media' => fn ($q) => $q->whereIn('type', ['logo', 'banner'])];

        $overall = Server::ranked()->whereIn('game_id', $games->pluck('id'))->with($with)->limit(max($rankingSize, self::ARENA_SIZE))->get();
        $perGame = $games->mapWithKeys(fn (Game $game) => [
            $game->id => $game->servers()->ranked()->with($with)->limit(max($rankingSize, self::ARENA_SIZE))->get(),
        ]);

        $all = $overall->concat($perGame->flatten(1))->unique('id');
        $day = $this->votesSince($all->pluck('id')->all(), now()->subDay());
        $sparks = $this->sparks($all->pluck('id')->all());

        $row = fn (Server $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'url' => route('servers.show', $s),
            'vote_url' => route('servers.vote', $s),
            'game' => $s->game->name,
            'game_short' => $s->game->shortName(),
            'theme' => $s->game->themeKey(),
            'details' => $s->fieldLabels(),
            'online' => (bool) $s->is_online,
            'players' => (int) $s->players,
            'votes' => (int) $s->votes_month,
            'score' => (int) $s->score,
            'day' => (int) ($day[$s->id] ?? 0),
            'spark' => $sparks[$s->id] ?? [],
            'logo' => self::media($s, 'logo'),
            'image' => self::media($s, 'banner') ?? self::url($s->game->cover),
        ];

        // Вкладки топу: «Усі» і найпопулярніші ігри за онлайном
        $arena = ['all' => ['label' => __('ui.home.all'), 'rows' => $overall->take(self::ARENA_SIZE)->map($row)->values()->all()]];
        foreach ($games->sortByDesc('online_sum')->take(self::ARENA_GAME_TABS) as $game) {
            if ($perGame[$game->id]->isNotEmpty()) {
                $arena[$game->slug] = ['label' => $game->shortName(), 'rows' => $perGame[$game->id]->take(self::ARENA_SIZE)->map($row)->values()->all()];
            }
        }

        $ranking = ['all' => ['label' => __('ui.home.all_games'), 'rows' => $overall->take($rankingSize)->map($row)->values()->all()]];
        foreach ($games as $game) {
            if ($perGame[$game->id]->isNotEmpty()) {
                $ranking[$game->slug] = ['label' => $game->name, 'rows' => $perGame[$game->id]->take($rankingSize)->map($row)->values()->all()];
            }
        }

        return [
            'kpis' => [
                'servers' => (int) $games->sum('servers_count'),
                'online' => (int) $games->sum('online_sum'),
                'votes' => Vote::where('status', 'counted')->where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'season_end' => now()->endOfMonth()->toIso8601String(),
            'month' => now()->translatedFormat('F Y'),
            'arena' => $arena,
            'ranking' => $ranking,
            'games' => $games->map(fn (Game $g) => [
                'name' => $g->name,
                'url' => route('games.show', $g),
                'theme' => $g->themeKey(),
                'servers' => (int) $g->servers_count,
                'online' => (int) $g->getAttribute('online_sum'),
                'image' => self::url($g->cover),
                'icon' => self::url($g->icon),
                'leader' => $perGame[$g->id]->first()?->name,
            ])->values()->all(),
            'marquee' => $this->marquee(),
            'soon' => $this->soon(),
            'spotlight' => $this->spotlight($overall->first()),
            'plans' => $this->plans($games),
        ];
    }

    /** Публічна адреса завантаженого зображення */
    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    /** Перше зображення сервера цього типу з уже завантажених media */
    private static function media(Server $server, string $type): ?string
    {
        return self::url($server->media->firstWhere('type', $type)?->path);
    }

    /** @return array<int, int> server_id => голосів */
    private function votesSince(array $ids, $since): array
    {
        return Vote::whereIn('server_id', $ids)->where('status', 'counted')->where('created_at', '>=', $since)
            ->groupBy('server_id')->selectRaw('server_id, count(*) as n')->pluck('n', 'server_id')
            ->map(fn ($n) => (int) $n)->all();
    }

    /** Середній онлайн за останні 24 зібрані години (у межах тижня, якщо моніторинг мав перерву), для міні-графіків */
    private function sparks(array $ids): array
    {
        return DB::table('server_stats_hourly')->whereIn('server_id', $ids)->where('hour', '>=', now()->subWeek())
            ->orderBy('hour')->get(['server_id', 'players_avg'])
            ->groupBy('server_id')
            ->map(fn ($rows) => $rows->take(-24)->pluck('players_avg')->map(fn ($v) => (int) $v)->values()->all())
            ->all();
    }

    private function marquee(): array
    {
        return Server::published()->where('is_online', true)->where('players', '>', 0)
            ->whereHas('game', fn ($q) => $q->where('is_active', true))
            ->with(['game', 'media' => fn ($q) => $q->where('type', 'logo')])->orderByDesc('players')->limit(12)->get()
            ->map(fn (Server $s) => [
                'name' => $s->name,
                'url' => route('servers.show', $s),
                'game_short' => $s->game->shortName(),
                'theme' => $s->game->themeKey(),
                'logo' => self::media($s, 'logo'),
                'players' => (int) $s->players,
            ])->all();
    }

    private function soon(): array
    {
        // На головній — лише оплачені анонси (платна послуга «Скоро відкриття», ТЗ 3.7); на сторінках ігор — усі
        return Server::published()->where('opens_at', '>', today())
            ->whereHas('game', fn ($q) => $q->where('is_active', true))
            ->whereHas('orders', fn ($q) => $q->active()->whereHas('tariff', fn ($t) => $t->where('service', 'coming_soon')))
            ->with(['game', 'fieldValues.field', 'media' => fn ($q) => $q->whereIn('type', ['logo', 'banner'])])->orderBy('opens_at')->limit(4)->get()
            ->map(fn (Server $s) => [
                'name' => $s->name,
                'url' => route('servers.show', $s),
                'game' => $s->game->name,
                'theme' => $s->game->themeKey(),
                'details' => $s->fieldLabels(),
                'logo' => self::media($s, 'logo'),
                'image' => self::media($s, 'banner'),
                'opens_at' => $s->opens_at->copy()->startOfDay()->toIso8601String(),
                'opens_label' => $s->opens_at->translatedFormat('j F'),
            ])->all();
    }

    /** Лідер місяця для блоку власникам: голоси по днях за 30 днів */
    private function spotlight(?Server $leader): ?array
    {
        if (! $leader) {
            return null;
        }

        $byDay = DB::table('server_stats_hourly')->where('server_id', $leader->id)->where('hour', '>=', now()->subDays(30)->startOfDay())
            ->get(['hour', 'votes'])
            ->groupBy(fn ($r) => substr((string) $r->hour, 0, 10))
            ->map(fn ($rows) => (int) $rows->sum('votes'));

        $days = [];
        for ($d = 29; $d >= 0; $d--) {
            $days[] = $byDay[now()->subDays($d)->toDateString()] ?? 0;
        }

        return [
            'name' => $leader->name,
            'url' => route('servers.show', $leader),
            'votes' => (int) $leader->votes_month,
            'uptime' => (float) $leader->uptime,
            'days' => $days,
            'from' => now()->subDays(29)->translatedFormat('j M'),
            'to' => now()->translatedFormat('j M'),
        ];
    }

    /** Найнижчі ціни на 30 днів серед ігор */
    private function plans(Collection $games): array
    {
        $price = fn (string $service) => Tariff::where('service', $service)->where('is_active', true)->where('days', 30)->min('price');

        return [
            'vip' => $price('vip'),
            'highlight' => $price('highlight'),
            'vip_slots' => (int) ($games->min('vip_slots') ?? 5),
        ];
    }
}
