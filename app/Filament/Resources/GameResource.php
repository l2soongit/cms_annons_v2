<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GameResource\Pages;
use App\Models\Game;
use App\Monitoring\DriverRegistry;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class GameResource extends Resource
{
    protected static ?string $model = Game::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static ?string $modelLabel = 'гра';

    protected static ?string $pluralModelLabel = 'Ігри';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Гра')->columns(2)->schema([
                TextInput::make('name.uk')->label('Назва (uk)')->required(),
                TextInput::make('name.en')->label('Назва (en)'),
                TextInput::make('slug')->required()->unique(ignoreRecord: true)->alphaDash()
                    ->helperText('Частина URL: graj.club/<slug>'),
                Select::make('query_driver')->label('Протокол моніторингу')->options(DriverRegistry::options())->required(),
                TextInput::make('default_port')->label('Порт за замовчуванням')->numeric(),
                TextInput::make('vip_slots')->label('VIP-місць')->numeric()->default(5),
                TextInput::make('sort')->label('Порядок')->numeric()->default(0),
                Toggle::make('is_active')->label('Активна')->default(true),
            ]),
            Section::make('SEO')->columns(2)->collapsed()->schema([
                TextInput::make('seo_title.uk')->label('Title (uk)'),
                TextInput::make('seo_title.en')->label('Title (en)'),
                Textarea::make('seo_description.uk')->label('Description (uk)'),
                Textarea::make('seo_description.en')->label('Description (en)'),
            ]),
            Section::make('Поля сервера')->description('Що власник заповнює для цієї гри і за чим гравці фільтрують рейтинг.')->schema([
                Repeater::make('fields')->relationship()->hiddenLabel()
                    ->orderColumn('sort')->collapsible()
                    ->itemLabel(fn (array $state) => ($state['label']['uk'] ?? null) ?: ($state['key'] ?? null))
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('key')->label('Ключ')->required()->alphaDash(),
                            TextInput::make('label.uk')->label('Назва (uk)')->required(),
                            TextInput::make('label.en')->label('Назва (en)'),
                            Select::make('type')->label('Тип')->required()->options([
                                'select' => 'Список', 'multiselect' => 'Мультивибір', 'text' => 'Текст', 'number' => 'Число',
                            ]),
                        ]),
                        Grid::make(3)->schema([
                            Toggle::make('is_required')->label('Обов’язкове'),
                            Toggle::make('is_filterable')->label('Фільтр у рейтингу'),
                            Toggle::make('in_url')->label('У URL'),
                        ]),
                        Repeater::make('options')->label('Варіанти')->columns(3)->defaultItems(0)
                            ->schema([
                                TextInput::make('value')->label('Значення')->required(),
                                TextInput::make('label')->label('Підпис')->required(),
                                Select::make('driver')->label('Протокол (необов’язково)')->options(DriverRegistry::options()),
                            ]),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('Назва'),
                TextColumn::make('slug'),
                TextColumn::make('query_driver')->label('Протокол'),
                TextColumn::make('servers_count')->counts('servers')->label('Серверів'),
                IconColumn::make('is_active')->label('Активна')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGames::route('/'),
            'create' => Pages\CreateGame::route('/create'),
            'edit' => Pages\EditGame::route('/{record}/edit'),
        ];
    }
}
