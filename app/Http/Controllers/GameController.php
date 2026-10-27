<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameController extends Controller
{
    public function show(Request $request, Game $game): View
    {
        abort_unless($game->is_active, 404);

        $game->load('fields');

        $query = $game->servers()->ranked()->with('fieldValues.field');

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

        return view('games.show', compact('game', 'servers', 'vip'));
    }
}
