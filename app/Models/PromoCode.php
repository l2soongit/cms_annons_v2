<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** Чи діє код на цю послугу зараз */
    public function appliesTo(Tariff $tariff): bool
    {
        return $this->is_active
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && ($this->max_uses === null || $this->used < $this->max_uses)
            && ($this->service === null || $this->service === $tariff->service);
    }

    /** Ціна зі знижкою, у копійках (округлення на користь покупця) */
    public function apply(int $cents): int
    {
        return intdiv($cents * (100 - $this->discount_percent), 100);
    }
}
