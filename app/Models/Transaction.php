<?php

namespace App\Models;

use App\Billing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'rate' => 'decimal:6',
            'payload' => 'array',
        ];
    }

    /** Скільки списує платіжна система, у центах валюти оплати (для поповнення в гривнях — сам amount) */
    public function chargeCents(): int
    {
        return Money::cents($this->paid_amount ?? $this->amount);
    }

    public function chargeCurrency(): string
    {
        return $this->paid_currency ?? $this->currency;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
