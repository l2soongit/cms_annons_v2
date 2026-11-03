<?php

namespace App\Community;

use App\Models\User;
use App\Support\HomePageData;

/**
 * Куплені в магазині балів «плюшки» (users.perks: ключ => {value, until}): колір і ефект ніку, рамка, значок,
 * звання, фон картки автора, підпис з картинкою, анімований аватар. Прострочені не діють.
 */
final class Perks
{
    /** Ефекти ніку: значення => CSS-клас */
    public const NAME_EFFECTS = ['rainbow' => 'cm-fx-rainbow', 'fire' => 'cm-fx-fire', 'ice' => 'cm-fx-ice', 'glow' => 'cm-fx-glow'];

    public static function active(?User $user, string $key): ?string
    {
        $perk = ((array) ($user->perks ?? []))[$key] ?? null;
        if (! is_array($perk) || (! empty($perk['until']) && now()->gte($perk['until']))) {
            return null;
        }

        return isset($perk['value']) ? (string) $perk['value'] : '1';
    }

    public static function has(?User $user, string $key): bool
    {
        return self::active($user, $key) !== null;
    }

    /** CSS-клас ефекту ніку або null */
    public static function nameEffect(?User $user): ?string
    {
        return self::NAME_EFFECTS[self::active($user, 'name_effect') ?? ''] ?? null;
    }

    /** Фон картки автора: власна картинка користувача, поки діє «плюшка» «Фон картки автора» */
    public static function cardBackground(?User $user): ?string
    {
        return $user?->card_bg_image && self::has($user, 'card_bg') ? HomePageData::url($user->card_bg_image) : null;
    }
}
