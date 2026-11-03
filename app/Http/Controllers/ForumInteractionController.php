<?php

namespace App\Http\Controllers;

use App\Community\Activity;
use App\Community\AutoFilter;
use App\Community\Bonus;
use App\Community\Markup;
use App\Community\Subscriptions;
use App\Models\ForumCategory;
use App\Models\ForumPoll;
use App\Models\ForumPollVote;
use App\Models\ForumPost;
use App\Models\ForumReaction;
use App\Models\ForumTopic;
use App\Models\Report;
use App\Models\Server;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Дії в темах: відповіді, оцінки ▲▼, найкраща відповідь, скарги, підписки, опитування.
 */
class ForumInteractionController extends Controller
{
    public function reply(Request $request, ForumTopic $topic): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->isBanned() || ! $topic->isPublished(), 403);
        $moderator = $topic->moderatedBy($user);
        abort_if($topic->is_locked && ! $moderator, 403);
        // Хто відповідає — за правами розділу
        abort_unless($topic->category->allowsReplying($user), 403);

        $limit = (int) config('graj.community.posts_per_hour', 30);
        if (! $moderator && ForumPost::where('user_id', $user->id)->where('created_at', '>=', now()->subHour())->count() >= $limit) {
            throw ValidationException::withMessages(['body' => __('community.reply.limit')]);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'quote_id' => ['nullable', 'integer', Rule::exists('forum_posts', 'id')->where('topic_id', $topic->id)],
        ], [], ['body' => __('community.reply.body')]);

        $flag = AutoFilter::replyNeedsReview($user, $data['body'], $topic->category->replyMode(), $moderator);
        $post = $topic->posts()->create([
            'user_id' => $user->id,
            'quote_id' => $data['quote_id'] ?? null,
            'body' => Markup::toHtml($data['body']),
            'source' => $data['body'],
            'status' => $flag ? 'pending' : 'published',
            'flag_reason' => $flag,
        ]);

        Subscriptions::subscribe($user, $topic);
        Activity::touch($user);

        if ($flag) {
            return redirect(route('community.topic', $topic).'#post-'.$post->id)->with('status', __('community.reply.pending'));
        }

        $this->published($post);

        return redirect(route('community.topic', ['topic' => $topic, 'page' => $this->lastPage($topic)]).'#post-'.$post->id);
    }

    public function updatePost(Request $request, ForumPost $post): RedirectResponse
    {
        $user = $request->user();
        $moderator = $post->topic->moderatedBy($user);
        abort_unless($user->id === $post->user_id || $moderator, 403);
        abort_if($post->status === 'hidden' && ! $moderator, 403);

        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000']]);
        $flag = $moderator ? null : AutoFilter::check($user, $data['body']);

        $post->update([
            'body' => Markup::toHtml($data['body']),
            'source' => $data['body'],
            'edited_at' => now(),
            'status' => $flag ? 'pending' : $post->status,
            'flag_reason' => $flag ?? $post->flag_reason,
        ]);
        if ($flag) {
            $post->topic->refreshCounters();
        }

        return redirect(route('community.topic', $post->topic).'#post-'.$post->id)->with('status', $flag ? __('community.reply.pending') : __('community.form.saved'));
    }

    /** ▲ / ▼ для теми, ▲ для відповіді; повторне натискання знімає оцінку */
    public function react(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_if($user->isBanned(), 403);

        $data = $request->validate([
            'target' => ['required', Rule::in(['topic', 'post'])],
            'id' => ['required', 'integer'],
            'value' => ['required', Rule::in([1, -1])],
        ]);

        $target = $data['target'] === 'topic' ? ForumTopic::findOrFail($data['id']) : ForumPost::findOrFail($data['id']);
        $published = $target instanceof ForumTopic ? $target->isPublished() : $target->status === 'published';
        abort_unless($published && ($target instanceof ForumTopic ? $target : $target->topic)->visibleTo($user), 404);
        if ($target->user_id === $user->id) {
            return $this->reactResponse($request, $target, 0, __('community.react.own'));
        }
        $value = $target instanceof ForumPost ? 1 : (int) $data['value'];

        $mine = DB::transaction(function () use ($user, $target, $value) {
            $existing = ForumReaction::where(['user_id' => $user->id, 'target_type' => $target->getMorphClass(), 'target_id' => $target->getKey()])->first();
            if ($existing) {
                if ($existing->value === 1) {
                    Bonus::revoke($target->user, 'like', $existing);
                }
                $existing->delete();
                if ($existing->value === $value) {
                    $target->forceFill(['score' => (int) $target->reactions()->sum('value')])->saveQuietly();

                    return 0;
                }
            }

            $reaction = ForumReaction::create(['user_id' => $user->id, 'target_type' => $target->getMorphClass(), 'target_id' => $target->getKey(), 'value' => $value]);
            $target->forceFill(['score' => (int) $target->reactions()->sum('value')])->saveQuietly();

            // Бали за ▲ — лише від акаунтів, старших за N днів (захист від накрутки)
            $minDays = (int) config('graj.community.bonus.like_min_account_days', 7);
            if ($value === 1 && $user->created_at && $user->created_at->lte(now()->subDays($minDays))) {
                Bonus::award($target->user, 'like', $reaction);
            }

            return $value;
        });

        Activity::touch($user);

        return $this->reactResponse($request, $target->fresh() ?? $target, $mine);
    }

    public function best(Request $request, ForumPost $post): RedirectResponse
    {
        $user = $request->user();
        $topic = $post->topic;
        abort_unless($user->id === $topic->user_id || $topic->moderatedBy($user), 403);
        abort_unless($post->status === 'published', 404);

        $previous = $topic->bestPost;
        if ($previous) {
            Bonus::revoke($previous->user, 'best_answer', $previous);
        }

        if ($previous?->id === $post->id) {
            $topic->forceFill(['best_post_id' => null])->save();

            return redirect(route('community.topic', $topic).'#post-'.$post->id);
        }

        $topic->forceFill(['best_post_id' => $post->id])->save();
        if ($post->user_id !== $topic->user_id) {
            Bonus::award($post->user, 'best_answer', $post);
        }

        return redirect(route('community.topic', $topic).'#post-'.$post->id)->with('status', __('community.best.saved'));
    }

    public function report(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'target' => ['required', Rule::in(['topic', 'post'])],
            'id' => ['required', 'integer'],
            'reason' => ['required', Rule::in(Report::REASONS)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $target = $data['target'] === 'topic' ? ForumTopic::findOrFail($data['id']) : ForumPost::findOrFail($data['id']);
        abort_unless(($target instanceof ForumTopic ? $target : $target->topic)->visibleTo($request->user()), 404);
        $target->reports()->firstOrCreate(
            ['user_id' => $request->user()->id, 'status' => 'open'],
            ['reason' => $data['reason'], 'comment' => $data['comment'] ?? null],
        );

        return back()->with('status', __('community.report.sent'));
    }

    public function subscribe(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'target' => ['required', Rule::in(['topic', 'category', 'server'])],
            'id' => ['required', 'integer'],
        ]);

        $target = match ($data['target']) {
            'topic' => ForumTopic::findOrFail($data['id']),
            'category' => ForumCategory::findOrFail($data['id']),
            default => Server::findOrFail($data['id']),
        };
        $user = $request->user();
        // Підписатися можна лише на те, що користувач бачить
        abort_if($target instanceof ForumTopic && ! $target->visibleTo($user), 404);
        abort_if($target instanceof ForumCategory && ! $target->canView($user), 404);

        $on = ! Subscriptions::isSubscribed($user, $target);
        $on ? Subscriptions::subscribe($user, $target) : Subscriptions::unsubscribe($user, $target);

        if ($request->wantsJson()) {
            return response()->json(['subscribed' => $on, 'count' => Subscriptions::count($target)]);
        }

        return back()->with('status', $on ? __('community.subscribe.on') : __('community.subscribe.off'));
    }

    public function vote(Request $request, ForumPoll $poll): RedirectResponse
    {
        $user = $request->user();
        abort_unless($poll->topic->isPublished() && ! $poll->isClosed() && $poll->topic->visibleTo($user), 403);

        $count = count($poll->options);
        $data = $request->validate([
            'options' => ['required', 'array', $poll->multiple ? 'max:'.$count : 'max:1'],
            'options.*' => ['integer', 'between:0,'.($count - 1)],
        ]);

        DB::transaction(function () use ($poll, $user, $data) {
            ForumPollVote::where('poll_id', $poll->id)->where('user_id', $user->id)->delete();
            foreach (array_unique($data['options']) as $option) {
                ForumPollVote::create(['poll_id' => $poll->id, 'user_id' => $user->id, 'option' => (int) $option]);
            }
        });
        Activity::touch($user);

        return redirect(route('community.topic', $poll->topic).'#poll')->with('status', __('community.poll.saved'));
    }

    /** Відповідь опубліковано одразу: лічильники, сповіщення підписникам, бали власнику за відповідь у темі сервера */
    private function published(ForumPost $post): void
    {
        $topic = $post->topic;
        User::whereKey($post->user_id)->increment('forum_posts_count');
        $topic->refreshCounters();
        $topic->category->refreshCounters();
        Subscriptions::postPublished($post);

        if ($topic->server && $post->user_id !== $topic->user_id && $post->user->canManage($topic->server)) {
            Bonus::award($post->user, 'owner_reply', $post);
        }
    }

    private function lastPage(ForumTopic $topic): int
    {
        return max(1, (int) ceil($topic->posts()->where('status', 'published')->count() / 30));
    }

    private function reactResponse(Request $request, ForumTopic|ForumPost $target, int $mine, ?string $error = null): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['score' => $target->score, 'mine' => $mine, 'error' => $error], $error ? 422 : 200);
        }

        return back()->with('status', $error);
    }
}
