<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * Розділ форуму. Розділ гри прив'язаний до game_id; post_roles обмежує, хто може створювати теми,
 * topic_types — які типи тем дозволені (напр. «Новини й оголошення» — лише news).
 */
class ForumCategory extends Model
{
    use HasTranslations;

    /** Блоки таблиці розділів */
    public const GROUPS = ['site' => 'graj.club', 'games' => 'Ігри', 'other' => 'Різне'];

    public array $translatable = ['name', 'description'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'post_roles' => 'array',
            'topic_types' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<ForumCategory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<ForumCategory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<ForumTopic, $this> */
    public function topics(): HasMany
    {
        return $this->hasMany(ForumTopic::class, 'category_id');
    }

    /** @param Builder<ForumCategory> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort');
    }

    /** Чи може користувач створити тему цього типу в розділі */
    public function allowsPosting(?User $user, ?string $type = null): bool
    {
        if (! $user || $user->isBanned() || ! $this->is_active) {
            return false;
        }
        if ($type !== null && $this->topic_types && ! in_array($type, $this->topic_types, true)) {
            return false;
        }
        if ($type === 'news' && ! $user->can('community.news')) {
            return false;
        }

        return ! $this->post_roles || $user->hasAnyRole($this->post_roles) || $user->can('community.manage');
    }

    /** Типи тем, які користувач може створити в розділі */
    public function typesFor(?User $user): array
    {
        return array_values(array_filter(array_keys(ForumTopic::TYPES), fn (string $type) => $this->allowsPosting($user, $type)
            && ($type !== 'server_news' || ($user && $user->servers()->exists()))));
    }

    /** Перерахувати лічильники розділу з опублікованих тем */
    public function refreshCounters(): void
    {
        $topics = $this->topics()->where('status', ForumTopic::PUBLISHED);
        $this->forceFill([
            'topics_count' => (clone $topics)->count(),
            'posts_count' => (int) (clone $topics)->sum('posts_count'),
        ])->saveQuietly();
    }
}
