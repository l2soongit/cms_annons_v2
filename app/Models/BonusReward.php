<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/** Нагорода в магазині балів */
class BonusReward extends Model
{
    use HasTranslations;

    public const KINDS = [
        'name_color' => 'Колір ніку',
        'frame' => 'Рамка аватара',
        'badge' => 'Значок у профілі',
        'pin_clan' => 'Закріплення оголошення «Набір у клан»',
        'service_discount' => 'Знижка на платну послугу, %',
    ];

    public const AUDIENCES = ['all' => 'Усім', 'players' => 'Гравцям', 'owners' => 'Власникам серверів'];

    public array $translatable = ['name', 'description'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
