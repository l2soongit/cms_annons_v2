<?php

namespace App\Filament\Pages;

use App\Models\ModerationLog;
use App\Support\Locales;
use App\Support\Settings as SettingsStore;
use BackedEnum;
use Closure;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
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
                            if (! array_key_exists(Locales::default(), (array) $value)) {
                                $fail('Основну мову ('.Locales::default().') прибрати не можна.');
                            }
                        }]),
                ]),
            Section::make('Відгуки')->schema([
                TagsInput::make('reviews.stop_words')->label('Стоп-слова')
                    ->helperText('Відгуки з цими словами йдуть на премодерацію. Регістр не важливий.'),
            ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $values = [];
        foreach (SettingsStore::PORTAL as $key) {
            $value = Arr::get($state, $key);
            $values[$key] = match (true) {
                $key === 'reviews.stop_words' => array_values(array_filter(array_map(fn ($w) => mb_strtolower(trim($w)), (array) $value))),
                $key === 'locales' => collect((array) $value)->mapWithKeys(fn ($name, $code) => [strtolower(trim((string) $code)) => trim((string) $name)])->all(),
                (float) $value == (int) $value => (int) $value,
                default => (float) $value,
            };
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
