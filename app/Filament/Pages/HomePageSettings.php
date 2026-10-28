<?php

namespace App\Filament\Pages;

use App\Filament\Support\ImageField;
use App\Models\ModerationLog;
use App\Support\HomeContent;
use App\Support\HomePageData;
use App\Support\Locales;
use App\Support\Settings as SettingsStore;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/**
 * Тексти й секції головної сторінки. Дані (топ, онлайн, тарифи) беруться з каталогу автоматично.
 *
 * @property-read Schema $form
 */
class HomePageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static ?string $navigationLabel = 'Головна сторінка';

    protected static ?string $title = 'Головна сторінка';

    protected static ?string $slug = 'home-page';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.settings';

    private const IMAGES = ['hero_image', 'cta_image', 'logo_image', 'og_image'];

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(): void
    {
        $values = [];
        foreach (SettingsStore::HOME as $key) {
            Arr::set($values, $key, config("graj.{$key}"));
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Перший екран')->columns(2)->schema([
                ...$this->localized('hero_title', 'Заголовок', 'Перед виділеним словом'),
                ...$this->localized('hero_accent', 'Виділене слово', 'Показується градієнтом у кінці заголовка'),
                ...$this->localized('hero_lead', 'Підзаголовок', textarea: true),
                TagsInput::make('home.quick_links')->label('«Часто шукають»')->columnSpanFull()
                    ->helperText('Кожен пункт — посилання на пошук за цим текстом.'),
                TagsInput::make('home.search_hints')->label('Підказки в полі пошуку')->columnSpanFull()
                    ->helperText('Друкуються по черзі, поки поле порожнє.'),
            ]),
            Section::make('Зображення')
                ->description('Необов’язково, до 10 МБ — після завантаження стискаються у WebP. Без зображення лишається анімований фон. Обкладинки й іконки ігор — у розділі «Ігри», логотипи й банери серверів завантажують власники в кабінеті.')
                ->columns(2)->schema([
                    $this->image('home.hero_image', 'Фон першого екрана', 'hero')->helperText('Широке зображення, від 1920 px. Повільно наближається, згасає донизу.'),
                    $this->image('home.cta_image', 'Фон блоку «Заклик унизу»', 'cover')->helperText('Від 1200 px завширшки.'),
                    $this->image('home.logo_image', 'Логотип сайту', 'site_logo')->helperText('Замінює значок і назву в шапці та підвалі. PNG або WebP із прозорим фоном, висота від 64 px.'),
                    $this->image('home.og_image', 'Картинка для соцмереж', 'og')->imageEditorAspectRatios(['1.91:1'])->helperText('Прев’ю посилання в Telegram, Facebook, Discord. 1200×630 px.'),
                    TextInput::make('home.hero_image_opacity')->label('Яскравість фону першого екрана, %')
                        ->numeric()->minValue(5)->maxValue(100)->required()
                        ->helperText('Менше — текст читається краще.'),
                ]),
            Section::make('Секції')->schema([
                CheckboxList::make('home.sections')->label('Показувати на головній')->options(HomeContent::SECTIONS)->columns(2),
                TextInput::make('home.ranking_size')->label('Рядків у таблиці лідерів')->numeric()->minValue(3)->maxValue(30)->required(),
                Toggle::make('home.live_feed')->label('Сповіщення «щойно проголосували»')
                    ->helperText('Справжні голоси за останні хвилини, внизу екрана.'),
            ]),
            Section::make('Власникам серверів')->columns(2)->schema([
                ...$this->localized('owners_title', 'Заголовок'),
                ...$this->localized('owners_lead', 'Текст', textarea: true),
            ]),
            Section::make('Заклик унизу')->columns(2)->schema([
                ...$this->localized('cta_title', 'Заголовок'),
                ...$this->localized('cta_text', 'Текст', textarea: true),
            ]),
        ]);
    }

    private function image(string $key, string $label, string $type): FileUpload
    {
        return ImageField::make($key, 'home', $type)->label($label);
    }

    /** Поле для кожної мови сайту; обов’язкове лише основною */
    private function localized(string $key, string $label, ?string $help = null, bool $textarea = false): array
    {
        $make = fn (string $locale) => ($textarea ? Textarea::make("home.{$key}.{$locale}")->rows(2) : TextInput::make("home.{$key}.{$locale}"))
            ->label("{$label} ({$locale})")
            ->maxLength(300)
            ->helperText($locale === Locales::default() ? $help : null);

        return array_map(fn (string $locale) => $locale === Locales::default() ? $make($locale)->required() : $make($locale), array_keys(Locales::all()));
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $values = [];
        foreach (SettingsStore::HOME as $key) {
            $value = Arr::get($state, $key);
            $name = substr($key, strlen('home.'));
            $values[$key] = match (true) {
                in_array($name, HomeContent::LOCALIZED, true) => collect(array_keys(Locales::all()))->mapWithKeys(fn ($l) => [$l => trim((string) ($value[$l] ?? ''))])->all(),
                in_array($name, ['quick_links', 'search_hints'], true) => array_values(array_filter(array_map('trim', (array) $value))),
                $name === 'sections' => array_values(array_intersect(array_keys(HomeContent::SECTIONS), (array) $value)),
                in_array($name, ['ranking_size', 'hero_image_opacity'], true) => (int) $value,
                in_array($name, self::IMAGES, true) => is_string($value) && $value !== '' ? $value : null,
                default => (bool) $value,
            };
        }

        // Замінені або прибрані зображення більше ніде не використовуються
        foreach (self::IMAGES as $name) {
            $old = config("graj.home.{$name}");
            if ($old && $old !== $values["home.{$name}"]) {
                Storage::disk('public')->delete($old);
            }
        }

        SettingsStore::save($values);
        HomePageData::forget();

        ModerationLog::create([
            'moderator_id' => auth()->id(),
            'subject_type' => 'settings',
            'subject_id' => 0,
            'action' => 'home_settings_update',
            'changes' => $values,
        ]);

        Notification::make()->title('Головну сторінку збережено')->success()->send();
    }
}
