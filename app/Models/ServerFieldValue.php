<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerFieldValue extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(GameField::class, 'game_field_id');
    }
}
