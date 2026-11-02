<?php

namespace App\Filament\Widgets;

use App\Enums\ServerStatus;
use App\Filament\Resources\ServerResource;
use App\Models\Server;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Інфопанель: останні додані сервери зі статусом — швидкий перехід до перевірки.
 */
class LatestServers extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Нові сервери';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return ServerResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Server::query()->with('game')->latest()->limit(8))
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Назва')->weight('bold'),
                TextColumn::make('game.name')->label('Гра'),
                TextColumn::make('status')->label('Статус')->badge()
                    ->formatStateUsing(fn (ServerStatus $state) => $state->label())
                    ->color(fn (ServerStatus $state) => match ($state) {
                        ServerStatus::Published => 'success',
                        ServerStatus::Pending => 'warning',
                        ServerStatus::Rejected, ServerStatus::Blocked => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('host')->label('Адреса')->fontFamily('mono')->toggleable(),
                TextColumn::make('created_at')->label('Додано')->since(),
            ])
            ->recordActions([
                Action::make('open')->label('Відкрити')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Server $record) => ServerResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
