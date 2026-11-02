<?php

namespace App\Http\Controllers;

use App\Community\Bonus;
use App\Community\Markup;
use App\Community\Subscriptions;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumReaction;
use App\Models\ForumTopic;
use App\Models\User;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Спільнота: новини зверху, стрічка або таблиця розділів, сторінка теми (новина + обговорення).
 * Показується лише опубліковане; автор і модератори бачать свої теми на перевірці.
 */
class CommunityController extends Controller
{
    public const SORTS = ['hot', 'new', 'top'];

    public function index(Request $request): View
    {
        return $this->feed($request, null, 'feed');
    }

    public function news(Request $request): View
    {
        return $this->feed($request, null, 'news');
    }

    public function mine(Request $request): View
    {
        abort_unless($request->user() !== null, 403);

        return $this->feed($request, null, 'mine');
    }

    public function category(Request $request, ForumCategory $category): View
    {
        abort_unless($category->is_active, 404);

        return $this->feed($request, $category, 'category');
    }

    public function forum(): View
    {
        $categories = ForumCategory::active()->whereNull('parent_id')->with(['game', 'children'])->get();
        $last = $this->lastTopics($categories->pluck('id'));

        return view('community.forum', $this->shared() + [
            'groups' => $categories->groupBy('group'),
            'last' => $last,
            'tab' => 'forum',
        ]);
    }

    public function show(Request $request, ForumTopic $topic): View
    {
        $user = $request->user();
        abort_unless($topic->visibleTo($user) && $topic->category->is_active, 404);

        // Перегляд рахуємо раз на сесію
        $seen = (array) $request->session()->get('forum_seen', []);
        if ($topic->isPublished() && ! in_array($topic->id, $seen, true)) {
            $topic->increment('views');
            $request->session()->put('forum_seen', array_slice([...$seen, $topic->id], -200));
        }

        $topic->load(['user', 'category.game', 'server.game', 'server.fieldValues.field', 'poll', 'bestPost.user']);
        $sort = in_array($request->query('order'), ['best', 'new', 'old'], true) ? $request->query('order') : 'old';

        $posts = $topic->posts()->with(['user', 'quote.user'])
            ->where(fn (Builder $q) => $q->where('status', 'published')
                ->when($user, fn ($w) => $w->orWhere(fn ($own) => $own->where('user_id', $user->id)->where('status', 'pending'))))
            ->when($sort === 'best', fn ($q) => $q->orderByDesc('score')->orderBy('id'))
            ->when($sort === 'new', fn ($q) => $q->orderByDesc('id'))
            ->when($sort === 'old', fn ($q) => $q->orderBy('id'))
            ->paginate(30)->withQueryString();

        [$body, $toc] = Markup::withToc($topic->body);

        $related = ForumTopic::published()->with('category')->where('category_id', $topic->category_id)->whereKeyNot($topic->id)
            ->orderByDesc('last_post_at')->limit(4)->get();

        $poll = $topic->poll;
        $pollVotes = $poll ? $poll->tally() : [];
        $myPollVotes = $poll && $user ? $poll->votes()->where('user_id', $user->id)->pluck('option')->all() : [];

        $authorIds = $posts->getCollection()->pluck('user_id')->push($topic->user_id)->unique();
        $ownerIds = $topic->server ? collect([$topic->server->user_id])->merge($topic->server->coOwners()->pluck('users.id'))->all() : [];

        return view('community.show', $this->shared() + [
            'topic' => $topic,
            'body' => $body,
            'toc' => $toc,
            'posts' => $posts,
            'sort' => $sort,
            'related' => $related,
            'poll' => $poll,
            'pollVotes' => $pollVotes,
            'myPollVotes' => $myPollVotes,
            'myVotes' => $this->myVotes($user, $topic, $posts->getCollection()),
            'subscribed' => Subscriptions::isSubscribed($user, $topic),
            'subscribers' => Subscriptions::count($topic),
            'staffIds' => User::whereIn('id', $authorIds)->get()->filter->isStaff()->pluck('id')->all(),
            'ownerIds' => $ownerIds,
            'canReply' => $user && ! $user->isBanned() && $topic->isPublished() && (! $topic->is_locked || $user->can('community.moderate')),
            'canEdit' => $user && ($user->id === $topic->user_id || $user->can('community.moderate')),
            'canChooseBest' => $user && ($user->id === $topic->user_id || $user->can('community.moderate')),
        ]);
    }

