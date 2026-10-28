<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Рекламний банер (платна послуга «Банер», ТЗ 3.7): місце, заброньовані дати, лічильники показів і кліків.
 */
class Banner extends Model
{
    protected $guarded = [];

    /** Рекламні місця: ключ => [назва, ширина, висота] */
    public const PLACEMENTS = [
        'header' => ['Шапка всіх сторінок', 1200, 150],
        'game' => ['Сторінка гри, під банером гри', 1200, 150],
        'sidebar' => ['Сайдбар картки сервера', 300, 250],
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** Банер, що зараз має показуватися на місці: увімкнений, у межах дат, замовлення не скасоване */
    public function scopeLive(Builder $query, string $placement, ?int $gameId = null): void
    {
        $query->where('placement', $placement)
            ->where('is_active', true)
            ->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->where(fn (Builder $q) => $placement === 'game' ? $q->where('game_id', $gameId) : $q->whereNull('game_id'))
            ->where(fn (Builder $q) => $q->whereNull('order_id')->orWhereHas('order', fn ($o) => $o->whereIn('status', ['active', 'expired'])));
    }

    public function ctr(): float
    {
        return $this->impressions ? round($this->clicks / $this->impressions * 100, 2) : 0.0;
    }
}
