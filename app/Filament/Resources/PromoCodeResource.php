<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromoCodeResource\Pages;
use App\Models\PromoCode;
use App\Models\Tariff;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Платні послуги';

    protected static ?string $modelLabel = 'промокод';

    protected static ?string $pluralModelLabel = 'Промокоди';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('billing.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('code')->label('Код')->required()->maxLength(32)->alphaDash()->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (string $state) => mb_strtoupper($state)),
                TextInput::make('discount_percent')->label('Знижка, %')->numeric()->minValue(1)->maxValue(100)->required(),
                Select::make('service')->label('Лише для послуги')->options(Tariff::SERVICES)->placeholder('Усі послуги'),
                TextInput::make('max_uses')->label('Скільки разів можна використати')->numeric()->minValue(1)->placeholder('Без обмежень'),
                DateTimePicker::make('expires_at')->label('Діє до'),
                Toggle::make('is_active')->label('Активний')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('code')->label('Код')->searchable()->copyable(),
                TextColumn::make('discount_percent')->label('Знижка')->suffix('%'),
                TextColumn::make('service')->label('Послуга')->formatStateUsing(fn (?string $state) => Tariff::SERVICES[$state] ?? $state)->placeholder('усі'),
                TextColumn::make('used')->label('Використано')->formatStateUsing(fn ($state, PromoCode $record) => $state.($record->max_uses ? ' / '.$record->max_uses : '')),
                TextColumn::make('expires_at')->label('Діє до')->dateTime('d.m.Y H:i')->placeholder('безстроково'),
                IconColumn::make('is_active')->label('Активний')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromoCodes::route('/'),
            'create' => Pages\CreatePromoCode::route('/create'),
            'edit' => Pages\EditPromoCode::route('/{record}/edit'),
        ];
    }
}
