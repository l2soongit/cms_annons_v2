<?php

namespace App\Models;

use App\Support\Locales;
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
        'vote' => ['Сторінка голосування, під кнопкою', 300, 250],
        'forum_skin' => ['Форум: фон сторінок (брендування)', 1920, 1080],
        'forum_top' => ['Форум: під заголовком головної й списків тем', 1200, 150],
        'forum_game' => ['Форум: розділ гри, під заголовком', 1200, 150],
        'forum_list' => ['Форум: між розділами й у списку тем', 1200, 150],
        'forum_side' => ['Форум: бічна колонка', 300, 250],
        'forum_post' => ['Форум: у темі, після першого повідомлення', 1200, 150],
    ];

    /** Місця форуму, що стоять на одній сторінці зі списком (головна форуму, розділ) */
    public const FORUM_LIST_PAGE = ['forum_top', 'forum_game', 'forum_list', 'forum_side'];

    /** Місця, що продаються окремо для кожної гри */
    public const PER_GAME = ['game', 'game_side', 'forum_game'];

    /** Стилі сторінки гри, де є бічна колонка (місце game_side) */
    public const SIDE_COLUMN_THEMES = ['l2'];

    /**
     * Місця, що не показуються одночасно для того самого сервера (правило «один сервер — один банер на сторінці»):
     * шапка є на кожній сторінці, крім голосування, тож конфліктує з усіма іншими; банер гри й бічна колонка — на одній сторінці гри.
     * Такі покупки на дати, що перетинаються, заборонені — інакше оплачений банер просто не показався б.
     */
    public static function clashesWith(string $placement): array
    {
        return match ($placement) {
            'header' => array_values(array_diff(array_keys(self::PLACEMENTS), ['header', 'vote'])),
            'game' => ['header', 'game_side'],
            'game_side' => ['header', 'game'],
            'vote' => [],
            // Фон форуму — на всіх його сторінках; місця списку — разом на головній форуму й у розділах; у темі — лише фон
            'forum_skin' => ['header', 'forum_post', ...self::FORUM_LIST_PAGE],
            'forum_top', 'forum_game', 'forum_list', 'forum_side' => ['header', 'forum_skin', ...array_values(array_diff(self::FORUM_LIST_PAGE, [$placement]))],
            'forum_post' => ['header', 'forum_skin'],
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

    /**
     * Знімок сторінки з позначеним місцем («Де буде банер»): мовою сторінки (images/ad-places/<мова>/), інакше основною;
     * для банера гри — знімок сторінки саме цієї гри, якщо він є.
     *
     * @return array{url: string, width: int, height: int}
     */
    public static function shot(string $placement, ?Game $game = null): array
    {
        $names = $placement === 'game' && $game ? ["game-{$game->slug}", 'game'] : [$placement];
        $locale = app()->getLocale();
        $dirs = $locale === Locales::default() ? [''] : ["{$locale}/", ''];

        foreach ($dirs as $dir) {
            foreach ($names as $name) {
                $path = "images/ad-places/{$dir}{$name}.webp";
                if (is_file(public_path($path))) {
                    [$w, $h] = @getimagesize(public_path($path)) ?: [1000, 540];

                    return ['url' => asset($path), 'width' => (int) $w, 'height' => (int) $h];
                }
            }
        }

        return ['url' => asset("images/ad-places/{$placement}.webp"), 'width' => 1000, 'height' => 540];
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
