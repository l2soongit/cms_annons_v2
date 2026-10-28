<?php

namespace App\Models;

use App\Support\HomePageData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;

class Game extends Model
{
    use HasTranslations;

    /** Стилі оформлення гри: ключ => назва в адмінці. Ключ — CSS-клас .g-<ключ> і сцена scene-<ключ> */
    public const THEMES = [
        'l2' => 'Фентезі (Lineage 2)',
        'gta' => 'Неон (GTA)',
        'mc' => 'Пікселі (Minecraft)',
        'dayz' => 'Туманний ліс (DayZ)',
        'rust' => 'Іржа й вогонь (Rust)',
        'cs' => 'Тактичний радар (CS)',
    ];

    /** Блоки сторінки гри, які адмін може вимкнути: ключ => назва в адмінці */
    public const BLOCKS = [
        'soon' => '«Скоро відкриття»',
        'fame' => 'Зал слави',
        'popular' => 'Популярність (хроніки, версії, карти)',
        'season' => 'Таймер фіналу сезону',
        'live' => 'Живі сповіщення про голоси',
    ];

    /**
     * Тексти стилю, які можна перевизначити в адмінці: стиль => [ключ перекладу ui.* => назва поля].
     * Порожнє поле — текст за замовчуванням з перекладів; :game — коротка назва гри.
     */
    public const THEME_TEXTS = [
        'l2' => [
            'home.season_left' => 'Підпис таймера сезону',
            'game.add_own' => 'Кнопка в банері (:game — назва гри)',
            'home.coming_soon' => 'Заголовок «Скоро відкриття»',
            'game.fame' => 'Заголовок залу слави',
            'game.empty' => 'Порожній список',
            'game.empty_hint' => 'Підказка до порожнього списку',
        ],
        'gta' => [
            'home.season_left' => 'Підпис таймера сезону',
            'game.add_own' => 'Кнопка в банері (:game — назва гри)',
            'game.soon_accent' => 'Виділене слово заголовка «Скоро відкриття»',
            'game.soon_title_gta' => 'Заголовок «Скоро відкриття» (:accent — виділене слово)',
            'game.fame' => 'Заголовок залу слави',
            'game.empty' => 'Порожній список',
            'game.empty_hint' => 'Підказка до порожнього списку',
        ],
        'cs' => [
            'cs.season' => 'Підпис таймера сезону',
            'cs.radar' => 'Підпис під радаром',
            'cs.buy' => 'Заголовок меню закупівлі',
            'cs.buy_hint' => 'Підказка під меню закупівлі',
            'cs.board' => 'Заголовок таблиці серверів',
            'cs.live' => 'Позначка «онлайн оновлюється»',
            'cs.soon' => 'Заголовок «Скоро старт»',
            'cs.mvp' => 'Заголовок залу слави (MVP)',
            'cs.popular_maps' => 'Заголовок популярних карт',
            'cs.empty' => 'Порожній список',
            'cs.empty_hint' => 'Підказка до порожнього списку',
        ],
        'mc' => [
            'mc.splash' => 'Жовті написи в банері (по одному на рядок)',
            'mc.season' => 'Підпис таймера сезону',
            'mc.soon' => 'Заголовок «Скоро відкриття»',
            'mc.soon_title' => 'Напис на картці анонсу',
            'mc.fame' => 'Заголовок залу слави',
            'mc.popular' => 'Заголовок популярних версій',
            'mc.empty' => 'Порожній список',
            'mc.empty_hint' => 'Підказка до порожнього списку',
        ],
        'dayz' => [
            'dayz.weather' => 'Погода в куті банера (по одному рядку, змінюються по колу)',
            'dayz.season' => 'Підпис таймера сезону',
            'dayz.soon' => 'Заголовок «Скоро відкриття»',
            'dayz.filters' => 'Заголовок фільтрів',
            'dayz.free' => 'Фільтр «вільні місця»',
            'dayz.fame' => 'Заголовок залу слави',
            'dayz.shares' => 'Заголовок «Де зараз вцілілі»',
            'dayz.empty' => 'Порожній список',
            'dayz.empty_hint' => 'Підказка до порожнього списку',
        ],
        'rust' => [
            'rust.v_forced' => 'Підпис відліку до форс-вайпу',
            'rust.calendar' => 'Заголовок вайп-календаря',
            'rust.filters' => 'Заголовок фільтрів',
            'rust.fame' => 'Заголовок залу слави',
            'rust.shares' => 'Заголовок «Де зараз грають»',
            'rust.empty' => 'Порожній список',
            'rust.empty_hint' => 'Підказка до порожнього списку',
        ],
    ];

