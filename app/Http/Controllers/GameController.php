<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\MonthlyWinner;
use App\Models\Server;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GameController extends Controller
{
    public const SORTS = ['votes', 'online', 'new', 'opening'];

    public const PER_PAGE = 25;

    public function show(Request $request, Game $game): View
    {
        abort_unless($game->is_active, 404);

        $game->load('fields');
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'votes';
        $search = trim($request->string('q')->limit(64, '')->value());
        $filters = $game->fields->where('is_filterable', true);
        $with = ['fieldValues.field', 'media' => fn ($q) => $q->where('type', 'logo')];

        $query = $game->servers()->published()->with($with);

        match ($sort) {
            'online' => $query->orderByDesc('is_online')->orderByDesc('players'),
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

        if ($search !== '') {
            $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%');
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
        ]);
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
