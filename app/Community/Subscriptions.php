<?php

namespace App\Community;

use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumSubscription;
use App\Models\ForumTopic;
use App\Models\Server;
use App\Models\User;
use App\Notifications\ForumActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/** Підписки на теми, розділи й сервери та розсилка сповіщень підписникам */
final class Subscriptions
{
    public static function subscribe(User $user, Model $target): void
    {
        ForumSubscription::firstOrCreate(['user_id' => $user->id, 'subscribable_type' => $target->getMorphClass(), 'subscribable_id' => $target->getKey()]);
    }

    public static function unsubscribe(User $user, Model $target): void
    {
        ForumSubscription::where(['user_id' => $user->id, 'subscribable_type' => $target->getMorphClass(), 'subscribable_id' => $target->getKey()])->delete();
    }

    public static function isSubscribed(?User $user, Model $target): bool
    {
        return $user !== null && ForumSubscription::where(['user_id' => $user->id, 'subscribable_type' => $target->getMorphClass(), 'subscribable_id' => $target->getKey()])->exists();
    }

    public static function count(Model $target): int
    {
        return ForumSubscription::where(['subscribable_type' => $target->getMorphClass(), 'subscribable_id' => $target->getKey()])->count();
    }

    /** Тему опубліковано: автор підписується на неї, підписники розділу й сервера отримують сповіщення */
    public static function topicPublished(ForumTopic $topic): void
    {
        self::subscribe($topic->user, $topic);

        $targets = [[(new ForumCategory)->getMorphClass(), $topic->category_id]];
        if ($topic->server_id) {
            $targets[] = [(new Server)->getMorphClass(), $topic->server_id];
        }

        $ids = ForumSubscription::where(function ($q) use ($targets) {
            foreach ($targets as [$type, $id]) {
                $q->orWhere(fn ($w) => $w->where('subscribable_type', $type)->where('subscribable_id', $id));
            }
        })->where('user_id', '!=', $topic->user_id)->distinct()->pluck('user_id');

        self::send($ids->all(), new ForumActivity($topic), $topic);
    }

    /** Нова відповідь: сповіщення підписникам теми, крім автора відповіді */
    public static function postPublished(ForumPost $post): void
    {
        $ids = ForumSubscription::where('subscribable_type', $post->topic->getMorphClass())->where('subscribable_id', $post->topic_id)
            ->where('user_id', '!=', $post->user_id)->pluck('user_id');

        self::send($ids->all(), new ForumActivity($post->topic, $post), $post->topic);
    }

    /** @param list<int> $ids */
    private static function send(array $ids, ForumActivity $notification, ForumTopic $topic): void
    {
        if ($ids === []) {
            return;
        }
        // Закритий розділ: сповіщення лише тим, хто його бачить
        $category = $topic->category;
        User::whereIn('id', $ids)->whereNull('banned_until')->orWhere(fn ($q) => $q->whereIn('id', $ids)->where('banned_until', '<', now()))
            ->chunkById(200, fn ($users) => Notification::send($users->filter(fn (User $u) => $category->canView($u)), $notification));
    }
}
