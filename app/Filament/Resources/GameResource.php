<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GameResource\Pages;
use App\Filament\Support\ImageField;
use App\Filament\Support\Localized;
use App\Models\Game;
use App\Models\GameField;
use App\Monitoring\DriverRegistry;
use App\Support\Locales;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
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
        return auth()->user()?->can('games.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        // Вкладки за змістом: основне, оформлення, сторінка гри, поля сервера, SEO. Тексти різними мовами — підвкладками.
        return $schema->components([
            Tabs::make('game')->persistTabInQueryString()->columnSpanFull()->tabs([
                Tab::make('Основне')->icon(Heroicon::OutlinedInformationCircle)->schema([
                    Section::make('Гра')->schema([
                        Grid::make(count(Locales::all()))->schema(
                            Localized::fields('name', 'Назва', fn (string $path) => TextInput::make($path), required: true),
                        ),
                        Grid::make(4)->schema([
                            TextInput::make('slug')->label('Адреса')->required()->unique(ignoreRecord: true)->alphaDash()
                                ->prefix('graj.club/')->columnSpan(2),
                            TextInput::make('sort')->label('Порядок у списках')->numeric()->default(0),
                            TextInput::make('vip_slots')->label('VIP-місць')->numeric()->default(5),
                        ]),
                        Toggle::make('is_active')->label('Гра активна — показується на сайті')->default(true),
                    ]),
                    Section::make('Моніторинг')->description('Як сайт опитує сервери цієї гри.')->columns(2)->schema([
                        Select::make('query_driver')->label('Протокол')->options(DriverRegistry::options())->required(),
                        TextInput::make('default_port')->label('Порт за замовчуванням')->numeric(),
                    ]),
                ]),

                Tab::make('Оформлення')->icon(Heroicon::OutlinedPaintBrush)->schema([
                    Section::make('Стиль і зображення')->columns(3)->schema([
                        Select::make('theme')->label('Стиль оформлення')->options(Game::THEMES)->placeholder('Нейтральний')->live()->columnSpanFull()
                            ->helperText('Колір, шрифт і сцена гри на головній і в списках; для ігор з власним дизайном — уся сторінка гри.'),
                        ImageField::make('cover', 'games', 'cover')->label('Обкладинка')->columnSpan(2)
                            ->imageEditorAspectRatios(['16:9', '4:3'])
                            ->helperText('Картка гри на головній, у топі місяця й фон банера. До 10 МБ, стискається у WebP.'),
                        ImageField::make('icon', 'games/icons', 'icon')->label('Іконка')
                            ->imageEditorAspectRatios(['1:1'])
                            ->helperText('Квадратна, бажано з прозорим фоном.'),
                    ]),
                    Section::make('Тексти банера')->description('Банер угорі сторінки гри. Порожнє поле — значення за замовчуванням.')->schema([
                        self::perLocale(fn (string $locale) => [
                            TextInput::make("hero_title.{$locale}")->label('Заголовок банера')->maxLength(60)
                                ->helperText($locale !== Locales::default() ? null : fn (Get $get) => match ($get('theme')) {
                                    'l2', 'gta' => 'Напр. «Lineage II». Останнє слово виділяється кольором стилю. Порожньо — назва гри.',
                                    'cs' => 'Напр. «Counter-Strike». Частина після дефіса або останнє слово виділяється. Порожньо — назва гри.',
                                    'mc' => 'Пишеться великими піксельними літерами; одне довге слово ділиться навпіл. Порожньо — назва гри.',
                                    default => 'Порожньо — назва гри.',
                                }),
                            TextInput::make("eyebrow.{$locale}")->label('Підпис над заголовком')->maxLength(80)
                                ->helperText($locale === Locales::default() ? 'Напр. «Фентезі MMORPG · з 2003 року».' : null),
                            Textarea::make("description.{$locale}")->label('Опис під заголовком')->rows(2)->maxLength(240)->columnSpanFull(),
                        ], columns: 2),
                    ]),
                ]),

                Tab::make('Сторінка гри')->icon(Heroicon::OutlinedRectangleGroup)->schema([
                    Section::make('Блоки')->description('Що показувати на сторінці гри з власним дизайном.')->schema([
                        Grid::make(3)->schema(collect(Game::BLOCKS)->map(fn ($label, $key) => Toggle::make("page.blocks.{$key}")->label($label)->default(true))->values()->all()),
                        Grid::make(2)->schema([
                            TextInput::make('page.soon_limit')->label('Анонсів «Скоро відкриття»')->numeric()->minValue(1)->maxValue(6)
                                ->placeholder((string) Game::SOON_LIMIT)->helperText('Від 1 до 6 найближчих відкриттів.'),
                            Select::make('page.popular_field')->label('Блок популярності рахує за полем')->placeholder('Перше поле-фільтр')
                                ->options(fn (?Game $record) => $record?->fields->where('is_filterable', true)->mapWithKeys(fn ($f) => [$f->key => $f->label])->all() ?? [])
                                ->helperText('Напр. хроніки для Lineage 2, доповнення для WoW.'),
                        ]),
                    ]),
                    Section::make('Тексти стилю')->description('Підписи й заголовки, характерні для дизайну гри. Порожнє поле — текст за замовчуванням (показаний сірим).')
                        ->visible(fn (Get $get) => isset(Game::THEME_TEXTS[$get('theme')]))
                        ->schema(fn (Get $get) => [self::themeTextTabs((string) $get('theme'))]),
                ]),

                Tab::make('Поля сервера')->icon(Heroicon::OutlinedAdjustmentsHorizontal)->schema([
                    Section::make('Поля сервера')->description('Що власник заповнює для цієї гри і за чим гравці фільтрують рейтинг.')->schema([
                        Repeater::make('fields')->relationship()->hiddenLabel()->addActionLabel('Додати поле')
                            ->orderColumn('sort')->collapsible()->collapsed()
                            ->itemLabel(fn (array $state) => ($state['label'][Locales::default()] ?? null) ?: ($state['key'] ?? null))
                            ->schema([
                                Grid::make(4)->schema([
                                    TextInput::make('key')->label('Ключ')->required()->alphaDash(),
                                    Select::make('type')->label('Тип')->required()->options(GameField::TYPES)->live()
                                        ->helperText(fn (Get $get) => $get('type') === 'boolean' ? 'Галочка у формі сервера; у фільтрі — «Так» / «Ні».' : null),
                                    Toggle::make('is_required')->label('Обов’язкове')->inline(false),
                                    Toggle::make('is_filterable')->label('Фільтр у рейтингу')->inline(false),
                                ]),
                                Grid::make(count(Locales::all()))->schema(
                                    Localized::fields('label', 'Назва', fn (string $path) => TextInput::make($path), required: true),
                                ),
                                Toggle::make('in_url')->label('Значення поля — у адресі сторінки (/lineage2/interlude)'),
                                Repeater::make('options')->label('Варіанти')->columns(4)->defaultItems(0)->addActionLabel('Додати варіант')
                                    ->visible(fn (Get $get) => in_array($get('type'), ['select', 'multiselect'], true))
                                    ->itemLabel(fn (array $state) => $state['label'] ?? null)->collapsible()->collapsed()
                                    ->schema([
                                        TextInput::make('value')->label('Значення')->required()->helperText('У посиланні фільтра: ?поле=значення'),
                                        TextInput::make('label')->label('Підпис')->required(),
                                        TextInput::make('short')->label('Коротка назва')->maxLength(16)
                                            ->helperText('Для вузьких перемикачів: «1.6» замість «CS 1.6».'),
                                        TextInput::make('note')->label('Підпис під варіантом')->maxLength(24)
                                            ->helperText('Напр. рушій у перемикачі версій CS: «Source 2».'),
                                        ColorPicker::make('color')->label('Колір позначки')
                                            ->helperText('Порожньо — колір стилю.'),
                                        TextInput::make('connect')->label('Команда підключення')->maxLength(80)->placeholder('connect {address}')
                                            ->helperText('{address} — адреса сервера. Порожньо — лише адреса.'),
                                        Select::make('driver')->label('Протокол (необов’язково)')->options(DriverRegistry::options())->columnSpan(2),
                                        KeyValue::make('labels')->label('Переклади підпису')->keyLabel('Мова')->valueLabel('Підпис')->columnSpanFull()
                                            ->addActionLabel('Додати переклад')
                                            ->helperText(fn () => 'Напр. en → Hardcore. Мови сайту: '.implode(', ', array_keys(Locales::all())).'. Без перекладу показується основний підпис.'),
                                    ]),
                            ]),
                    ]),
                ]),

                Tab::make('SEO')->icon(Heroicon::OutlinedMagnifyingGlass)->schema([
                    Section::make('Пошуковики')->description('Title і description сторінки гри. Порожньо — згенеровані автоматично.')->schema([
                        self::perLocale(fn (string $locale) => [
                            TextInput::make("seo_title.{$locale}")->label('Title')->maxLength(70),
                            Textarea::make("seo_description.{$locale}")->label('Description')->rows(3)->maxLength(200),
                        ]),
                    ]),
                ]),
            ]),
        ]);
    }

    /**
     * Підвкладки за мовами сайту: «Українська», «English»… — однакові поля для кожної мови.
     *
     * @param  \Closure(string $locale): array  $fields
     */
    private static function perLocale(\Closure $fields, int $columns = 1): Tabs
    {
        return Tabs::make('locales')->contained(false)->tabs(collect(Locales::all())->map(
            fn ($name, string $locale) => Tab::make(is_string($name) ? $name : strtoupper($locale))->columns($columns)->schema($fields($locale))
        )->values()->all());
    }

    /** Тексти стилю за мовами; сірим — текст за замовчуванням */
    private static function themeTextTabs(string $theme): Tabs
    {
        return self::perLocale(fn (string $locale) => collect(Game::THEME_TEXTS[$theme] ?? [])->map(function (string $label, string $key) use ($locale) {
            $default = trans("ui.{$key}", [], $locale);
            $path = 'page.texts.'.$locale.'.'.Game::textKey($key);

            return (is_array($default) ? Textarea::make($path)->rows(4) : TextInput::make($path)->maxLength(160))
                ->label($label)
                ->placeholder(is_array($default) ? implode("\n", $default) : (string) $default);
        })->values()->all(), columns: 2);
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
