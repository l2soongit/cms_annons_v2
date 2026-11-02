<?php

namespace App\Filament\Pages;

use App\Billing\CurrencySwitch;
use App\Billing\Money;
use App\Models\ModerationLog;
use App\Support\Locales;
use App\Support\PageCache;
use App\Support\Settings as SettingsStore;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Throwable;
use UnitEnum;

/**
 * Відкриті питання ТЗ (інтервал голосування, формула рейтингу) вирішуються тут без зміни коду.
 *
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static ?string $navigationLabel = 'Налаштування';

    protected static ?string $title = 'Налаштування порталу';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(): void
    {
        $values = [];
        foreach (SettingsStore::PORTAL as $key) {
            Arr::set($values, $key, config("graj.{$key}"));
        }

        $values['default_locale'] = Locales::default();
        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Голосування та захист від накрутки')->columns(2)->schema([
                TextInput::make('voting.interval_hours')->label('Інтервал між голосами, год')->numeric()->minValue(1)->maxValue(48)->required(),
                TextInput::make('voting.subnet_limit')->label('Голосів з однієї підмережі /24 за інтервал')->numeric()->minValue(1)->required()
                    ->helperText('Понад ліміт голоси позначаються підозрілими і не зараховуються до перевірки.'),
            ]),
            Section::make('Формула рейтингу')
                ->description('Бали = голоси місяця × w1 + бонусні бали × w2 + онлайн × w3 + аптайм% × w4. Перерахунок кожні 5 хвилин.')
                ->columns(4)->schema([
                    TextInput::make('rating.weights.votes')->label('w1: голоси')->numeric()->required(),
                    TextInput::make('rating.weights.bonus')->label('w2: бонуси')->numeric()->required(),
                    TextInput::make('rating.weights.online')->label('w3: онлайн')->numeric()->required(),
                    TextInput::make('rating.weights.uptime')->label('w4: аптайм')->numeric()->required(),
                ]),
            Section::make('Моніторинг')->columns(2)->schema([
                TextInput::make('monitoring.interval_minutes')->label('Інтервал опитування, хв')->numeric()->minValue(1)->maxValue(60)->required(),
                TextInput::make('monitoring.archive_after_offline_days')->label('Архівувати після офлайну, днів')->numeric()->minValue(1)->required(),
            ]),
            Section::make('Мови сайту')
                ->description('Основна мова — без префікса в адресі; решта відкриваються за /<код>/… (напр. /en/lineage2). Тексти нової мови перекладаються в розділі «Переклади»; поки перекладу немає, показується англійський.')
                ->schema([
                    Select::make('default_locale')->label('Головна мова сайту')
                        ->options(fn (Get $get) => collect((array) $get('locales'))
                            ->mapWithKeys(fn ($row, $code) => is_array($row) ? [trim((string) ($row['key'] ?? '')) => trim((string) ($row['value'] ?? ''))] : [$code => $row])
                            ->filter(fn ($name, $code) => preg_match('/^[a-z]{2}$/', (string) $code))
                            ->map(fn ($name, $code) => ($name ?: strtoupper($code)).' ('.$code.')')->all())
                        ->required()->native(false)->live()
                        ->helperText('Нею сайт відкривається без префікса в адресі (graj.club/…); інші мови — graj.club/<код>/…. Після зміни сторінки колишньої головної мови отримають префікс.'),
                    KeyValue::make('locales')->label('Мови')->keyLabel('Код (2 літери)')->valueLabel('Назва в перемикачі')
                        ->addActionLabel('Додати мову')
                        ->rules([fn () => function (string $attribute, $value, Closure $fail) {
                            // Валідатор бачить сирий стан поля — рядки [{key, value}], а не «код => назва»
                            $value = collect((array) $value)->mapWithKeys(fn ($row, $code) => is_array($row) ? [trim((string) ($row['key'] ?? '')) => $row['value'] ?? ''] : [$code => $row])->all();
                            foreach (array_keys($value) as $code) {
                                if (! preg_match('/^[a-z]{2}$/', (string) $code)) {
                                    $fail("Код мови «{$code}» має складатися з двох латинських літер (uk, en, pl).");
                                }
                            }
                            $main = $this->data['default_locale'] ?? Locales::default();
                            if (! array_key_exists($main, (array) $value)) {
                                $fail("Головну мову ({$main}) прибрати не можна — спершу виберіть іншу головну.");
                            }
                        }]),
                ]),
            Section::make('Гроші')
                ->description('Основна валюта — у ній баланс власників, ціни тарифів і банерів, історія операцій і звіти. Поповнити баланс можна й в інших валютах: сума перераховується за курсом НБУ.')
                ->columns(3)->schema([
                    Select::make('billing.currency')->label('Валюта сайту')->options(CurrencySwitch::CURRENCIES)->required()->native(false)
                        ->helperText('При зміні всі суми (баланси, ціни, межі поповнення, історія) перераховуються за курсом НБУ — ні в кого не стане більше чи менше грошей. Ціни потім можна округлити в «Тарифах».'),
                    TextInput::make('billing.min_deposit')->label('Мінімальне поповнення')->numeric()->minValue(1)->required()
                        ->suffix(fn () => Money::symbol()),
                    TextInput::make('billing.max_deposit')->label('Максимальне поповнення')->numeric()->minValue(1)->required()
                        ->suffix(fn () => Money::symbol()),
                ]),
            Section::make('Швидкість сайту')
                ->description('Головна, сторінки ігор, серверів і статичні сторінки для гостей віддаються готовими, без запитів до бази. Залогінені користувачі завжди бачать сторінку наживо. Кеш скидається сам після змін в адмінці чи кабінеті.')
                ->columns(2)->schema([
                    Toggle::make('page_cache.enabled')->label('Кешувати сторінки для гостей')->inline(false),
                    TextInput::make('page_cache.ttl')->label('Скільки тримати сторінку, секунд')->numeric()->minValue(10)->maxValue(3600)->required()
                        ->helperText('Онлайн і голоси на закешованій сторінці оновлюються не рідше. 60–300 — оптимально.'),
                ]),
            Section::make('Відгуки')->schema([
                TagsInput::make('reviews.stop_words')->label('Стоп-слова')
                    ->helperText('Відгуки з цими словами йдуть на премодерацію. Регістр не важливий.'),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('flushPageCache')->label('Очистити кеш сторінок')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->action(function () {
                    PageCache::flush();
                    Notification::make()->title('Кеш сторінок очищено')->body('Наступні відвідувачі побачать свіжі сторінки.')->success()->send();
                }),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $values = [];
        foreach (SettingsStore::PORTAL as $key) {
            $value = Arr::get($state, $key);
            $values[$key] = match (true) {
                $key === 'reviews.stop_words' => array_values(array_filter(array_map(fn ($w) => mb_strtolower(trim($w)), (array) $value))),
                $key === 'default_locale' => (string) $value,
                $key === 'page_cache.enabled' => (bool) $value,
                $key === 'billing.currency' => (string) $value,
                $key === 'locales' => collect((array) $value)->mapWithKeys(fn ($name, $code) => [strtolower(trim((string) $code)) => trim((string) $name)])->all(),
                (float) $value == (int) $value => (int) $value,
                default => (float) $value,
            };
        }

        // Нова валюта: спершу перерахунок усіх сум за курсом НБУ (межі поповнення перераховуються там само)
        $from = Money::base();
        if ($values['billing.currency'] !== $from) {
            try {
                $rate = app(CurrencySwitch::class)->run($values['billing.currency'], auth()->id());
            } catch (Throwable $e) {
                Notification::make()->title('Валюту не змінено')->body($e->getMessage())->danger()->send();

                return;
            }
            // Курс показуємо «1 дорожча = N дешевшої»: 1 $ = 41,2 ₴, а не 1 ₴ = 0,024 $
            $line = $rate >= 1 ? "1 {$from} = ".round($rate, 4).' '.Money::base() : '1 '.Money::base().' = '.round(1 / $rate, 4)." {$from}";
            Notification::make()->title('Валюту змінено на '.Money::base())
                ->body("Усі суми перераховано за курсом НБУ: {$line}.")->success()->send();
            unset($values['billing.currency'], $values['billing.min_deposit'], $values['billing.max_deposit']);
            $this->data['billing'] = ['currency' => Money::base(), 'min_deposit' => config('graj.billing.min_deposit'), 'max_deposit' => config('graj.billing.max_deposit')];
        }

        SettingsStore::save($values);

        ModerationLog::create([
            'moderator_id' => auth()->id(),
            'subject_type' => 'settings',
            'subject_id' => 0,
            'action' => 'settings_update',
            'changes' => $values,
        ]);

        Notification::make()->title('Налаштування збережено')->success()->send();
    }
}
