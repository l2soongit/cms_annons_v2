<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Опитування в темі чи новині */
class ForumPoll extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['options' => 'array', 'multiple' => 'boolean', 'closes_at' => 'datetime'];
    }

    /** @return BelongsTo<ForumTopic, $this> */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'topic_id');
    }

    /** @return HasMany<ForumPollVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(ForumPollVote::class, 'poll_id');
    }

    public function isClosed(): bool
    {
        return $this->closes_at !== null && $this->closes_at->isPast();
    }

    /** @return array<int, int> номер варіанта => кількість голосів */
    public function tally(): array
    {
        $counts = $this->votes()->selectRaw('`option` as opt, count(*) as c')->groupBy('option')->pluck('c', 'opt')->all();

        return collect($this->options)->keys()->mapWithKeys(fn ($i) => [$i => (int) ($counts[$i] ?? 0)])->all();
    }
}
