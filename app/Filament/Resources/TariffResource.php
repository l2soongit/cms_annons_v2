<?php

namespace App\Filament\Resources;

use App\Billing\Money;
use App\Filament\Resources\TariffResource\Pages;
use App\Filament\Support\Localized;
use App\Models\Banner;
use App\Models\Tariff;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Тарифи платних послуг: окремо на кожну гру (ТЗ 3.7), ціни й терміни змінюються тут.
 */
class TariffResource extends Resource
{
    protected static ?string $model = Tariff::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Платні послуги';

    protected static ?string $modelLabel = 'тариф';

    protected static ?string $pluralModelLabel = 'Тарифи';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('billing.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('game_id')->label('Гра')->relationship('game', 'slug')->placeholder('Усі ігри'),
                Select::make('service')->label('Послуга')->options(Tariff::SERVICES)->required()->live(),
                ...Localized::fields('name', 'Назва', fn (string $path) => TextInput::make($path)->maxLength(80), required: true),
                TextInput::make('days')->label('Днів')->numeric()->minValue(1)->maxValue(365)
                    ->visible(fn (Get $get) => in_array($get('service'), ['vip', 'highlight', 'banner'], true))
                    ->required(fn (Get $get) => in_array($get('service'), ['vip', 'highlight', 'banner'], true)),
                Select::make('placement')->label('Рекламне місце')
                    ->options(collect(Banner::PLACEMENTS)->map(fn ($p) => "{$p[0]} ({$p[1]}×{$p[2]})"))
                    ->helperText('Шапка й сайдбар — спільні для всього сайту (гру не обирайте); «Сторінка гри» — для вибраної гри.')
                    ->visible(fn (Get $get) => $get('service') === 'banner')->required(fn (Get $get) => $get('service') === 'banner'),
                TextInput::make('points')->label('Бонусних балів')->numeric()->minValue(1)
                    ->helperText('Діють до кінця місяця; вага балів у формулі рейтингу — у Налаштуваннях.')
                    ->visible(fn (Get $get) => $get('service') === 'boost')->required(fn (Get $get) => $get('service') === 'boost'),
                TextInput::make('price')->label('Ціна, ₴')->numeric()->minValue(0)->required(),
                Toggle::make('is_active')->label('Продається')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('game_id')
            ->columns([
                TextColumn::make('game.slug')->label('Гра')->placeholder('усі')->sortable(),
                TextColumn::make('service')->label('Послуга')->formatStateUsing(fn (string $state) => Tariff::SERVICES[$state] ?? $state),
                TextColumn::make('name')->label('Назва'),
                TextColumn::make('days')->label('Днів')->placeholder('—'),
                TextColumn::make('placement')->label('Місце')->formatStateUsing(fn (?string $state) => Banner::PLACEMENTS[$state][0] ?? $state)->placeholder('—')->toggleable(),
                TextColumn::make('price')->label('Ціна')->formatStateUsing(fn ($state) => Money::format($state))->sortable(),
                IconColumn::make('is_active')->label('Продається')->boolean(),
            ])
            ->filters([
                SelectFilter::make('game')->relationship('game', 'slug')->label('Гра'),
                SelectFilter::make('service')->options(Tariff::SERVICES)->label('Послуга'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTariffs::route('/'),
            'create' => Pages\CreateTariff::route('/create'),
            'edit' => Pages\EditTariff::route('/{record}/edit'),
        ];
    }
}
