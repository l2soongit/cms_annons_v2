<?php

namespace App\Community;

use App\Models\BonusTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Бонусна програма: бали окремо від грошового балансу.
 * Автоматичні нарахування мають денну межу й не дублюються для того самого об'єкта;
 * якщо тему чи відповідь прибрали, зароблене за неї знімається (revoke).
 */
final class Bonus
{
    /** Причини руху балів: ключ => назва в адмінці */
    public const REASONS = [
        'topic' => 'Схвалена тема',
        'server_news' => 'Схвалена новина сервера',
        'best_answer' => 'Найкраща відповідь',
        'like' => 'Оцінка ▲ допису',
        'daily_vote' => 'Голос за сервер',
        'streak_7' => '7 днів активності поспіль',
        'owner_reply' => 'Відповідь власника в темі сервера',
        'revoke' => 'Зняття: допис прибрано',
        'manual' => 'Вручну адміністратором',
        'reward' => 'Покупка в магазині балів',
        'payment' => 'Оплата послуги балами',
        'refund' => 'Повернення балів',
    ];

    /** Автоматичні причини, на які діє денна межа */
    private const CAPPED = ['topic', 'server_news', 'best_answer', 'like', 'daily_vote', 'streak_7', 'owner_reply'];

    public static function enabled(): bool
    {
        return (bool) config('graj.community.bonus.enabled', true);
    }

    /**
     * Нарахувати бали за подію. Повертає транзакцію або null, якщо програма вимкнена,
     * бали за цей об'єкт уже нараховано чи вичерпано денну межу.
     */
    public static function award(User $user, string $reason, ?Model $subject = null, ?int $amount = null): ?BonusTransaction
    {
        $amount ??= (int) config("graj.community.bonus.{$reason}", 0);
        if (! self::enabled() || $amount <= 0 || $user->isBanned()) {
            return null;
        }

        if ($subject && self::earnedFor($user, $reason, $subject) > 0) {
            return null;
        }

        if (in_array($reason, self::CAPPED, true)) {
            $today = (int) BonusTransaction::where('user_id', $user->id)->whereIn('reason', self::CAPPED)
                ->where('created_at', '>=', now()->startOfDay())->sum('amount');
            $amount = min($amount, max(0, (int) config('graj.community.bonus.daily_cap', 100) - $today));
            if ($amount <= 0) {
                return null;
            }
        }

        return self::apply($user, $amount, $reason, $subject, earned: true);
    }

    /** Зняти бали, нараховані за об'єкт (тему прибрали, лайк скасували) */
    public static function revoke(User $user, string $reason, Model $subject): ?BonusTransaction
    {
        $earned = self::earnedFor($user, $reason, $subject);
        if ($earned <= 0) {
            return null;
        }

        // Позначаємо зняття тим самим reason у note, щоб повторне нарахування знову було можливе
        return self::apply($user, -$earned, 'revoke', $subject, note: $reason, earned: true);
    }

    /** Ручне коригування адміністратором (+ або −) */
    public static function adjust(User $user, int $amount, string $note, ?User $by = null): BonusTransaction
    {
        return self::apply($user, $amount, 'manual', null, note: $note, by: $by, earned: $amount > 0);
    }

    /** Витратити бали; false — якщо не вистачає */
    public static function spend(User $user, int $amount, string $reason, ?Model $subject = null, ?string $note = null): bool
    {
        if ($amount <= 0) {
            return true;
        }

        return DB::transaction(function () use ($user, $amount, $reason, $subject, $note) {
            $fresh = User::whereKey($user->id)->lockForUpdate()->first();
            if (! $fresh || $fresh->bonus_balance < $amount) {
                return false;
            }
            self::apply($user, -$amount, $reason, $subject, note: $note);

            return true;
        });
    }

    /** Скільки балів зараз дає відповідь за об'єкт (нарахування мінус зняття) */
    public static function earnedFor(User $user, string $reason, Model $subject): int
    {
        $base = BonusTransaction::where('user_id', $user->id)
            ->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey());

        return (int) (clone $base)->where('reason', $reason)->sum('amount')
            + (int) (clone $base)->where('reason', 'revoke')->where('note', $reason)->sum('amount');
    }

    private static function apply(User $user, int $amount, string $reason, ?Model $subject, ?string $note = null, ?User $by = null, bool $earned = false): BonusTransaction
    {
        return DB::transaction(function () use ($user, $amount, $reason, $subject, $note, $by, $earned) {
            $tx = BonusTransaction::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'reason' => $reason,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'note' => $note,
                'created_by' => $by?->id,
                'created_at' => now(),
            ]);

            User::whereKey($user->id)->increment('bonus_balance', $amount);
            if ($earned) {
                // Рівень рахується із заробленого; зняття зменшує, але не нижче нуля
                $current = (int) DB::table('users')->where('id', $user->id)->lockForUpdate()->value('bonus_earned');
                DB::table('users')->where('id', $user->id)->update(['bonus_earned' => max(0, $current + $amount)]);
            }
            $user->refresh();

            return $tx;
        });
    }

    /**
     * Рівень учасника за заробленими балами.
     *
     * @return array{key: string, index: int, min: int, next: int|null}
     */
    public static function level(User $user): array
    {
        $levels = config('graj.community.levels', [0 => 'newbie']);
        ksort($levels);
        $current = ['key' => 'newbie', 'index' => 0, 'min' => 0, 'next' => null];
        $i = 0;
        foreach ($levels as $min => $key) {
            if ($user->bonus_earned >= $min) {
                $current = ['key' => (string) $key, 'index' => $i, 'min' => (int) $min, 'next' => null];
            } elseif ($current['next'] === null) {
                $current['next'] = (int) $min;
            }
            $i++;
        }

        return $current;
    }
}
