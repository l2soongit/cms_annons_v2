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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Спільнота у вигляді класичного форуму (дизайн design/community/community-v2-d.html):
 * головна з блоками розділів, списки тем (розділ, нові повідомлення, новини, мої теми), тема з карткою автора.
 * Показується лише опубліковане; автор і модератори бачать свої теми на перевірці.
 */
class CommunityController extends Controller
{
    public const SORTS = ['hot', 'new', 'top'];

    /** Відповідей на сторінці теми — від цього залежать номери сторінок у списку тем */
    public const PER_PAGE = 30;

    /** Головна форуму: новини зверху, блоки розділів (graj.club, ігри, різне) з підрозділами й останньою темою */
    public function index(): View
    {
        $visible = ForumCategory::visibleIds(auth()->user());
        $categories = ForumCategory::active()->whereNull('parent_id')->whereIn('id', $visible)
            ->with(['game', 'children' => fn ($q) => $q->where('is_active', true)->whereIn('id', $visible)])->get();
        $ids = $categories->pluck('id')->merge($categories->flatMap(fn (ForumCategory $c) => $c->children->pluck('id')));
        $last = $this->lastTopics($ids);

        // Лічильники й остання тема розділу — разом із підрозділами
        $nodes = $categories->mapWithKeys(function (ForumCategory $c) use ($last) {
            $family = $c->children->concat([$c]);

            return [$c->id => [
                'topics' => (int) $family->sum('topics_count'),
                'posts' => (int) $family->sum('posts_count'),
                'last' => $family->map(fn (ForumCategory $f) => $last[$f->id] ?? null)->filter()->sortByDesc('last_post_at')->first(),
            ]];
        });

        return view('community.index', $this->shared() + [
            'groups' => $categories->groupBy('group'),
            'nodes' => $nodes,
            'tab' => 'forum',
        ]);
    }

    /** Нові повідомлення: усі опубліковані теми, свіжі відповіді першими (sort=new — нові теми) */
    public function latest(Request $request): View
    {
        return $this->feed($request, null, 'latest');
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
        $category->loadMissing(['parent', 'game']);
        abort_unless($category->canView($request->user()), 404);

        return $this->feed($request, $category, 'category');
    }

    /** Колишня таблиця розділів — тепер це головна форуму */
    public function forum(): RedirectResponse
    {
        return redirect()->route('community', status: 301);
    }

