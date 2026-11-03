<?php

namespace App\Community;

use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Str;

/**
 * Автофільтр дописів: стоп-слова, заборонені домени (інші топи, запрошення Discord) і посилання від новачків.
 * Не блокує допис, а відправляє його на перевірку з позначкою причини для модератора.
 */
final class AutoFilter
{
    /** Причина для модератора або null, якщо все гаразд */
    public static function check(User $user, string $text): ?string
    {
        $plain = mb_strtolower(HtmlSanitizer::text($text).' '.$text);

        foreach ((array) config('graj.community.stop_words', []) as $word) {
            $word = mb_strtolower(trim((string) $word));
            if ($word !== '' && str_contains($plain, $word)) {
                return 'Стоп-слово: «'.$word.'»';
            }
        }

        foreach ((array) config('graj.community.banned_domains', []) as $domain) {
            $domain = mb_strtolower(trim((string) $domain));
            if ($domain !== '' && str_contains($plain, $domain)) {
                return 'Заборонене посилання: '.$domain;
            }
        }

        if (config('graj.community.flag_links_for_newbies', true) && self::isNewbie($user)
            && preg_match('~(https?://|www\.)~i', $plain)) {
            return 'Посилання від новачка';
        }

        return null;
    }

    /** Новачок — менше N схвалених дописів і не персонал */
    public static function isNewbie(User $user): bool
    {
        return ! $user->isStaff() && $user->forum_posts_count < (int) config('graj.community.newbie_posts', 5);
    }

    /**
     * Чи відповідь цього користувача йде на перевірку. $mode — режим розділу (all | newbies | none),
     * за замовчуванням загальне налаштування; модератор розділу не перевіряється.
     */
    public static function replyNeedsReview(User $user, string $text, ?string $mode = null, bool $moderator = false): ?string
    {
        if ($moderator) {
            return null;
        }
        $flag = self::check($user, $text);
        if ($flag) {
            return $flag;
        }

        return match ($mode ?? config('graj.community.moderate_replies', 'newbies')) {
            'all' => $user->can('community.moderate') ? null : 'Усі відповіді перевіряються',
            'none' => null,
            default => self::isNewbie($user) ? 'Відповідь новачка' : null,
        };
    }

    public static function excerpt(string $html, int $limit = 220): string
    {
        return Str::limit(HtmlSanitizer::text($html), $limit);
    }
}
