<?php

namespace App\Http\Controllers;

use App\Community\Activity;
use App\Community\AutoFilter;
use App\Community\Markup;
use App\Community\Moderation;
use App\Models\ForumCategory;
use App\Models\ForumPoll;
use App\Models\ForumTopic;
use App\Models\Server;
use App\Models\User;
use App\Support\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Створення й редагування тем із сайту. Кожна нова тема — на перевірку модератором;
 * редагування схваленої теми знову відправляє її на перевірку (налаштовується в адмінці).
 * Новину сервера пише власник чи співвласник — вона з'являється на сторінці сервера, у розділі гри й у стрічці.
 */
class ForumTopicController extends Controller
{
    public function create(Request $request): View
    {
        $user = $request->user();
        abort_if($user->isBanned(), 403);

        $categories = ForumCategory::active()->with('parent')->get()->filter(fn (ForumCategory $c) => $c->typesFor($user) !== [])->values();
        abort_if($categories->isEmpty(), 403);

        $server = $request->integer('server') ? Server::find($request->integer('server')) : null;
        if ($server && ! $user->canManage($server)) {
            $server = null;
        }
        // Новина сервера — одразу в підрозділ «Новини серверів» його гри (якщо є), інакше в розділ гри
        $preset = $server ? ForumCategory::where('game_id', $server->game_id)->where('is_active', true)
            ->orderByRaw("CASE WHEN slug LIKE '%-news' THEN 0 ELSE 1 END")->orderBy('sort')->value('id') : $request->integer('category');

        return view('community.form', [
            'topic' => new ForumTopic(['type' => $server ? 'server_news' : ((string) $request->query('type') ?: 'discussion'), 'category_id' => $preset, 'server_id' => $server?->id]),
            'categories' => $categories,
            'servers' => $this->ownServers($user),
            'types' => $categories->mapWithKeys(fn (ForumCategory $c) => [$c->id => $c->typesFor($user)])->all(),
            'source' => '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->isBanned(), 403);

        $limit = (int) config('graj.community.topics_per_day', 5);
        if (! $user->can('community.moderate') && ForumTopic::where('user_id', $user->id)->where('created_at', '>=', now()->subDay())->count() >= $limit) {
            throw ValidationException::withMessages(['title' => __('community.form.limit', ['n' => $limit])]);
        }

        $data = $this->validated($request, $user);
        $html = Markup::toHtml($data['body']);

        $topic = ForumTopic::create([
            'category_id' => $data['category_id'],
            'user_id' => $user->id,
            'server_id' => $data['server_id'] ?? null,
            'type' => $data['type'],
            'title' => $data['title'],
            'body' => $html,
            'source' => $data['body'],
            'excerpt' => AutoFilter::excerpt($html),
            'cover' => $request->hasFile('cover') ? ImageOptimizer::store($request->file('cover'), 'forum', 'cover') : null,
            'locale' => app()->getLocale(),
            'meta' => $this->meta($data),
            'status' => ForumTopic::PENDING,
            'flag_reason' => AutoFilter::check($user, $data['title'].' '.$data['body']),
        ]);

        $this->savePoll($topic, $data);
        Activity::touch($user);

        // Розділ без перевірки або тема модератора розділу — публікується одразу (якщо автофільтр нічого не знайшов)
        $category = $topic->category;
        if (! $topic->flag_reason && (! $category->moderate_topics || $category->isModeratedBy($user))) {
            Moderation::approve($topic);

            return to_route('community.topic', $topic)->with('status', __('community.form.published'));
        }

        return to_route('community.topic', $topic)->with('status', __('community.form.sent'));
    }

    public function edit(Request $request, ForumTopic $topic): View
    {
        $user = $request->user();
        abort_unless($user->id === $topic->user_id || $topic->moderatedBy($user), 403);

        $categories = ForumCategory::active()->with('parent')->get()->filter(fn (ForumCategory $c) => $c->typesFor($user) !== [] || $c->id === $topic->category_id)->values();

        return view('community.form', [
            'topic' => $topic->load('poll'),
            'categories' => $categories,
            'servers' => $this->ownServers($user),
            'types' => $categories->mapWithKeys(fn (ForumCategory $c) => [$c->id => array_values(array_unique([...$c->typesFor($user), $topic->type]))])->all(),
            'source' => Markup::source($topic->source, $topic->body),
        ]);
    }

    public function update(Request $request, ForumTopic $topic): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->id === $topic->user_id || $topic->moderatedBy($user), 403);

        $data = $this->validated($request, $user, $topic);
        $html = Markup::toHtml($data['body']);
        $wasPublished = $topic->isPublished();

        $topic->fill([
            'category_id' => $data['category_id'],
            'server_id' => $data['server_id'] ?? null,
            'type' => $data['type'],
            'title' => $data['title'],
            'body' => $html,
            'source' => $data['body'],
            'excerpt' => AutoFilter::excerpt($html),
            'meta' => $this->meta($data),
        ]);
        if ($request->hasFile('cover')) {
            $topic->cover = ImageOptimizer::store($request->file('cover'), 'forum', 'cover');
        }
        $topic->save();
        $this->savePoll($topic, $data);

        $flag = AutoFilter::check($user, $data['title'].' '.$data['body']);
        $topic->load('category');
        $moderator = $topic->moderatedBy($user);
        $review = config('graj.community.edit_requires_review', true) && $topic->category->moderate_topics;
        if ($wasPublished && ! $moderator && ($review || $flag)) {
            Moderation::resubmit($topic, $flag);

            return to_route('community.topic', $topic)->with('status', __('community.form.resent'));
        }
        if (! $wasPublished && $topic->status === ForumTopic::REJECTED) {
            // Відхилена тема після виправлення — знову в чергу
            $topic->forceFill(['status' => ForumTopic::PENDING, 'flag_reason' => $flag])->save();
        }

        return to_route('community.topic', $topic)->with('status', __('community.form.saved'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, User $user, ?ForumTopic $topic = null): array
    {
        $category = ForumCategory::where('is_active', true)->find($request->integer('category_id'));
        $types = $category ? $category->typesFor($user) : [];
        if ($topic && $category?->id === $topic->category_id) {
            $types[] = $topic->type;
        }

        $data = $request->validate([
            'category_id' => ['required', Rule::exists('forum_categories', 'id')->where('is_active', true)],
            'type' => ['required', Rule::in($types)],
            'title' => ['required', 'string', 'min:8', 'max:160'],
            'body' => ['required', 'string', 'min:30', 'max:20000'],
            'server_id' => ['nullable', 'required_if:type,server_news', 'integer', 'exists:servers,id'],
            'cover' => ['nullable', 'image', 'max:5120'],
            'clan_level' => ['nullable', 'string', 'max:40'],
            'clan_prime' => ['nullable', 'string', 'max:40'],
            'clan_language' => ['nullable', 'string', 'max:40'],
            'clan_server' => ['nullable', 'string', 'max:80'],
            'poll_question' => ['nullable', 'string', 'max:200'],
            'poll_options' => ['nullable', 'array', 'max:8'],
            'poll_options.*' => ['nullable', 'string', 'max:100'],
            'poll_multiple' => ['nullable', 'boolean'],
            'poll_days' => ['nullable', 'integer', 'between:1,60'],
        ], [], [
            'category_id' => __('community.form.category'),
            'title' => __('community.form.title_label'),
            'body' => __('community.form.body'),
        ]);

        if (! empty($data['server_id']) && ! $user->canManage(Server::findOrFail($data['server_id'])) && ! $user->can('community.moderate')) {
            throw ValidationException::withMessages(['server_id' => __('community.form.not_your_server')]);
        }
        if (($data['type'] ?? null) !== 'server_news' && ($data['type'] ?? null) !== 'clan') {
            $data['server_id'] = $topic && $topic->type === $data['type'] ? $topic->server_id : null;
        }

        return $data;
    }

    private function meta(array $data): ?array
    {
        if ($data['type'] !== 'clan') {
            return null;
        }

        return array_filter([
            'level' => $data['clan_level'] ?? null,
            'prime' => $data['clan_prime'] ?? null,
            'language' => $data['clan_language'] ?? null,
            'server' => $data['clan_server'] ?? null,
        ]) ?: null;
    }

    private function savePoll(ForumTopic $topic, array $data): void
    {
        $options = array_values(array_filter(array_map('trim', (array) ($data['poll_options'] ?? [])), fn (string $o) => $o !== ''));
        if (blank($data['poll_question'] ?? null) || count($options) < 2) {
            // Опитування з голосами не видаляємо, щоб не втратити результати
            if ($topic->poll && $topic->poll->votes()->doesntExist()) {
                $topic->poll->delete();
            }

            return;
        }

        $poll = $topic->poll ?? new ForumPoll(['topic_id' => $topic->id]);
        if ($poll->exists && $poll->votes()->exists()) {
            $poll->fill(['question' => $data['poll_question']])->save(); // варіанти з голосами не міняємо

            return;
        }
        $poll->fill([
            'question' => $data['poll_question'],
            'options' => $options,
            'multiple' => (bool) ($data['poll_multiple'] ?? false),
            'closes_at' => ! empty($data['poll_days']) ? now()->addDays((int) $data['poll_days']) : null,
        ])->save();
    }

    /** @return Collection<int, Server> */
    private function ownServers(User $user)
    {
        return Server::where('user_id', $user->id)->orWhereHas('coOwners', fn ($q) => $q->whereKey($user->id))->orderBy('name')->get(['id', 'name', 'game_id']);
    }
}
