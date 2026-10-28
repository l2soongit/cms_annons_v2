<?php

namespace App\Filament\Support;

use App\Support\Locales;
use Closure;
use Filament\Forms\Components\Field;

/**
 * Поле форми адмінки для кожної мови сайту: name.uk, name.en, … — нова мова з Налаштувань з’являється сама.
 */
final class Localized
{
    /**
     * @param  Closure(string $path, string $locale): Field  $make  створює поле для шляху «атрибут.мова»
     * @return list<Field>
     */
    public static function fields(string $attribute, string $label, Closure $make, bool $required = false): array
    {
        return collect(array_keys(Locales::all()))->map(function (string $locale) use ($attribute, $label, $make, $required) {
            $field = $make("{$attribute}.{$locale}", $locale)->label("{$label} ({$locale})");

            // Обов’язковий лише текст основною мовою; решта — переклади за бажанням
            return $required && $locale === Locales::default() ? $field->required() : $field;
        })->all();
    }
}
