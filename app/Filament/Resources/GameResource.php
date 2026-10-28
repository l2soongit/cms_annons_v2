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
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
        return $schema->components([
            Section::make('Гра')->columns(2)->schema([
                ...Localized::fields('name', 'Назва', fn (string $path) => TextInput::make($path), required: true),
                TextInput::make('slug')->required()->unique(ignoreRecord: true)->alphaDash()
                    ->helperText('Частина URL: graj.club/<slug>'),
                Select::make('query_driver')->label('Протокол моніторингу')->options(DriverRegistry::options())->required(),
                TextInput::make('default_port')->label('Порт за замовчуванням')->numeric(),
                Select::make('theme')->label('Стиль оформлення')->options(Game::THEMES)->placeholder('Нейтральний')->live()
                    ->helperText('Колір, шрифт і анімована сцена гри на головній і в списках. Для Lineage 2, GTA, CS і Minecraft — власний дизайн сторінки гри.'),
                ImageField::make('cover', 'games', 'cover')->label('Обкладинка')->columnSpanFull()
                    ->imageEditorAspectRatios(['16:9', '4:3'])
                    ->helperText('Картка гри на головній і в топі місяця, а також фон банера на сторінці гри. До 10 МБ, стискається у WebP. Без обкладинки показується анімована сцена стилю.'),
                ImageField::make('icon', 'games/icons', 'icon')->label('Іконка')
                    ->imageEditorAspectRatios(['1:1'])
                    ->helperText('Квадратна, бажано з прозорим фоном (PNG/WebP). Показується на картці гри на головній.'),
                TextInput::make('vip_slots')->label('VIP-місць')->numeric()->default(5),
                TextInput::make('sort')->label('Порядок')->numeric()->default(0),
                Toggle::make('is_active')->label('Активна')->default(true),
            ]),
            Section::make('Сторінка гри')->description('Банер угорі сторінки гри. Обкладинка гри стає його фоном.')
                ->columns(2)->schema([
                    ...Localized::fields('hero_title', 'Заголовок банера', fn (string $path, string $locale) => TextInput::make($path)->maxLength(60)
                        ->helperText($locale !== Locales::default() ? null : fn (Get $get) => match ($get('theme')) {
                            'l2', 'gta' => 'Напр. «Lineage II». Останнє слово виділяється кольором стилю. Порожньо — назва гри.',
                            'cs' => 'Напр. «Counter-Strike». Частина після дефіса або останнє слово виділяється. Порожньо — назва гри.',
                            'mc' => 'Пишеться великими піксельними літерами; одне довге слово ділиться навпіл («MINE» «CRAFT»). Порожньо — назва гри.',
                            default => 'Порожньо — назва гри.',
                        })),
                    ...Localized::fields('eyebrow', 'Підпис над заголовком', fn (string $path, string $locale) => TextInput::make($path)->maxLength(80)
                        ->helperText($locale === Locales::default() ? 'Напр. «Фентезі MMORPG · з 2003 року».' : null)),
                    ...Localized::fields('description', 'Опис під заголовком', fn (string $path) => Textarea::make($path)->rows(2)->maxLength(240)),
                ]),
            Section::make('Блоки сторінки гри')->description('Що показувати на сторінці гри з власним дизайном (Lineage 2, GTA, CS, Minecraft).')
                ->columns(2)->collapsible()->schema([
                    Fieldset::make('Показувати')->columns(3)->columnSpanFull()->schema(collect(Game::BLOCKS)->map(fn ($label, $key) => Toggle::make("page.blocks.{$key}")->label($label)->default(true))->values()->all()),
                    TextInput::make('page.soon_limit')->label('Анонсів «Скоро відкриття»')->numeric()->minValue(1)->maxValue(6)
                        ->placeholder((string) Game::SOON_LIMIT)->helperText('Від 1 до 6 найближчих відкриттів.'),
                    Select::make('page.popular_field')->label('Блок популярності рахує за полем')->placeholder('Перше поле-фільтр')
                        ->options(fn (?Game $record) => $record?->fields->where('is_filterable', true)->mapWithKeys(fn ($f) => [$f->key => $f->label])->all() ?? [])
                        ->helperText('Напр. хроніки для Lineage 2, версії для Minecraft. У CS блок показує популярні карти з моніторингу.'),
                ]),
            Section::make('Тексти стилю')->description('Підписи й заголовки, характерні для дизайну гри. Порожнє поле — текст за замовчуванням (показаний сірим).')
                ->columns(2)->collapsible()->collapsed()
                ->visible(fn (Get $get) => isset(Game::THEME_TEXTS[$get('theme')]))
                ->schema(fn (Get $get) => self::themeTextFields((string) $get('theme'))),
            Section::make('SEO')->columns(2)->collapsed()->schema([
                ...Localized::fields('seo_title', 'Title', fn (string $path) => TextInput::make($path)),
                ...Localized::fields('seo_description', 'Description', fn (string $path) => Textarea::make($path)),
            ]),
            Section::make('Поля сервера')->description('Що власник заповнює для цієї гри і за чим гравці фільтрують рейтинг.')->schema([
                Repeater::make('fields')->relationship()->hiddenLabel()
                    ->orderColumn('sort')->collapsible()
                    ->itemLabel(fn (array $state) => ($state['label']['uk'] ?? null) ?: ($state['key'] ?? null))
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('key')->label('Ключ')->required()->alphaDash(),
                            ...Localized::fields('label', 'Назва', fn (string $path) => TextInput::make($path), required: true),
                            Select::make('type')->label('Тип')->required()->options(GameField::TYPES)->live()
                                ->helperText(fn (Get $get) => $get('type') === 'boolean' ? 'Галочка у формі сервера; у фільтрі — «Так» / «Ні».' : null),
                        ]),
                        Grid::make(3)->schema([
                            Toggle::make('is_required')->label('Обов’язкове'),
                            Toggle::make('is_filterable')->label('Фільтр у рейтингу'),
                            Toggle::make('in_url')->label('У URL'),
                        ]),
                        Repeater::make('options')->label('Варіанти')->columns(4)->defaultItems(0)
                            ->visible(fn (Get $get) => in_array($get('type'), ['select', 'multiselect'], true))
                            ->itemLabel(fn (array $state) => $state['label'] ?? null)->collapsible()
                            ->schema([
                                TextInput::make('value')->label('Значення')->required()->helperText('У посиланні фільтра: ?поле=значення'),
                                TextInput::make('label')->label('Підпис')->required(),
                                TextInput::make('short')->label('Коротка назва')->maxLength(16)
                                    ->helperText('Для вузьких перемикачів: «1.6» замість «CS 1.6», «Java+Bed».'),
                                TextInput::make('note')->label('Підпис під варіантом')->maxLength(24)
                                    ->helperText('Напр. рушій у перемикачі версій CS: «Source 2».'),
                                ColorPicker::make('color')->label('Колір позначки')
                                    ->helperText('Платформи GTA, версії CS, видання Minecraft. Порожньо — колір стилю.'),
                                TextInput::make('connect')->label('Команда підключення')->maxLength(80)->placeholder('connect {address}')
                                    ->helperText('Що копіює кнопка «Підключитися»; {address} — адреса сервера. Порожньо — лише адреса.'),
                                Select::make('driver')->label('Протокол (необов’язково)')->options(DriverRegistry::options()),
                                KeyValue::make('labels')->label('Переклади підпису')->keyLabel('Мова')->valueLabel('Підпис')->columnSpanFull()
                                    ->addActionLabel('Додати переклад')
                                    ->helperText(fn () => 'Напр. en → Hardcore. Мови сайту: '.implode(', ', array_keys(Locales::all())).'. Без перекладу показується основний підпис.'),
                            ]),
                    ]),
            ]),
        ]);
    }

    /** Поля текстів стилю для кожної мови сайту; сірим — текст за замовчуванням */
    private static function themeTextFields(string $theme): array
    {
        return collect(Game::THEME_TEXTS[$theme] ?? [])->flatMap(function (string $label, string $key) {
            $path = Game::textKey($key);

            return collect(array_keys(Locales::all()))->map(function (string $locale) use ($key, $path, $label) {
                $default = trans("ui.{$key}", [], $locale);

                return (is_array($default) ? Textarea::make("page.texts.{$locale}.{$path}")->rows(4) : TextInput::make("page.texts.{$locale}.{$path}")->maxLength(160))
                    ->label("{$label} ({$locale})")
                    ->placeholder(is_array($default) ? implode("\n", $default) : (string) $default);
            });
        })->all();
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
