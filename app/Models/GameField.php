<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class GameField extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['label'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
            'in_url' => 'boolean',
        ];
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
