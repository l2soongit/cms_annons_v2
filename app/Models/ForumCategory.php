<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Translatable\HasTranslations;

/**
 * Розділ форуму. Розділ гри прив'язаний до game_id; post_roles обмежує, хто може створювати теми,
 * topic_types — які типи тем дозволені (напр. «Новини й оголошення» — лише news).
 * Права розділу: view_roles — хто бачить (закритий розділ закриває й підрозділи), reply_roles — хто відповідає,
 * moderate_topics / moderate_replies — власна модерація, moderators — модератори розділу (і його підрозділів).
 * У списках ролей окрім назв ролей бувають «@auth» (усі зареєстровані) і «@owners» (власники серверів).
 */
class ForumCategory extends Model
{
    use HasTranslations;

    /** Блоки таблиці розділів */
    public const GROUPS = ['site' => 'graj.club', 'games' => 'Ігри', 'other' => 'Різне'];

    /** Особливі аудиторії в списках ролей розділу */
    public const AUDIENCES = ['@auth' => 'Усі зареєстровані', '@owners' => 'Власники серверів'];

    /** Режими перевірки відповідей */
    public const REPLY_MODES = ['all' => 'Усі відповіді на перевірку', 'newbies' => 'Лише відповіді новачків', 'none' => 'Без перевірки'];

    public array $translatable = ['name', 'description'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'post_roles' => 'array',
            'view_roles' => 'array',
            'reply_roles' => 'array',
            'moderate_topics' => 'boolean',
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

    /** @return BelongsToMany<User, $this> */
    public function moderators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'forum_category_moderators', 'category_id', 'user_id')->withTimestamps();
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

    /** Чи входить користувач в аудиторію: назви ролей, «@auth», «@owners» */
    public static function inAudience(?User $user, ?array $audience): bool
    {
        if (! $audience) {
            return true;
        }
        if (! $user) {
            return false;
        }
        if (in_array('@auth', $audience, true)) {
            return true;
        }
        if (in_array('@owners', $audience, true) && ($user->servers()->exists() || $user->sharedServers()->exists())) {
            return true;
        }
        $roles = array_values(array_filter($audience, fn ($r) => ! str_starts_with((string) $r, '@')));

        return $roles !== [] && $user->hasAnyRole($roles);
    }

    /** id розділів, які модерує користувач: призначений у розділі — модерує і його підрозділи */
    public static function moderatedIds(User $user): array
    {
        return self::remember('forum_mod:'.$user->id, function () use ($user) {
            $own = DB::table('forum_category_moderators')->where('user_id', $user->id)->pluck('category_id')->all();

            return $own === [] ? [] : array_values(array_unique([...$own, ...self::whereIn('parent_id', $own)->pluck('id')->all()]));
        });
    }

    /** Модерує розділ: загальний модератор спільноти або модератор цього розділу (чи батьківського) */
    public function isModeratedBy(?User $user): bool
    {
        return $user !== null && ($user->can('community.moderate') || in_array($this->id, self::moderatedIds($user), true));
    }

    /** Хто бачить розділ: закритий батьківський розділ закриває й підрозділи; модератори й адміністратори бачать усе */
    public function canView(?User $user): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($user && ($user->can('community.manage') || $this->isModeratedBy($user))) {
            return true;
        }
        if ($this->parent_id && ! ($this->parent ?? self::find($this->parent_id))?->canView($user)) {
            return false;
        }

        return self::inAudience($user, $this->view_roles);
    }

    /** id усіх розділів, які бачить користувач (для запитів списків, бічної колонки, карти сайту) */
    public static function visibleIds(?User $user): array
    {
        return self::remember('forum_view:'.($user->id ?? 0), function () use ($user) {
            $all = self::where('is_active', true)->get()->keyBy('id');
            $all->each(fn (ForumCategory $c) => $c->setRelation('parent', $c->parent_id ? $all->get($c->parent_id) : null));

            return $all->filter(fn (ForumCategory $c) => $c->canView($user))->keys()->all();
        });
    }

    /** Кеш прав у межах одного HTTP-запиту (у новому запиті права рахуються заново) */
    private static function remember(string $key, \Closure $compute): array
    {
        $bag = request()->attributes;
        if (! $bag->has($key)) {
            $bag->set($key, $compute());
        }

        return $bag->get($key);
    }

    /** Хто може відповідати в темах розділу */
    public function allowsReplying(?User $user): bool
    {
        if (! $user || $user->isBanned() || ! $this->canView($user)) {
            return false;
        }

        return $this->isModeratedBy($user) || $user->can('community.manage') || self::inAudience($user, $this->reply_roles);
    }

    /** Режим перевірки відповідей у розділі: власний або загальний */
    public function replyMode(): string
    {
        return $this->moderate_replies ?: (string) config('graj.community.moderate_replies', 'newbies');
    }

    /** Чи може користувач створити тему цього типу в розділі */
    public function allowsPosting(?User $user, ?string $type = null): bool
    {
        if (! $user || $user->isBanned() || ! $this->is_active || ! $this->canView($user)) {
            return false;
        }
        if ($type !== null && $this->topic_types && ! in_array($type, $this->topic_types, true)) {
            return false;
        }
        if ($type === 'news' && ! $user->can('community.news')) {
            return false;
        }

        return self::inAudience($user, $this->post_roles) || $user->can('community.manage') || $this->isModeratedBy($user);
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
