<?php

namespace App\Filament\Pages;

use App\Models\ModerationLog;
use App\Support\Locales;
use App\Support\Translations as TranslationStore;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Переклади інтерфейсу й листів (ТЗ 4): тексти з файлів lang/ можна змінити для будь-якої мови сайту,
 * зокрема перекласти нову мову, додану в Налаштуваннях.
 */
class Translations extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static ?string $navigationLabel = 'Переклади';

    protected static ?string $title = 'Переклади інтерфейсу й листів';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.translations';

    /** Більше рядків на сторінці не показуємо — звужуйте пошуком */
    public const LIMIT = 80;

    public string $locale = '';

    public string $group = 'ui';

    public string $search = '';

    /** @var array<string, string> група.ключ => свій текст (порожньо — з файлу) */
    public array $values = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(): void
    {
        $this->locale = Locales::prefixed()[0] ?? Locales::default();
        $this->loadValues();
    }

    public function updatedLocale(): void
    {
        $this->loadValues();
    }

    public function updatedGroup(): void
    {
        $this->loadValues();
    }

    private function loadValues(): void
    {
        abort_unless(Locales::isSupported($this->locale) && in_array($this->group, TranslationStore::groups(), true), 404);
        $overrides = TranslationStore::overrides($this->locale);
        $this->values = collect(TranslationStore::fileLines(Locales::default(), $this->group))
            ->mapWithKeys(fn ($v, $key) => [$this->field($key) => $overrides[$key] ?? ''])
            ->all();
    }

    /** Ключ без крапок для wire:model (крапка в Livewire означає вкладеність) */
    public function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /**
     * Рядки групи: ключ, текст основною мовою, текст з файлу вибраної мови.
     *
     * @return list<array{key: string, field: string, source: string, file: ?string}>
     */
    public function rows(): array
    {
        $source = TranslationStore::fileLines(Locales::default(), $this->group);
        $file = TranslationStore::fileLines($this->locale, $this->group);
        $needle = mb_strtolower(trim($this->search));

        return collect($source)
            ->filter(fn ($text, $key) => $needle === '' || Str::contains(mb_strtolower($key.' '.$text.' '.($file[$key] ?? '').' '.($this->values[$this->field($key)] ?? '')), $needle))
            ->take(self::LIMIT)
            ->map(fn ($text, $key) => ['key' => $key, 'field' => $this->field($key), 'source' => $text, 'file' => $file[$key] ?? null])
            ->values()->all();
    }

    public function saveRow(string $key): void
    {
        abort_unless(array_key_exists($key, TranslationStore::fileLines(Locales::default(), $this->group)), 404);

        TranslationStore::save($this->locale, $key, $this->values[$this->field($key)] ?? '');
        ModerationLog::create([
            'moderator_id' => auth()->id(),
            'subject_type' => 'translation',
            'subject_id' => 0,
            'action' => 'translation_update',
            'changes' => ['locale' => $this->locale, 'key' => $key, 'value' => $this->values[$this->field($key)] ?? ''],
        ]);

        Notification::make()->title("Збережено: {$key}")->success()->send();
    }
}
