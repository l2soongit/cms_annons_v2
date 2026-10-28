<?php

namespace App\Models;

use App\Support\Locales;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class GameField extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['label'];

    /** Типи полів у конструкторі гри: ключ => назва в адмінці */
    public const TYPES = ['select' => 'Список', 'multiselect' => 'Мультивибір', 'boolean' => 'Так / ні', 'text' => 'Текст', 'number' => 'Число'];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
            'in_url' => 'boolean',
        ];
    }

    /**
     * Варіанти списку. Поле «так / ні» має два сталі варіанти (1 / 0) — тому фільтри,
     * підписи й теми сторінок ігор працюють з ним так само, як зі списком.
     *
     * @return Attribute<array<int, array<string, string>>, array<int, array<string, string>>|null>
     */
    protected function options(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->type === 'boolean'
                ? [['value' => '1', 'label' => __('ui.yes')], ['value' => '0', 'label' => __('ui.no')]]
                : self::localize(is_array($value) ? $value : (json_decode($value ?? '', true) ?: [])),
            set: fn ($value) => $value === null ? null : json_encode(array_values((array) $value), JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * Підпис варіанта мовою сторінки: labels[<мова>] з адмінки, інакше основний підпис.
     * Основною мовою (і в адмінці) варіанти не змінюються, тож форма зберігає основний підпис.
     */
    private static function localize(array $options): array
    {
        $locale = app()->getLocale();
        if ($locale === Locales::default()) {
            return $options;
        }

        return array_map(fn ($o) => is_array($o) && filled($o['labels'][$locale] ?? null) ? ['label' => $o['labels'][$locale]] + $o : $o, $options);
    }

    /**
     * Варіант списку за значенням: value, label і необов’язкові short (коротка назва), note (підпис),
     * color (колір позначки), connect (команда підключення з {address}), driver (протокол).
     *
     * @return array<string, string>|null
     */
    public function option(?string $value): ?array
    {
        return $value === null ? null : collect($this->options)->firstWhere('value', $value);
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
