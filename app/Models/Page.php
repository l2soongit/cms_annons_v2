<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * Статична сторінка (правила, FAQ, контакти) — ТЗ 3.9; тексти для кожної мови сайту.
 */
class Page extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['title', 'body'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
