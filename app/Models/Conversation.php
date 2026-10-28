<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Діалог (ТЗ 3.8): приватний між двома користувачами (гравець ↔ власник сервера) або гілка тікета підтримки.
 */
class Conversation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['read_at', 'last_read_id']);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    /** @return HasOne<Ticket, $this> */
    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** Діалоги користувача (без тікетів), найсвіжіші зверху */
    public function scopeDirectFor(Builder $query, User $user): void
    {
        $query->whereHas('participants', fn ($q) => $q->whereKey($user->id))
            ->whereDoesntHave('ticket')
            ->orderByDesc('last_message_at');
    }

    /** Співрозмовник у приватному діалозі; потрібні завантажені participants */
    public function other(User $user): ?User
    {
        return $this->participants->first(fn (User $p) => $p->id !== $user->id);
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->whereKey($user->id)->exists();
    }

    /** Номер останнього прочитаного користувачем повідомлення; потрібні завантажені participants */
    public function lastReadId(?User $user): int
    {
        /** @var Pivot|null $pivot */
        $pivot = $user ? $this->participants->firstWhere('id', $user->id)?->getRelation('pivot') : null;

        return (int) $pivot?->getAttribute('last_read_id');
    }

    /** Є повідомлення, яких користувач ще не бачив; потрібні завантажені participants */
    public function isUnreadFor(User $user): bool
    {
        return $this->last_message_id && $this->lastReadId($user) < $this->last_message_id;
    }
}
