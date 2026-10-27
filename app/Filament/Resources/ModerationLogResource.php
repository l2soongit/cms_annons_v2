<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModerationLogResource\Pages;
use App\Models\ModerationLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ModerationLogResource extends Resource
{
    protected static ?string $model = ModerationLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Модерація';

    protected static ?string $modelLabel = 'запис журналу';

    protected static ?string $pluralModelLabel = 'Журнал дій';

    protected static ?int $navigationSort = 9;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Час')->dateTime('d.m.Y H:i'),
                TextColumn::make('moderator.name')->label('Хто')->placeholder('система'),
                TextColumn::make('action')->label('Дія')->badge(),
                TextColumn::make('subject_type')->label('Об’єкт')
                    ->formatStateUsing(fn ($state, ModerationLog $log) => class_basename($state).' #'.$log->subject_id),
                TextColumn::make('reason')->label('Причина')->wrap(),
                TextColumn::make('changes')->label('Зміни')->limit(80)
                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_UNESCAPED_UNICODE)),
            ])
            ->filters([
                SelectFilter::make('moderator')->relationship('moderator', 'name')->label('Модератор'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListModerationLogs::route('/')];
    }
}
