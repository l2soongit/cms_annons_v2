<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class Tariff extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['name'];

    /** Платні послуги з ТЗ (3.7): ключ => назва в адмінці */
    public const SERVICES = [
        'vip' => 'VIP-блок над рейтингом',
        'highlight' => 'Виділення в списку',
        'boost' => 'Підняття (бонусні бали)',
        'coming_soon' => '«Скоро відкриття» на головній',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
