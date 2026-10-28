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
        'game_side' => ['Бічна колонка сторінки гри', 300, 250],
        'sidebar' => ['Сайдбар сторінок серверів', 300, 250],
    ];

    /** Місця, що продаються окремо для кожної гри */
    public const PER_GAME = ['game', 'game_side'];

    /** Стилі сторінки гри, де є бічна колонка (місце game_side) */
    public const SIDE_COLUMN_THEMES = ['l2'];

    /**
     * Місця, що не показуються одночасно для того самого сервера (правило «один сервер — один банер на сторінці»):
     * шапка є на кожній сторінці, тож конфліктує з усіма; банер гри й бічна колонка — на одній сторінці гри.
     * Такі покупки на дати, що перетинаються, заборонені — інакше оплачений банер просто не показався б.
     */
    public static function clashesWith(string $placement): array
    {
        return match ($placement) {
            'header' => array_values(array_diff(array_keys(self::PLACEMENTS), ['header'])),
            'game' => ['header', 'game_side'],
            'game_side' => ['header', 'game'],
            default => ['header'],
        };
    }

    public static function isPerGame(string $placement): bool
    {
        return in_array($placement, self::PER_GAME, true);
    }

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
            ->where(fn (Builder $q) => self::isPerGame($placement) ? $q->where('game_id', $gameId) : $q->whereNull('game_id'))
            ->where(fn (Builder $q) => $q->whereNull('order_id')->orWhereHas('order', fn ($o) => $o->whereIn('status', ['active', 'expired'])));
    }

    public function ctr(): float
    {
        return $this->impressions ? round($this->clicks / $this->impressions * 100, 2) : 0.0;
    }
}
