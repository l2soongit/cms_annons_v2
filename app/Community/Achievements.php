<?php

namespace App\Community;

use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Значки за досягнення на форумі — видаються автоматично за повідомлення, теми, найкращі відповіді й стаж.
 * Лічильники тем і найкращих відповідей рахуються одним запитом на сторінку для всіх авторів.
 */
final class Achievements
{
    /** @return array<int, array{topics: int, best: int}> */
    public static function stats(iterable $userIds): array
    {
        $ids = collect($userIds)->unique()->values()->all();
        if ($ids === []) {
            return [];
        }
        $topics = ForumTopic::published()->whereIn('user_id', $ids)->groupBy('user_id')->selectRaw('user_id, COUNT(*) as n')->pluck('n', 'user_id');
        $best = DB::table('forum_topics')->join('forum_posts', 'forum_posts.id', '=', 'forum_topics.best_post_id')
            ->whereIn('forum_posts.user_id', $ids)->groupBy('forum_posts.user_id')->selectRaw('forum_posts.user_id as uid, COUNT(*) as n')->pluck('n', 'uid');

        return collect($ids)->mapWithKeys(fn ($id) => [$id => ['topics' => (int) ($topics[$id] ?? 0), 'best' => (int) ($best[$id] ?? 0)]])->all();
    }

    /** @return list<array{icon: string, key: string}> значки користувача, від найціннішого */
    public static function for(User $user, array $stats): array
    {
        $out = [];
        $posts = (int) $user->forum_posts_count;
        if ($posts >= 1000) {
            $out[] = ['icon' => '🗣', 'key' => 'orator'];
        } elseif ($posts >= 100) {
            $out[] = ['icon' => '💬', 'key' => 'talker'];
        }
        if (($stats['best'] ?? 0) >= 10) {
            $out[] = ['icon' => '🎓', 'key' => 'expert'];
        } elseif (($stats['best'] ?? 0) >= 1) {
            $out[] = ['icon' => '✅', 'key' => 'helper'];
        }
        if (($stats['topics'] ?? 0) >= 10) {
            $out[] = ['icon' => '✍️', 'key' => 'author'];
        }
        if ($user->created_at && $user->created_at->lte(now()->subYear())) {
            $out[] = ['icon' => '🎂', 'key' => 'year'];
        }

        return $out;
    }
}
