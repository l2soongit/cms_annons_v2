<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ForumCategoryResource\Pages;
use App\Filament\Support\Localized;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\Game;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;
use UnitEnum;

/** Розділи форуму: блоки, ігри, хто може писати й які типи тем дозволені */
class ForumCategoryResource extends Resource
{
    protected static ?string $model = ForumCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Спільнота';

    protected static ?string $modelLabel = 'розділ форуму';

    protected static ?string $pluralModelLabel = 'Розділи форуму';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('community.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Розділ')->columns(2)->schema([
                ...Localized::fields('name', 'Назва', fn (string $path) => TextInput::make($path)->maxLength(80), required: true),
                ...Localized::fields('description', 'Опис', fn (string $path) => Textarea::make($path)->rows(2)->maxLength(200)),
            ]),
            Section::make('Налаштування')->columns(3)->schema([
                TextInput::make('slug')->label('Адреса')->required()->alphaDash()->maxLength(64)->unique(ignoreRecord: true)
                    ->prefix(url('/community/c').'/'),
                Select::make('group')->label('Блок')->options(ForumCategory::GROUPS)->required()->default('games'),
                TextInput::make('icon')->label('Іконка (емодзі)')->maxLength(8)->helperText('Для розділу гри — іконка гри.'),
                Select::make('game_id')->label('Гра')->options(fn () => Game::orderBy('sort')->get()->mapWithKeys(fn (Game $g) => [$g->id => $g->name])),
                Select::make('parent_id')->label('Підрозділ у')->options(fn (?ForumCategory $record) => ForumCategory::whereNull('parent_id')
                    ->when($record, fn ($q) => $q->whereKeyNot($record->id))->orderBy('sort')->get()->mapWithKeys(fn (ForumCategory $c) => [$c->id => $c->name])),
                TextInput::make('sort')->label('Порядок')->numeric()->default(0),
                Select::make('post_roles')->label('Хто створює теми')->multiple()
                    ->options(fn () => Role::orderBy('name')->pluck('name', 'name'))
                    ->helperText('Порожньо — усі зареєстровані. Відповідати можуть усі.'),
                Select::make('topic_types')->label('Дозволені типи тем')->multiple()->options(ForumTopic::TYPES)
                    ->helperText('Порожньо — усі типи.'),
                Toggle::make('is_active')->label('Показувати на сайті')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('Розділ')->description(fn (ForumCategory $c) => ForumCategory::GROUPS[$c->group] ?? $c->group),
                TextColumn::make('slug')->label('Адреса'),
                TextColumn::make('post_roles')->label('Хто пише')->badge()->placeholder('Усі'),
                TextColumn::make('topics_count')->label('Тем')->numeric(),
                TextColumn::make('posts_count')->label('Відповідей')->numeric(),
                IconColumn::make('is_active')->label('На сайті')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListForumCategories::route('/'),
            'create' => Pages\CreateForumCategory::route('/create'),
            'edit' => Pages\EditForumCategory::route('/{record}/edit'),
        ];
    }
}