    public function show(Request $request, ForumTopic $topic): View
    {
        $user = $request->user();
        abort_unless($topic->visibleTo($user) && $topic->category->is_active, 404);
        $moderator = $topic->moderatedBy($user);

        // Перегляд рахуємо раз на сесію
        $seen = (array) $request->session()->get('forum_seen', []);
        if ($topic->isPublished() && ! in_array($topic->id, $seen, true)) {
            $topic->increment('views');
            $request->session()->put('forum_seen', array_slice([...$seen, $topic->id], -200));
        }

        $topic->load(['user', 'category.game', 'category.parent', 'server.game', 'server.fieldValues.field', 'poll', 'bestPost.user']);
        $sort = in_array($request->query('order'), ['best', 'new', 'old'], true) ? $request->query('order') : 'old';

        $posts = $topic->posts()->with(['user', 'quote.user'])
            ->where(fn (Builder $q) => $q->where('status', 'published')
                ->when($moderator, fn ($w) => $w->orWhere('status', 'pending'))
                ->when($user && ! $moderator, fn ($w) => $w->orWhere(fn ($own) => $own->where('user_id', $user->id)->where('status', 'pending'))))
            ->when($sort === 'best', fn ($q) => $q->orderByDesc('score')->orderBy('id'))
            ->when($sort === 'new', fn ($q) => $q->orderByDesc('id'))
            ->when($sort === 'old', fn ($q) => $q->orderBy('id'))
            ->paginate(self::PER_PAGE)->withQueryString();

        $related = ForumTopic::published()->with(['category', 'lastPostUser', 'user', 'server'])->where('category_id', $topic->category_id)->whereKeyNot($topic->id)
            ->orderByDesc('last_post_at')->limit(5)->get();

        $poll = $topic->poll;
        $pollVotes = $poll ? $poll->tally() : [];
        $myPollVotes = $poll && $user ? $poll->votes()->where('user_id', $user->id)->pluck('option')->all() : [];

        $authorIds = $posts->getCollection()->pluck('user_id')->push($topic->user_id)->unique();
        $ownerIds = $topic->server ? collect([$topic->server->user_id])->merge($topic->server->coOwners()->pluck('users.id'))->all() : [];

        return view('community.show', [
            'topic' => $topic,
            'body' => Markup::withToc($topic->body)[0],
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
            // Значок «Власник сервера» в картці автора — для всіх, хто має сервер у каталозі
            'serverOwnerIds' => User::whereIn('id', $authorIds)->whereHas('servers')->pluck('id')->all(),
            'canReply' => $user && $topic->isPublished() && (! $topic->is_locked || $moderator) && $topic->category->allowsReplying($user),
            'canEdit' => $user && ($user->id === $topic->user_id || $moderator),
            'canChooseBest' => $user && ($user->id === $topic->user_id || $moderator),
            'canModerate' => $moderator,
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
            $query->published()->whereIn('category_id', ForumCategory::visibleIds($user));
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

        // Закріплені — першими (у розділі й у загальних списках), окремим блоком
        $query->orderByRaw('CASE WHEN is_pinned = 1 OR pinned_until > ? THEN 0 ELSE 1 END', [now()]);
        match ($sort) {
            'new' => $query->orderByDesc('published_at')->orderByDesc('id'),
            'top' => $query->where('published_at', '>=', now()->subDays(7))->orderByDesc('score')->orderByDesc('posts_count'),
            default => $query->orderByDesc('last_post_at')->orderByDesc('id'),
        };

        $topics = $query->paginate(20)->withQueryString();

        // Підрозділи розділу — блоком над списком тем
        $children = $category ? $category->children()->where('is_active', true)->whereIn('id', ForumCategory::visibleIds($user))->with('game')->get() : collect();

        return view('community.list', $this->shared() + [
            'topics' => $topics,
            'children' => $children,
            'childLast' => $children->isNotEmpty() ? $this->lastTopics($children->pluck('id')) : collect(),
            'category' => $category,
            'tab' => $tab,
            'sort' => $sort,
            'type' => $type,
            'lang' => $lang,
            'subscribed' => $category ? Subscriptions::isSubscribed($user, $category) : false,
            'canPost' => $user && ($category ? $category->typesFor($user) !== [] : true),
        ]);
    }

    /** Спільне для сторінок форуму: новини зверху, бічна колонка (бали, хто онлайн, останні повідомлення, статистика) */
    private function shared(): array
    {
        $user = auth()->user();
        $visible = ForumCategory::visibleIds($user);
        $news = ForumTopic::published()->news()->whereIn('category_id', $visible)->with(['user', 'server', 'category.game'])
            ->orderByDesc('is_featured')->orderByDesc('published_at')->limit(3)->get();

        $latest = ForumTopic::published()->with(['lastPostUser', 'category.game'])->whereIn('category_id', $visible)
            ->orderByDesc('last_post_at')->limit(5)->get();

        $all = ForumCategory::where('is_active', true)->get(['topics_count', 'posts_count']);
        $online = User::where('updated_at', '>=', now()->subMinutes(15))->where('forum_posts_count', '>', 0)
            ->orderByDesc('updated_at')->limit(30)->get();

        return [
            'news' => $news,
            'latestTopics' => $latest,
            'stats' => [
                'topics' => (int) $all->sum('topics_count'),
                'posts' => (int) $all->sum('posts_count'),
                'members' => User::where('forum_posts_count', '>', 0)->count(),
                'newest' => User::where('forum_posts_count', '>', 0)->latest('id')->first(),
            ],
            'online' => $online,
            'onlineStaff' => $online->filter->isStaff()->pluck('id')->all(),
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
