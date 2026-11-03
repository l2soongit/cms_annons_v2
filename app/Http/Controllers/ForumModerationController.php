<?php

namespace App\Http\Controllers;

use App\Community\Moderation;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\ModerationLog;
use App\Models\User;
use App\Support\PageCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Модерація форуму на сайті: черга тем і відповідей на перевірці та дії в темі.
 * Доступна загальним модераторам спільноти й модераторам розділів — кожному лише в його розділах.
 */
class ForumModerationController extends Controller
{
    public const TOPIC_ACTIONS = ['approve', 'reject', 'hide', 'pin', 'lock'];

    public const POST_ACTIONS = ['approve', 'hide'];

    public function queue(Request $request): View
    {
        $ids = self::categoryIds($request->user());
        abort_if($ids === [], 403);

        return view('community.queue', [
            'topics' => ForumTopic::where('status', ForumTopic::PENDING)->whereIn('category_id', $ids)
                ->with(['user', 'category'])->oldest('id')->limit(100)->get(),
            'posts' => ForumPost::where('status', 'pending')->whereHas('topic', fn ($q) => $q->whereIn('category_id', $ids))
                ->with(['user', 'topic.category'])->oldest('id')->limit(100)->get(),
            // Модератору розділу — список його розділів (загальному модератору — не потрібен)
            'sections' => $request->user()->can('community.moderate') ? collect() : ForumCategory::whereIn('id', $ids)->orderBy('sort')->get(),
        ]);
    }

    public function topic(Request $request, ForumTopic $topic, string $action): RedirectResponse
    {
        abort_unless($topic->moderatedBy($request->user()), 403);

        switch ($action) {
            case 'approve':
                Moderation::approve($topic);
                break;
            case 'reject':
                Moderation::reject($topic, $request->validate(['reason' => ['required', 'string', 'max:500']])['reason']);
                break;
            case 'hide':
                Moderation::hide($topic, $request->input('reason') ?: null);
                break;
            case 'pin':
                $this->toggle($topic, 'is_pinned', 'forum_topic_pinned', 'forum_topic_unpinned');
                break;
            default:
                $this->toggle($topic, 'is_locked', 'forum_topic_locked', 'forum_topic_unlocked');
        }

        return back()->with('status', __('community.mod.done'));
    }

    public function post(Request $request, ForumPost $post, string $action): RedirectResponse
    {
        abort_unless($post->topic->moderatedBy($request->user()), 403);

        if ($action === 'hide') {
            Moderation::hidePost($post, $request->input('reason') ?: null);
        } elseif ($post->status !== 'published') {
            Moderation::approvePost($post);
        }

        return back()->with('status', __('community.mod.done'));
    }

    /** Розділи, які модерує користувач: загальний модератор — усі */
    public static function categoryIds(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return $user->can('community.moderate') ? ForumCategory::pluck('id')->all() : ForumCategory::moderatedIds($user);
    }

    /** Скільки чекає на перевірку в розділах користувача (для лічильника в меню форуму) */
    public static function pendingCount(User $user): int
    {
        $ids = self::categoryIds($user);
        if ($ids === []) {
            return 0;
        }

        return ForumTopic::where('status', ForumTopic::PENDING)->whereIn('category_id', $ids)->count()
            + ForumPost::where('status', 'pending')->whereHas('topic', fn ($q) => $q->whereIn('category_id', $ids))->count();
    }

    private function toggle(ForumTopic $topic, string $field, string $on, string $off): void
    {
        $topic->forceFill([$field => ! $topic->{$field}])->save();
        ModerationLog::record($topic, $topic->{$field} ? $on : $off);
        PageCache::flush();
    }
}
