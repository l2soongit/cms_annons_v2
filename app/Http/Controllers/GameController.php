<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\MonthlyWinner;
use App\Models\Server;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

class GameController extends Controller
{
    public const SORTS = ['votes', 'online', 'new', 'opening', 'ping'];

    /** Швидкі фільтри (меню закупівлі на сторінці CS): ?vac=1&lowping=1 */
    public const PERKS = ['notempty', 'free', 'tick128', 'vac', 'lowping', 'big'];

    private const LOW_PING_MS = 50;

    private const BIG_SLOTS = 24;

    public const PER_PAGE = 25;

    public function show(Request $request, Game $game): View
    {
        abort_unless($game->is_active, 404);

        $game->load('fields');
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'votes';
        $search = trim($request->string('q')->limit(64, '')->value());
        $filters = $game->fields->where('is_filterable', true);
        // Логотип (герб L2) і банер (обкладинка картки GTA) завантажує власник у кабінеті
        $with = ['fieldValues.field', 'media' => fn ($q) => $q->whereIn('type', ['logo', 'banner'])];

        $query = $game->servers()->published()->with($with);

        match ($sort) {
            'online' => $query->orderByDesc('is_online')->orderByDesc('players'),
            'ping' => $query->orderByDesc('is_online')->orderByRaw('ping_ms is null')->orderBy('ping_ms'),
            'new' => $query->latest(),
            'opening' => $query->whereNotNull('opens_at')->orderByDesc('opens_at'),
            default => $query->orderByDesc('score')->orderByDesc('votes_month'),
        };

        $active = [];
        foreach ($filters as $field) {
            if ($value = $request->string($field->key)->value()) {
                $active[$field->key] = $value;
                $query->whereHas('fieldValues', fn (Builder $q) => $q
                    ->where('game_field_id', $field->id)
                    ->where('value', $value));
            }
        }

        foreach (self::PERKS as $perk) {
            if ($request->boolean($perk)) {
                $active[$perk] = '1';
                $this->applyPerk($query, $perk, $game);
            }
        }

        if ($search !== '') {
            // Назва або адреса: гравці часто шукають сервер за IP
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('host', 'like', $like));
        }

        $servers = $query->paginate(self::PER_PAGE)->withQueryString();

        // VIP показуємо лише без фільтрів і пошуку на першій сторінці — як закріплення над рейтингом
        $vip = $active || $search !== '' || ! $servers->onFirstPage() ? collect() : $game->servers()
            ->published()
            ->whereHas('orders', fn (Builder $q) => $q->active()->whereHas('tariff', fn ($t) => $t->where('service', 'vip')))
            ->with($with)
            ->orderByDesc('score')
            ->limit($game->vip_slots)
            ->get();

        $theme = "games.themes.{$game->themeKey()}";
        if (! view()->exists($theme)) {
            $comingSoon = $game->servers()->published()->where('opens_at', '>', now())->orderBy('opens_at')->limit(6)->get();
            $winners = $this->winners($game, 6)->groupBy(fn ($w) => $w->month->toDateString());

            return view('games.show', compact('game', 'servers', 'vip', 'sort', 'comingSoon', 'winners'));
        }

        // Оновлення списку без перезавантаження (фільтри, пошук, «Показати ще») — лише рядки
        if ($request->header('X-Partial') === 'list') {
            return view("{$theme}-list", compact('game', 'servers', 'vip', 'active', 'search'));
        }

