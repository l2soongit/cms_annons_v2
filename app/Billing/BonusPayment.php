<?php

namespace App\Billing;

use App\Community\Bonus;
use App\Models\Order;
use App\Models\User;

/**
 * Бонусні бали в оплаті послуг: знижка, куплена в магазині балів (одноразова), і часткова оплата балами
 * (не більше max_pay_share % ціни за курсом point_value). Викликається всередині транзакції покупки.
 */
final class BonusPayment
{
    /**
     * @return array{0: int, 1: int, 2: int|null} нова ціна в центах, використані бали, відсоток знижки
     */
    public static function apply(User $user, int $cents, int $points): array
    {
        $discount = self::activeDiscount($user);
        if ($discount) {
            $cents -= (int) round($cents * $discount / 100);
        }

        $used = 0;
        $pointCents = (float) config('graj.community.bonus.point_value', 0.01) * 100;
        if ($points > 0 && $pointCents > 0 && Bonus::enabled()) {
            $maxCents = (int) floor($cents * (int) config('graj.community.bonus.max_pay_share', 50) / 100);
            $used = (int) min($points, $user->bonus_balance, floor($maxCents / $pointCents));
            $cents -= (int) round($used * $pointCents);
        }

        return [max(0, $cents), max(0, $used), $discount];
    }

    public static function commit(User $user, Order $order, int $points, ?int $discount): void
    {
        if ($points > 0) {
            Bonus::spend($user, $points, 'payment', $order, '#'.$order->id);
        }
        if ($discount) {
            $perks = (array) $user->perks;
            unset($perks['discount']);
            $user->forceFill(['perks' => $perks ?: null])->save();
        }
    }

    /** Відсоток знижки з магазину балів, якщо куплена й не прострочена */
    public static function activeDiscount(User $user): ?int
    {
        $perk = ((array) $user->perks)['discount'] ?? null;
        if (! $perk || (! empty($perk['until']) && now()->gte($perk['until']))) {
            return null;
        }

        return max(1, min(90, (int) $perk['value']));
    }

    /** Скільки балів максимум можна витратити на послугу цієї ціни */
    public static function maxPoints(User $user, int $cents): int
    {
        $pointCents = (float) config('graj.community.bonus.point_value', 0.01) * 100;
        if ($pointCents <= 0 || ! Bonus::enabled()) {
            return 0;
        }
        $maxCents = (int) floor($cents * (int) config('graj.community.bonus.max_pay_share', 50) / 100);

        return (int) min($user->bonus_balance, floor($maxCents / $pointCents));
    }
}
