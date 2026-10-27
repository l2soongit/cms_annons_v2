<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Server;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $games = Game::active()
            ->withCount(['servers' => fn ($q) => $q->published()])
            ->get()
            ->each(fn (Game $game) => $game->setRelation('top', $game->servers()
                ->ranked()
                ->with('fieldValues.field')
                ->limit(5)
                ->get()));

        $comingSoon = Server::published()
            ->where('opens_at', '>', now())
            ->with('game')
            ->orderBy('opens_at')
            ->limit(9)
            ->get();

        return view('home', compact('games', 'comingSoon'));
    }
}
