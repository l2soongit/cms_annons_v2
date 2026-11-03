<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BonusRewardResource\Pages;
use App\Filament\Support\Localized;
use App\Models\BonusReward;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Магазин балів: прикраси профілю, закріплення оголошення клану, знижки на платні послуги */
class BonusRewardResource extends Resource
{
    protected static ?string $model = BonusReward::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'Спільнота';

    protected static ?string $modelLabel = 'нагорода';

    protected static ?string $pluralModelLabel = 'Магазин балів';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('community.bonus') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Нагорода')->columns(2)->schema([
                ...Localized::fields('name', 'Назва', fn (string $path) => TextInput::make($path)->maxLength(80), required: true),
                ...Localized::fields('description', 'Опис', fn (string $path) => Textarea::make($path)->rows(2)->maxLength(200)),
            ]),
            Section::make('Умови')->columns(3)->schema([
                Select::make('kind')->label('Що дає')->options(BonusReward::KINDS)->required()->live(),
                TextInput::make('value')->label(fn (Get $get) => match ($get('kind')) {
                    'name_color' => 'Колір ніку (#fbbf24)',
                    'service_discount' => 'Знижка, %',
                    'frame' => 'Рамка: gold, fire, ice, neon',
                    'badge' => 'Значок (емодзі)',
                    'title' => 'Текст звання (до 40 символів)',
                    'name_effect' => 'Ефект: rainbow, fire, ice, glow',
                    default => 'Значення',
                })->maxLength(40)->required(fn (Get $get) => ! in_array($get('kind'), BonusReward::FLAG_KINDS, true)),
                TextInput::make('cost')->label('Ціна, балів')->numeric()->minValue(1)->required(),
                TextInput::make('duration_days')->label('Діє днів')->numeric()->minValue(1)->helperText('Порожньо — назавжди.'),
                Select::make('audience')->label('Для кого')->options(BonusReward::AUDIENCES)->default('all')->required(),
                TextInput::make('sort')->label('Порядок')->numeric()->default(0),
                Toggle::make('is_active')->label('У продажу')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('Нагорода')->description(fn (BonusReward $r) => BonusReward::KINDS[$r->kind] ?? $r->kind),
                TextColumn::make('value')->label('Значення'),
                TextColumn::make('cost')->label('Ціна')->suffix(' б.')->sortable(),
                TextColumn::make('duration_days')->label('Днів')->placeholder('∞'),
                TextColumn::make('audience')->label('Для кого')->formatStateUsing(fn (string $state) => BonusReward::AUDIENCES[$state] ?? $state),
                IconColumn::make('is_active')->label('У продажу')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBonusRewards::route('/'),
            'create' => Pages\CreateBonusReward::route('/create'),
            'edit' => Pages\EditBonusReward::route('/{record}/edit'),
        ];
    }
}
