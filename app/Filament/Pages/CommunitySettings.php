<?php

namespace App\Filament\Pages;

use App\Models\ModerationLog;
use App\Support\Settings as SettingsStore;
use BackedEnum;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use UnitEnum;

/**
 * Налаштування спільноти: модерація відповідей, автофільтр, ліміти, бонусна програма й рівні учасників.
 * Теми перевіряються завжди — це не вимикається.
 *
 * @property-read Schema $form
 */
class CommunitySettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Спільнота';

    protected static ?string $navigationLabel = 'Налаштування';

    protected static ?string $title = 'Налаштування спільноти';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    /** Ключі, що зберігаються як списки рядків */
    private const LISTS = ['community.stop_words', 'community.banned_domains'];

    private const BOOLS = ['community.enabled', 'community.edit_requires_review', 'community.flag_links_for_newbies', 'community.bonus.enabled'];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('community.manage') ?? false;
    }

    public function mount(): void
    {
        $values = [];
        foreach (SettingsStore::COMMUNITY as $key) {
            Arr::set($values, $key, config("graj.{$key}"));
        }
        // Рівні у формі: назва => поріг (KeyValue не любить числові ключі)
        $values['community']['levels'] = collect(config('graj.community.levels', []))->mapWithKeys(fn ($name, $min) => [$name => (string) $min])->all();
        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Модерація')->description('Кожна нова тема й новина проходить перевірку модератором — це правило не вимикається.')->columns(2)->schema([
                Toggle::make('community.enabled')->label('Розділ «Спільнота» на сайті')->columnSpanFull(),
                Radio::make('community.moderate_replies')->label('Відповіді в темах')->required()->options([
                    'newbies' => 'Перевіряти відповіді новачків (рекомендовано)',
                    'all' => 'Перевіряти всі відповіді',
                    'none' => 'Публікувати одразу (автофільтр однаково працює)',
                ]),
                Grid::make(1)->schema([
                    TextInput::make('community.newbie_posts')->label('Новачок — поки схвалених дописів менше ніж')->numeric()->minValue(0)->maxValue(100)->required(),
                    Toggle::make('community.edit_requires_review')->label('Редагування схваленої теми — знову на перевірку'),
                    Toggle::make('community.flag_links_for_newbies')->label('Посилання від новачків — на перевірку'),
                ]),
                TagsInput::make('community.stop_words')->label('Стоп-слова')->helperText('Допис зі стоп-словом іде на перевірку з позначкою.'),
                TagsInput::make('community.banned_domains')->label('Заборонені посилання')->helperText('Інші топи, запрошення Discord тощо.'),
                TextInput::make('community.topics_per_day')->label('Тем на день від одного користувача')->numeric()->minValue(1)->required(),
                TextInput::make('community.posts_per_hour')->label('Відповідей на годину від одного користувача')->numeric()->minValue(1)->required(),
            ]),
            Section::make('Бонусна програма')->description('Бали за схвалені дописи, найкращі відповіді, голоси й активність. Витрачаються в магазині балів і на оплату послуг.')->columns(4)->schema([
                Toggle::make('community.bonus.enabled')->label('Нараховувати бали')->columnSpanFull(),
                TextInput::make('community.bonus.topic')->label('Схвалена тема')->numeric()->minValue(0),
                TextInput::make('community.bonus.server_news')->label('Схвалена новина сервера')->numeric()->minValue(0),
                TextInput::make('community.bonus.best_answer')->label('Найкраща відповідь')->numeric()->minValue(0),
                TextInput::make('community.bonus.like')->label('За кожен ▲')->numeric()->minValue(0),
                TextInput::make('community.bonus.daily_vote')->label('Голос за сервер')->numeric()->minValue(0),
                TextInput::make('community.bonus.streak_7')->label('7 днів активності поспіль')->numeric()->minValue(0),
                TextInput::make('community.bonus.owner_reply')->label('Відповідь власника в темі сервера')->numeric()->minValue(0),
                TextInput::make('community.bonus.daily_cap')->label('Максимум автобалів на день')->numeric()->minValue(1),
                TextInput::make('community.bonus.like_min_account_days')->label('▲ рахуються від акаунтів, старших за днів')->numeric()->minValue(0),
                TextInput::make('community.bonus.point_value')->label('Вартість 1 бала в оплаті')->numeric()->minValue(0)->step(0.001),
                TextInput::make('community.bonus.max_pay_share')->label('Частка замовлення балами, %')->numeric()->minValue(0)->maxValue(100),
            ]),
            Section::make('Рівні учасників')->description('Рівень рахується з усіх зароблених балів. Ключ — код рівня (newbie, member, veteran, legend), значення — поріг балів.')->schema([
                KeyValue::make('community.levels')->label('Рівні')->keyLabel('Код рівня')->valueLabel('Поріг балів')->required(),
            ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $values = [];
        foreach (SettingsStore::COMMUNITY as $key) {
            $value = Arr::get($state, $key);
            $values[$key] = match (true) {
                in_array($key, self::LISTS, true) => array_values(array_filter(array_map(fn ($w) => mb_strtolower(trim((string) $w)), (array) $value))),
                in_array($key, self::BOOLS, true) => (bool) $value,
                $key === 'community.moderate_replies' => (string) $value,
                $key === 'community.levels' => collect((array) $value)->mapWithKeys(fn ($min, $name) => [max(0, (int) $min) => trim((string) $name)])->sortKeys()->all(),
                (float) $value == (int) $value => (int) $value,
                default => (float) $value,
            };
        }

        if (! array_key_exists(0, $values['community.levels'])) {
            Notification::make()->title('Потрібен рівень з порогом 0')->body('Наприклад, newbie → 0.')->danger()->send();

            return;
        }

        SettingsStore::save($values);
        ModerationLog::create([
            'moderator_id' => auth()->id(),
            'subject_type' => 'settings',
            'subject_id' => 0,
            'action' => 'community_settings_update',
            'changes' => $values,
        ]);

        Notification::make()->title('Збережено')->success()->send();
    }
}
