<?php

namespace App\Models;

use App\Enums\ServerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Server extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    protected $guarded = [];

    public array $translatable = ['description'];

    protected $hidden = ['callback_secret', 'verify_token'];

    protected function casts(): array
    {
        return [
            'status' => ServerStatus::class,
            'pending_changes' => 'array',
            'languages' => 'array',
            'offline_since' => 'datetime',
            'offline_notified_at' => 'datetime',
            'suspicious_online' => 'boolean',
            'is_online' => 'boolean',
            'uptime' => 'decimal:2',
            'verified_at' => 'datetime',
            'checked_at' => 'datetime',
            'opens_at' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function coOwners(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** @return HasMany<ServerFieldValue, $this> */
    public function fieldValues(): HasMany
    {
        return $this->hasMany(ServerFieldValue::class);
    }

    /** @return HasMany<ServerMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(ServerMedia::class)->orderBy('sort');
    }

    /** @return HasMany<ServerCheck, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(ServerCheck::class);
    }

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return MorphMany<Report, $this> */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', ServerStatus::Published);
    }

    /** @param Builder<Server> $query */
    public function scopeRanked(Builder $query): void
    {
        $query->published()->orderByDesc('score')->orderByDesc('votes_month');
    }

    public function address(): string
    {
        return $this->port ? "{$this->host}:{$this->port}" : $this->host;
    }

    /** Підписи значень полів гри («Interlude», «x50»); потрібні завантажені fieldValues.field */
    public function fieldLabels(): string
    {
        return $this->fieldValues
            ->map(fn ($v) => collect($v->field?->options)->firstWhere('value', $v->value)['label'] ?? $v->value)
            ->filter()
            ->implode(' ');
    }

    public function fillRatio(): float
    {
        return $this->max_players > 0 ? min(1, $this->players / $this->max_players) : 0;
    }
}
