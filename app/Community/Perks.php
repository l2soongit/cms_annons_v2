<?php

namespace App\Community;

use App\Models\Game;
use App\Models\User;
use App\Support\GameNav;
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

    /** Обкладинка гри для фону картки автора (значення — slug гри) */
    public static function cardBackground(?User $user): ?string
    {
        $slug = self::active($user, 'card_bg');
        if (! $slug) {
            return null;
        }
        $bag = request()->attributes;
        $key = 'perk_cover:'.$slug;
        if (! $bag->has($key)) {
            $game = Game::where('slug', $slug)->first();
            // Обкладинка гри, якщо її немає — вбудований логотип
            $bag->set($key, $game ? (HomePageData::url($game->cover) ?? GameNav::builtinLogo($game)) : null);
        }

        return $bag->get($key);
    }
}
