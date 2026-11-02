<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Відповідь у темі (або коментар під новиною — це те саме) */
class ForumPost extends Model
{
    public const STATUSES = ['published' => 'Опубліковано', 'pending' => 'На перевірці', 'hidden' => 'Приховано'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['edited_at' => 'datetime'];
    }

    /** @return BelongsTo<ForumTopic, $this> */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'topic_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ForumPost, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(self::class, 'quote_id');
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
}