    private function feed(Request $request, ?ForumCategory $category, string $tab): View
    {
        $user = $request->user();
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'hot';
        $type = array_key_exists((string) $request->query('type'), ForumTopic::TYPES) ? (string) $request->query('type') : null;
        $lang = array_key_exists((string) $request->query('lang'), Locales::all()) ? (string) $request->query('lang') : null;

        $query = ForumTopic::query()->with(['user', 'category.game', 'server', 'lastPostUser'])
            ->whereHas('category', fn ($q) => $q->where('is_active', true));

        if ($tab === 'mine') {
            $query->where('user_id', $user?->id);
        } else {
            $query->published();
        }
        if ($category) {
            $ids = $category->children()->pluck('id')->push($category->id);
            $query->whereIn('category_id', $ids);
        }
        if ($tab === 'news') {
            $query->news();
        }
        if ($type) {
            $query->where('type', $type);
        }
        if ($lang) {
            $query->where('locale', $lang);
        }
        if ($request->integer('server')) {
            $query->where('server_id', $request->integer('server'));
        }

        // Закріплені — першими (у стрічці розділу й загальній)
        $query->orderByRaw('CASE WHEN is_pinned = 1 OR pinned_until > ? THEN 0 ELSE 1 END', [now()]);
        match ($sort) {
            'new' => $query->orderByDesc('published_at')->orderByDesc('id'),
            'top' => $query->where('published_at', '>=', now()->subDays(7))->orderByDesc('score')->orderByDesc('posts_count'),
            default => $query->orderByDesc('last_post_at')->orderByDesc('id'),
        };

        $topics = $query->paginate(20)->withQueryString();

        return view('community.index', $this->shared() + [
            'topics' => $topics,
            'category' => $category,
            'tab' => $tab,
            'sort' => $sort,
            'type' => $type,
            'lang' => $lang,
            'myVotes' => $this->myVotes($user, null, $topics->getCollection()),
            'subscribed' => $category ? Subscriptions::isSubscribed($user, $category) : false,
            'canPost' => $user && ($category ? $category->typesFor($user) !== [] : true),
        ]);
    }

    /** Спільне для всіх сторінок: новини зверху, розділи зліва, бічна колонка */
    private function shared(): array
    {
        $news = ForumTopic::published()->news()->with(['user', 'server', 'category.game'])
            ->orderByDesc('is_featured')->orderByDesc('published_at')->limit(4)->get();

        $hot = ForumTopic::published()->with('category.game')->where('last_post_at', '>=', now()->subDays(7))
            ->orderByDesc('posts_count')->limit(4)->get();

        $nav = ForumCategory::active()->whereNull('parent_id')->with('game')->get();
        $user = auth()->user();

        return [
            'featured' => $news->first(),
            'sideNews' => $news->slice(1)->values(),
            'navCategories' => $nav,
            'hot' => $hot,
            'stats' => [
                'topics' => (int) $nav->sum('topics_count'),
                'posts' => (int) $nav->sum('posts_count'),
                'members' => User::where('forum_posts_count', '>', 0)->count(),
            ],
            'online' => User::where('updated_at', '>=', now()->subMinutes(15))->where('forum_posts_count', '>', 0)->limit(8)->get(),
            'level' => $user ? Bonus::level($user) : null,
            'isOwner' => $user && $user->servers()->exists(),
        ];
    }

    /** @return Collection<int, ForumTopic> остання тема кожного розділу */
    private function lastTopics(Collection $ids): Collection
    {
        return ForumTopic::published()->with('lastPostUser')->whereIn('category_id', $ids)
            ->orderByDesc('last_post_at')->get()->unique('category_id')->keyBy('category_id');
    }

    /** @return array<string, int> «topic-5» / «post-9» => оцінка поточного користувача */
    private function myVotes(?User $user, ?ForumTopic $topic, Collection $items): array
    {
        if (! $user) {
            return [];
        }

        $topicIds = $items->whereInstanceOf(ForumTopic::class)->pluck('id')->when($topic, fn ($c) => $c->push($topic->id));
        $postIds = $items->whereInstanceOf(ForumPost::class)->pluck('id');

        return ForumReaction::where('user_id', $user->id)
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('target_type', (new ForumTopic)->getMorphClass())->whereIn('target_id', $topicIds))
                ->orWhere(fn ($w) => $w->where('target_type', (new ForumPost)->getMorphClass())->whereIn('target_id', $postIds)))
            ->get()->mapWithKeys(fn (ForumReaction $r) => [($r->target_type === (new ForumTopic)->getMorphClass() ? 'topic-' : 'post-').$r->target_id => $r->value])
            ->all();
    }
}
