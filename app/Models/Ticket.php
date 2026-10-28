<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Звернення в підтримку (ТЗ 3.8): категорія, статус, відповідальний; листування — у діалозі тікета.
 */
class Ticket extends Model
{
    protected $guarded = [];

    public const CATEGORIES = ['payment', 'moderation', 'technical', 'other'];

    /** open — чекає відповіді підтримки, answered — підтримка відповіла, closed — закрито */
    public const STATUSES = ['open', 'answered', 'closed'];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
