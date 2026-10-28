<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VoteResource\Pages;
use App\Models\ModerationLog;
use App\Models\Vote;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Голоси: перевірка підозрілих (ліміт підмережі) і масове анулювання накрутки.
 */
class VoteResource extends Resource
{
    protected static ?string $model = Vote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandThumbUp;

    protected static string|UnitEnum|null $navigationGroup = 'Модерація';

    protected static ?string $modelLabel = 'голос';

    protected static ?string $pluralModelLabel = 'Голоси';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('votes.moderate') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) (Vote::where('status', 'suspicious')->count()) ?: null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('server.name')->label('Сервер')->searchable(),
                TextColumn::make('nickname')->label('Нік')->searchable(),
                TextColumn::make('user.name')->label('Акаунт'),
                TextColumn::make('subnet_hash')->label('Підмережа')->formatStateUsing(fn ($state) => substr((string) $state, 0, 8))
                    ->tooltip('Однакові значення = одна підмережа /24'),
                TextColumn::make('status')->label('Статус')->badge()
                    ->color(fn (string $state) => ['counted' => 'success', 'suspicious' => 'warning', 'annulled' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('callback_status')->label('Callback'),
                TextColumn::make('created_at')->label('Час')->dateTime('d.m H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['suspicious' => 'Підозрілі', 'counted' => 'Зараховані', 'annulled' => 'Анульовані']),
                SelectFilter::make('server')->relationship('server', 'name')->searchable()->label('Сервер'),
            ])
            ->toolbarActions([
                BulkAction::make('count')->label('Зарахувати')->color('success')->requiresConfirmation()
                    ->action(fn (Collection $records) => self::setStatus($records, 'counted')),
                BulkAction::make('annul')->label('Анулювати')->color('danger')->requiresConfirmation()
                    ->action(fn (Collection $records) => self::setStatus($records, 'annulled')),
            ]);
    }

    private static function setStatus(Collection $votes, string $status): void
    {
        Vote::whereKey($votes->modelKeys())->update(['status' => $status]);

        foreach ($votes->groupBy('server_id') as $serverVotes) {
            /** @var Vote $first */
            $first = $serverVotes->first();
            ModerationLog::record($first->server, "votes_{$status}", changes: ['vote_ids' => $serverVotes->modelKeys()]);
        }
        // Місця перерахує планувальник протягом 5 хвилин
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListVotes::route('/')];
    }
}
