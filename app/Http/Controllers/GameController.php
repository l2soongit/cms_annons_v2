<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\MonthlyWinner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameController extends Controller
{
    public const SORTS = ['votes', 'online', 'new', 'opening'];

    public function show(Request $request, Game $game): View
    {
        abort_unless($game->is_active, 404);

        $game->load('fields');
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'votes';

        $query = $game->servers()->published()->with('fieldValues.field');

        match ($sort) {
            'online' => $query->orderByDesc('is_online')->orderByDesc('players'),
            'new' => $query->latest(),
            'opening' => $query->whereNotNull('opens_at')->orderByDesc('opens_at'),
            default => $query->orderByDesc('score')->orderByDesc('votes_month'),
        };

        foreach ($game->fields->where('is_filterable', true) as $field) {
            if ($value = $request->string($field->key)->value()) {
                $query->whereHas('fieldValues', fn (Builder $q) => $q
                    ->where('game_field_id', $field->id)
                    ->where('value', $value));
            }
        }

        $vip = $game->servers()
            ->published()
            ->whereHas('orders', fn (Builder $q) => $q->active()->whereHas('tariff', fn ($t) => $t->where('service', 'vip')))
            ->with('fieldValues.field')
            ->orderByDesc('score')
            ->limit($game->vip_slots)
            ->get();

        $servers = $query->paginate(25)->withQueryString();

        $comingSoon = $game->servers()->published()
            ->where('opens_at', '>', now())
            ->orderBy('opens_at')
            ->limit(6)
            ->get();

        $winners = MonthlyWinner::with('server:id,slug')
            ->where('game_id', $game->id)
            ->where('month', '>=', now()->subMonths(6)->startOfMonth())
            ->orderByDesc('month')
            ->orderBy('place')
            ->get()
            ->groupBy(fn ($w) => $w->month->toDateString());

        return view('games.show', compact('game', 'servers', 'vip', 'sort', 'comingSoon', 'winners'));
    }
}
