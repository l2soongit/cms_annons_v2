<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    protected $guarded = [];

    public array $translatable = ['name', 'description', 'seo_title', 'seo_description'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort');
    }
}
