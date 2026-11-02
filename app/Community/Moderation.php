<?php

namespace App\Community;

use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\ModerationLog;
use App\Models\User;
use App\Notifications\ForumModerated;
use App\Support\PageCache;

/**
 * Рішення модератора щодо тем і відповідей: схвалення, відхилення, приховування.
 * Кожна дія — у журнал модерації, автор отримує сповіщення, бали нараховуються лише після схвалення.
 */
final class Moderation
{
    public static function approve(ForumTopic $topic): void
    {
        $first = $topic->published_at === null;
        $topic->forceFill([
            'status' => ForumTopic::PUBLISHED,
            'reject_reason' => null,
            'flag_reason' => null,
            'published_at' => $topic->published_at ?? now(),
            'last_post_at' => $topic->last_post_at ?? now(),
            'last_post_user_id' => $topic->last_post_user_id ?? $topic->user_id,
            'moderated_by' => auth()->id(),
            'moderated_at' => now(),
        ])->save();

        if ($first) {
            User::whereKey($topic->user_id)->increment('forum_posts_count');
        }
        Bonus::award($topic->user, $topic->type === 'server_news' ? 'server_news' : 'topic', $topic);

        self::after($topic, 'forum_topic_approved');
        if ($topic->user_id !== auth()->id()) {
            $topic->user->notify(new ForumModerated($topic, 'approved'));
        }
        if ($first) {
            Subscriptions::topicPublished($topic);
        }
    }

    public static function reject(ForumTopic $topic, string $reason): void
    {
        self::takeDown($topic, ForumTopic::REJECTED, $reason, 'forum_topic_rejected');
        $topic->user->notify(new ForumModerated($topic, 'rejected', $reason));
    }

    public static function hide(ForumTopic $topic, ?string $reason = null): void
    {
        self::takeDown($topic, ForumTopic::HIDDEN, $reason, 'forum_topic_hidden');
        $topic->user->notify(new ForumModerated($topic, 'hidden', $reason));
    }

    /** Повторна перевірка після редагування схваленої теми */
    public static function resubmit(ForumTopic $topic, ?string $flag = null): void
    {
        $topic->forceFill(['status' => ForumTopic::PENDING, 'flag_reason' => $flag ?? 'Тему відредаговано після схвалення'])->save();
        self::after($topic, 'forum_topic_resubmitted', log: false);
    }

    private static function takeDown(ForumTopic $topic, string $status, ?string $reason, string $action): void
    {
        $topic->forceFill([
            'status' => $status,
            'reject_reason' => $reason,
            'is_featured' => false,
            'moderated_by' => auth()->id(),
            'moderated_at' => now(),
        ])->save();

        Bonus::revoke($topic->user, $topic->type === 'server_news' ? 'server_news' : 'topic', $topic);
        self::after($topic, $action, $reason);
    }

    public static function approvePost(ForumPost $post): void
    {
        $post->forceFill(['status' => 'published', 'flag_reason' => null])->save();
        User::whereKey($post->user_id)->increment('forum_posts_count');
        ModerationLog::record($post, 'forum_post_approved');
        $post->topic->refreshCounters();
        $post->topic->category->refreshCounters();
        Subscriptions::postPublished($post);
        $topic = $post->topic;
        if ($topic->server && $post->user_id !== $topic->user_id && $post->user->canManage($topic->server)) {
            Bonus::award($post->user, 'owner_reply', $post);
        }
        PageCache::flush();
    }

    public static function hidePost(ForumPost $post, ?string $reason = null): void
    {
        $post->forceFill(['status' => 'hidden'])->save();
        ModerationLog::record($post, 'forum_post_hidden', $reason);
        Bonus::revoke($post->user, 'like', $post);
        Bonus::revoke($post->user, 'best_answer', $post);
        Bonus::revoke($post->user, 'owner_reply', $post);
        if ($post->topic->best_post_id === $post->id) {
            $post->topic->forceFill(['best_post_id' => null])->saveQuietly();
        }
        $post->topic->refreshCounters();
        $post->topic->category->refreshCounters();
        PageCache::flush();
    }

    private static function after(ForumTopic $topic, string $action, ?string $reason = null, bool $log = true): void
    {
        if ($log) {
            ModerationLog::record($topic, $action, $reason);
        }
        $topic->refreshCounters();
        $topic->category->refreshCounters();
        PageCache::flush();
    }
}
