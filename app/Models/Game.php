<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Game extends Model
{
    use HasTranslations;

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

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort');
    }
}
