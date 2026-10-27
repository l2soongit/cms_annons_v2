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

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function coOwners(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(ServerFieldValue::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ServerMedia::class)->orderBy('sort');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(ServerCheck::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', ServerStatus::Published);
    }

    public function scopeRanked(Builder $query): void
    {
        $query->published()->orderByDesc('score')->orderByDesc('votes_month');
    }

    public function address(): string
    {
        return $this->port ? "{$this->host}:{$this->port}" : $this->host;
    }

    public function fillRatio(): float
    {
        return $this->max_players > 0 ? min(1, $this->players / $this->max_players) : 0;
    }
}