        return view($theme, [
            'game' => $game,
            'servers' => $servers,
            'vip' => $vip,
            'sort' => $sort,
            'active' => $active,
            'search' => $search,
            'filters' => $filters,
            'counts' => $this->optionCounts($game),
            'stats' => $this->stats($game),
            'comingSoon' => $game->servers()->published()->where('opens_at', '>', today())
                ->with('fieldValues.field')->orderBy('opens_at')->limit(3)->get(),
            'fame' => $this->winners($game, 4)->where('place', 1)->values(),
            'shares' => $this->shares($game, $filters->first()),
            'perks' => $game->themeKey() === 'cs' ? $this->perkCounts($game) : [],
            'maps' => $game->themeKey() === 'cs' ? $this->mapShares($game) : [],
        ]);
    }

    private function applyPerk(Builder|HasMany $query, string $perk, Game $game): void
    {
        match ($perk) {
            'notempty' => $query->where('is_online', true)->where('players', '>', 0),
            'free' => $query->where('is_online', true)->whereColumn('players', '<', 'max_players'),
            'tick128' => $query->whereHas('fieldValues', fn (Builder $q) => $q
                ->where('game_field_id', $game->fields->firstWhere('key', 'tickrate')?->id)
                ->where('value', '128')),
            'vac' => $query->where('vac', true),
            'lowping' => $query->where('is_online', true)->where('ping_ms', '<=', self::LOW_PING_MS),
            'big' => $query->where('max_players', '>=', self::BIG_SLOTS),
            // Сюди потрапляють лише значення з self::PERKS
            default => throw new InvalidArgumentException("Unknown quick filter: {$perk}"),
        };
    }

    /** Скільки серверів підходить під кожен швидкий фільтр окремо */
    private function perkCounts(Game $game): array
    {
        return collect(self::PERKS)->mapWithKeys(function (string $perk) use ($game) {
            $query = $game->servers()->published();
            $this->applyPerk($query, $perk, $game);

            return [$perk => $query->count()];
        })->all();
    }

    /** Частка гравців онлайн за поточною картою */
    private function mapShares(Game $game): array
    {
        $rows = $game->servers()->published()->where('is_online', true)->whereNotNull('current_map')
            ->groupBy('current_map')->selectRaw('current_map, sum(players) as players')
            ->pluck('players', 'current_map')->map(fn ($n) => (int) $n)->filter()->sortDesc()->take(5);

        $total = max(1, $rows->sum());
        $top = max(1, (int) $rows->first());

        return $rows->map(fn ($n, $map) => [
            'map' => $map,
            'percent' => (int) round($n / $total * 100),
            'width' => round($n / $top * 100, 1),
        ])->values()->all();
    }

    /** @return Collection<int, MonthlyWinner> */
    private function winners(Game $game, int $months): Collection
    {
        return MonthlyWinner::with('server:id,slug')
            ->where('game_id', $game->id)
            ->where('month', '>=', now()->subMonths($months)->startOfMonth())
            ->orderByDesc('month')
            ->orderBy('place')
            ->get();
    }

    /** Скільки опублікованих серверів має кожне значення фільтра: [game_field_id][value] => n */
    private function optionCounts(Game $game): array
    {
        $counts = [];
        DB::table('server_field_values')
            ->join('servers', 'servers.id', '=', 'server_field_values.server_id')
            ->where('servers.game_id', $game->id)
            ->where('servers.status', 'published')
            ->whereNull('servers.deleted_at')
            ->groupBy('server_field_values.game_field_id', 'server_field_values.value')
            ->get(['server_field_values.game_field_id as field', 'server_field_values.value', DB::raw('count(*) as n')])
            ->each(function ($row) use (&$counts) {
                $counts[$row->field][$row->value] = (int) $row->n;
            });

        return $counts;
    }

    private function stats(Game $game): array
    {
        $published = $game->servers()->published();

        return [
            'servers' => (clone $published)->count(),
            'online' => (int) (clone $published)->where('is_online', true)->sum('players'),
            'votes' => Vote::where('status', 'counted')->where('created_at', '>=', now()->startOfMonth())
                ->whereIn('server_id', $game->servers()->select('id'))->count(),
            'season_end' => now()->endOfMonth()->toIso8601String(),
        ];
    }

    /** Частка гравців онлайн за значеннями першого фільтра (для L2 — хроніки) */
    private function shares(Game $game, $field): array
    {
        if (! $field) {
            return [];
        }

        $labels = collect($field->options)->pluck('label', 'value');
        $rows = Server::query()->published()->where('game_id', $game->id)->where('is_online', true)
            ->join('server_field_values', 'server_field_values.server_id', '=', 'servers.id')
            ->where('server_field_values.game_field_id', $field->id)
            ->groupBy('server_field_values.value')
            ->selectRaw('server_field_values.value as value, sum(servers.players) as players')
            ->pluck('players', 'value')
            ->map(fn ($n) => (int) $n)
            ->filter()
            ->sortDesc();

        $total = max(1, $rows->sum());
        $top = max(1, (int) $rows->first());

        return $rows->map(fn ($n, $value) => [
            'label' => $labels[$value] ?? $value,
            'percent' => (int) round($n / $total * 100),
            'width' => round($n / $top * 100, 1),
        ])->values()->all();
    }
}
