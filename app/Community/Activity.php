<?php

namespace App\Community;

use App\Models\User;

/**
 * Серія днів активності (тема, відповідь, оцінка, голос): кожні 7 днів поспіль — бонус streak_7.
 */
final class Activity
{
    public static function touch(User $user): void
    {
        $today = now()->startOfDay();
        $last = $user->streak_date;

        if ($last && $last->isSameDay($today)) {
            return;
        }

        $streak = $last && $last->copy()->addDay()->isSameDay($today) ? $user->streak_days + 1 : 1;
        $user->forceFill(['streak_days' => $streak, 'streak_date' => $today->toDateString()])->saveQuietly();

        if ($streak % 7 === 0) {
            Bonus::award($user, 'streak_7');
        }
    }
}