    /** Скільки анонсів «Скоро відкриття» показувати за замовчуванням */
    public const SOON_LIMIT = 3;

    protected $guarded = [];

    public array $translatable = ['name', 'hero_title', 'eyebrow', 'description', 'seo_title', 'seo_description'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'page' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Головна кешує дані на хвилину — зміни гри з адмінки показуємо одразу
        static::saved(fn () => HomePageData::forget());
        static::deleted(fn () => HomePageData::forget());

        // Замінена або прибрана обкладинка/іконка більше ніде не використовується
        static::updated(function (Game $game) {
            foreach (['cover', 'icon'] as $field) {
                $old = $game->getOriginal($field);
                if ($old && $game->wasChanged($field)) {
                    Storage::disk('public')->delete($old);
                }
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<GameField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(GameField::class)->orderBy('sort');
    }

    /** @return HasMany<Server, $this> */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    /** @return HasMany<Tariff, $this> */
    public function tariffs(): HasMany
    {
        return $this->hasMany(Tariff::class);
    }

    /** Ключ стилю для CSS; нова гра без стилю отримує нейтральний */
    public function themeKey(): string
    {
        return array_key_exists((string) $this->theme, self::THEMES) ? $this->theme : 'default';
    }

    /** Коротка назва для вкладок і позначок: «Grand Theft Auto» → GTA, «Lineage 2» → L2, «Rust» → Rust */
    public function shortName(): string
    {
        $words = preg_split('/[\s-]+/u', trim($this->name), -1, PREG_SPLIT_NO_EMPTY);

        if (count($words) < 2) {
            return $this->name;
        }

        return implode('', array_map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)), $words));
    }

    /** Назва в меню ігор: коротка назва лишається, довга скорочується («Grand Theft Auto» → GTA) */
    public function navName(): string
    {
        return mb_strlen($this->name) > 12 ? $this->shortName() : $this->name;
    }

    /** Чи показувати блок сторінки; нові й не налаштовані ігри показують усі блоки */
    public function showsBlock(string $block): bool
    {
        return (bool) ($this->page['blocks'][$block] ?? true);
    }

    public function soonLimit(): int
    {
        return max(1, min(6, (int) ($this->page['soon_limit'] ?? self::SOON_LIMIT)));
    }

    /** Ключ поля для блоку популярності; порожньо — перший фільтр гри */
    public function popularFieldKey(): ?string
    {
        return $this->page['popular_field'] ?? null;
    }

    /**
     * Текст сторінки: свій з адмінки для поточної мови або переклад ui.<key>.
     * Для списків (написи Minecraft) повертає масив рядків.
     *
     * @return string|array<int, string>
     */
    public function pageText(string $key, array $replace = []): string|array
    {
        $default = __("ui.{$key}", $replace);
        $custom = trim((string) ($this->page['texts'][app()->getLocale()][self::textKey($key)] ?? ''));

        if ($custom === '') {
            return $default;
        }

        if (is_array($default)) {
            return array_values(array_filter(array_map('trim', preg_split('/\R/u', $custom))));
        }

        return strtr($custom, collect($replace)->mapWithKeys(fn ($v, $k) => [":{$k}" => $v])->all());
    }

    /** Ключ у JSON без крапок: у формі адмінки крапка означає вкладеність */
    public static function textKey(string $key): string
    {
        return str_replace('.', '_', $key);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort');
    }
}
