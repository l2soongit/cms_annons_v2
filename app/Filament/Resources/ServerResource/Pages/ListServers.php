<?php

namespace App\Filament\Resources\ServerResource\Pages;

use App\Enums\ServerStatus;
use App\Filament\Resources\ServerResource;
use App\Models\Game;
use App\Models\Server;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Сервери вкладками: усі, черга модерації й окремо кожна гра — з кількістю серверів на вкладці.
 */
class ListServers extends ListRecords
{
    protected static string $resource = ServerResource::class;

    public function getTabs(): array
    {
        // Filament передає аргументи за назвою — параметр має зватися саме $query
        $queue = fn (Builder $query) => $query->where(fn ($w) => $w->where('status', ServerStatus::Pending)->orWhereNotNull('pending_changes'));
        $counts = Server::query()->selectRaw('game_id, count(*) as n')->groupBy('game_id')->pluck('n', 'game_id');

        $tabs = [
            'all' => Tab::make('Усі')->badge(Server::count()),
            'queue' => Tab::make('На перевірці')->icon('heroicon-o-clock')
                ->badge($queue(Server::query())->count() ?: null)->badgeColor('warning')
                ->modifyQueryUsing($queue),
        ];

        foreach (Game::orderBy('sort')->get() as $game) {
            $tabs[$game->slug] = Tab::make($game->shortName())->badge($counts[$game->id] ?? 0)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('game_id', $game->id));
        }

        return $tabs;
    }
}
