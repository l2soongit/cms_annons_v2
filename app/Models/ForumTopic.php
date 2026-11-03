<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * Тема форуму. Новина — теж тема (type news / server_news): коментарі під новиною — це відповіді в темі.
 * Кожна тема спершу pending і стає видимою лише після схвалення модератором.
 */
class ForumTopic extends Model
{
    public const PENDING = 'pending';

    public const PUBLISHED = 'published';

    public const REJECTED = 'rejected';

    public const HIDDEN = 'hidden';

    public const STATUSES = [
        self::PENDING => 'На перевірці',
        self::PUBLISHED => 'Опубліковано',
        self::REJECTED => 'Відхилено',
        self::HIDDEN => 'Приховано',
    ];

    /** Типи тем: ключ => назва в адмінці (на сайті — переклад ui.community.types.*) */
    public const TYPES = [
        'discussion' => 'Обговорення',
        'news' => 'Новина graj.club',
        'server_news' => 'Новина сервера',
        'guide' => 'Гайд',
        'question' => 'Питання',
        'clan' => 'Набір у клан',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'last_post_at' => 'datetime',
            'moderated_at' => 'datetime',
            'pinned_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ForumTopic $topic) {
            $topic->slug = $topic->slug ?: (Str::slug(Str::limit($topic->title, 80, '')) ?: 'topic');
        });
    }

    public function getRouteKey(): string
    {
        return $this->id.'-'.$this->slug;
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return self::find((int) $value);
    }

    /** @return BelongsTo<ForumCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ForumCategory::class, 'category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** @return BelongsTo<User, $this> */
    public function lastPostUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_post_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /** @return HasMany<ForumPost, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'topic_id');
    }

    /** @return BelongsTo<ForumPost, $this> */
    public function bestPost(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'best_post_id');
    }

    /** @return HasOne<ForumPoll, $this> */
    public function poll(): HasOne
    {
        return $this->hasOne(ForumPoll::class, 'topic_id');
    }

    /** @return MorphMany<ForumReaction, $this> */
    public function reactions(): MorphMany
    {
        return $this->morphMany(ForumReaction::class, 'target');
    }

    /** @return MorphMany<Report, $this> */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /** @param Builder<ForumTopic> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::PUBLISHED);
    }

    /** @param Builder<ForumTopic> $query */
    public function scopeNews(Builder $query): void
    {
        $query->whereIn('type', ['news', 'server_news']);
    }

    public function isNews(): bool
    {
        return in_array($this->type, ['news', 'server_news'], true);
    }

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
    }

    /** Хто бачить тему: опубліковану — ті, кому відкритий розділ; іншу — автор і модератори розділу */
    public function visibleTo(?User $user): bool
    {
        $own = $user !== null && $user->id === $this->user_id;
        if (! $own && ! $this->category->canView($user)) {
            return false;
        }

        return $this->isPublished() || $own || $this->category->isModeratedBy($user);
    }

    /** Чи може користувач модерувати тему (загальний модератор або модератор її розділу) */
    public function moderatedBy(?User $user): bool
    {
        return $this->category->isModeratedBy($user);
    }

    /** Перерахувати лічильники й останню відповідь */
    public function refreshCounters(): void
    {
        $last = $this->posts()->where('status', 'published')->latest('id')->first();
        $this->forceFill([
            'posts_count' => $this->posts()->where('status', 'published')->count(),
            'last_post_at' => $last->created_at ?? $this->published_at,
            'last_post_user_id' => $last->user_id ?? $this->user_id,
        ])->saveQuietly();
    }
}
