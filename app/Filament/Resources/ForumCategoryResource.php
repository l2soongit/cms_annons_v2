<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ForumCategoryResource\Pages;
use App\Filament\Support\Localized;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\Game;
use App\Models\User;
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

/** Розділи форуму: блоки, ігри, права (хто бачить, пише, відповідає), модерація розділу й модератори */
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
                Select::make('topic_types')->label('Дозволені типи тем')->multiple()->options(ForumTopic::TYPES)
                    ->helperText('Порожньо — усі типи.'),
                Toggle::make('is_active')->label('Показувати на сайті')->default(true),
            ]),
            Section::make('Права')->description('Ролі або «Усі зареєстровані» / «Власники серверів». Адміністратори й модератори розділу мають доступ завжди.')->columns(3)->schema([
                Select::make('view_roles')->label('Хто бачить розділ')->multiple()->options(fn () => self::audiences())
                    ->helperText('Порожньо — усі, зокрема гості. Закритий розділ закриває й підрозділи, зникає зі списків, пошуку й карти сайту.'),
                Select::make('post_roles')->label('Хто створює теми')->multiple()->options(fn () => self::audiences())
                    ->helperText('Порожньо — усі зареєстровані.'),
                Select::make('reply_roles')->label('Хто відповідає')->multiple()->options(fn () => self::audiences())
                    ->helperText('Порожньо — усі зареєстровані.'),
            ]),
            Section::make('Модерація розділу')->columns(3)->schema([
                Toggle::make('moderate_topics')->label('Нові теми — на перевірку')->default(true)
                    ->helperText('Вимкнено — теми публікуються одразу (якщо автофільтр нічого не знайшов).'),
                Select::make('moderate_replies')->label('Перевірка відповідей')->options(ForumCategory::REPLY_MODES)
                    ->placeholder('Як у налаштуваннях спільноти'),
                Select::make('moderators')->label('Модератори розділу')->multiple()->searchable()
                    ->relationship('moderators', 'name')
                    ->getOptionLabelFromRecordUsing(fn (User $u) => trim(($u->nickname ?: $u->name).' · '.$u->email, ' ·'))
                    ->helperText('Модерують цей розділ і його підрозділи на сайті: черга «Модерація», схвалення, приховування, закріплення, закриття тем.'),
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
                TextColumn::make('view_roles')->label('Бачать')->badge()->placeholder('Усі')
                    ->formatStateUsing(fn (string $state) => ForumCategory::AUDIENCES[$state] ?? $state),
                TextColumn::make('post_roles')->label('Хто пише')->badge()->placeholder('Усі')
                    ->formatStateUsing(fn (string $state) => ForumCategory::AUDIENCES[$state] ?? $state),
                IconColumn::make('moderate_topics')->label('Перевірка тем')->boolean(),
                TextColumn::make('moderators.name')->label('Модератори')->badge()->placeholder('—'),
                TextColumn::make('topics_count')->label('Тем')->numeric(),
                TextColumn::make('posts_count')->label('Відповідей')->numeric(),
                IconColumn::make('is_active')->label('На сайті')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    /** Аудиторії для прав розділу: особливі групи й ролі */
    private static function audiences(): array
    {
        return ForumCategory::AUDIENCES + Role::orderBy('name')->pluck('name', 'name')->all();
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
