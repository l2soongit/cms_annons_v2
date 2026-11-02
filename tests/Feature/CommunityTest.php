<?php

namespace Tests\Feature;

use App\Billing\Billing;
use App\Community\Bonus;
use App\Community\Moderation;
use App\Enums\ServerStatus;
use App\Filament\Resources\ForumTopicResource;
use App\Filament\Resources\ForumTopicResource\Pages\ListForumTopics;
use App\Models\BonusReward;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Game;
use App\Models\ModerationLog;
use App\Models\Server;
use App\Models\Tariff;
use App\Models\User;
use App\Notifications\ForumActivity;
use App\Notifications\ForumModerated;
use App\Support\Permissions;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TariffSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Спільнота: кожна тема проходить модерацію, права на розділи й новини, відповіді й автофільтр,
 * оцінки й бонусні бали з денною межею, магазин балів і оплата послуг балами.
 */
class CommunityTest extends TestCase
{
    use RefreshDatabase;

    private Game $l2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(GameSeeder::class);
        Permissions::sync();
        Notification::fake();
        $this->l2 = Game::where('slug', 'lineage2')->firstOrFail();
    }

    private function user(int $daysOld = 30, string $role = 'player'): User
    {
        $user = User::factory()->create(['created_at' => now()->subDays($daysOld)]);
        $user->assignRole($role);

        return $user;
    }

    private function moderator(): User
    {
        $user = $this->user(30, 'moderator');
        $user->givePermissionTo('community.moderate');

        return $user;
    }

    private function gameCategory(): ForumCategory
    {
        return ForumCategory::where('game_id', $this->l2->id)->firstOrFail();
    }

    private function topic(User $author, string $status = ForumTopic::PUBLISHED, array $extra = []): ForumTopic
    {
        return ForumTopic::create($extra + [
            'category_id' => $this->gameCategory()->id, 'user_id' => $author->id, 'type' => 'discussion',
            'title' => 'Який сервер Interlude обрати?', 'body' => '<p>Шукаю живий сервер з чесним донатом.</p>',
            'status' => $status, 'published_at' => $status === ForumTopic::PUBLISHED ? now() : null, 'last_post_at' => now(),
        ]);
    }

    public function test_every_game_gets_a_forum_section(): void
    {
        $this->assertSame(Game::count(), ForumCategory::whereNotNull('game_id')->count());
        $this->assertNotNull(ForumCategory::where('slug', 'lineage2')->first());
    }

    public function test_new_topic_from_site_waits_for_moderation_and_is_hidden_from_others(): void
    {
        $author = $this->user();

        $this->actingAs($author)->post(route('community.store'), [
            'category_id' => $this->gameCategory()->id,
            'type' => 'question',
            'title' => 'Де найкраще качатися на x10?',
            'body' => 'Підкажіть **локації** для 40–52 рівня на Interlude x10, граю гномом.',
        ])->assertRedirect();

        $topic = ForumTopic::firstOrFail();
        $this->assertSame(ForumTopic::PENDING, $topic->status);
        $this->assertStringContainsString('<strong>локації</strong>', $topic->body);

        // Автор бачить свою тему з позначкою, гість і інші — ні
        $this->actingAs($author)->get(route('community.topic', $topic))->assertOk()->assertSee(__('community.pending_note'));
        $this->actingAs($this->user())->get(route('community.topic', $topic))->assertNotFound();
        auth()->logout();
        $this->get(route('community'))->assertOk()->assertDontSee('Де найкраще качатися');
    }

    public function test_moderator_approval_publishes_awards_points_logs_and_notifies(): void
    {
        $author = $this->user();
        $topic = $this->topic($author, ForumTopic::PENDING);
        $moderator = $this->moderator();
        $this->actingAs($moderator);

        Moderation::approve($topic);

        $topic->refresh();
        $this->assertSame(ForumTopic::PUBLISHED, $topic->status);
        $this->assertSame($moderator->id, $topic->moderated_by);
        $this->assertSame((int) config('graj.community.bonus.topic'), $author->fresh()->bonus_balance);
        $this->assertTrue(ModerationLog::where('action', 'forum_topic_approved')->where('subject_id', $topic->id)->exists());
        Notification::assertSentTo($author, ForumModerated::class, fn ($n) => $n->decision === 'approved');
        $this->get(route('community'))->assertOk()->assertSee($topic->title);

        // Повторне схвалення не нараховує вдруге; приховування знімає бали
        Moderation::approve($topic);
        $this->assertSame((int) config('graj.community.bonus.topic'), $author->fresh()->bonus_balance);
        Moderation::hide($topic, 'Реклама');
        $this->assertSame(0, $author->fresh()->bonus_balance);
        $this->assertSame(ForumTopic::HIDDEN, $topic->fresh()->status);
    }

    public function test_reject_requires_reason_and_author_can_fix_and_resubmit(): void
    {
        $author = $this->user();
        $topic = $this->topic($author, ForumTopic::PENDING);
        $this->actingAs($this->moderator());

        Moderation::reject($topic, 'Заголовок не описує тему');
        Notification::assertSentTo($author, ForumModerated::class, fn ($n) => $n->decision === 'rejected' && $n->reason === 'Заголовок не описує тему');

        $this->actingAs($author)->put(route('community.update', $topic), [
            'category_id' => $topic->category_id, 'type' => 'discussion',
            'title' => 'Який сервер Interlude x10 обрати взимку?', 'body' => 'Шукаю живий сервер з чесним донатом і без вайпів до весни.',
        ])->assertRedirect();
        $this->assertSame(ForumTopic::PENDING, $topic->fresh()->status);
    }

    public function test_editing_approved_topic_sends_it_back_to_review(): void
    {
        $author = $this->user();
        $topic = $this->topic($author);

        $this->actingAs($author)->put(route('community.update', $topic), [
            'category_id' => $topic->category_id, 'type' => 'discussion',
            'title' => 'Заголовок після схвалення змінено', 'body' => 'Тепер тут зовсім інший текст, який теж треба перевірити.',
        ])->assertRedirect();

        $this->assertSame(ForumTopic::PENDING, $topic->fresh()->status);
    }

    public function test_news_section_and_news_type_need_permissions(): void
    {
        $player = $this->user();
        $news = ForumCategory::where('slug', 'news')->firstOrFail();

        $this->actingAs($player)->post(route('community.store'), [
            'category_id' => $news->id, 'type' => 'news', 'title' => 'Фейкова новина сайту', 'body' => 'Гравець не може публікувати новини від імені graj.club.',
        ])->assertSessionHasErrors('type');
        $this->assertSame(0, ForumTopic::count());

        $this->assertFalse($news->allowsPosting($player, 'news'));
        $editor = $this->user(30, 'moderator');
        $editor->givePermissionTo('community.news');
        $this->assertTrue($news->allowsPosting($editor, 'news'));
    }

    public function test_server_news_only_for_own_server(): void
    {
        $owner = $this->user();
        $server = Server::create(['game_id' => $this->l2->id, 'user_id' => $owner->id, 'name' => 'Aden x10', 'slug' => 'aden-x10', 'host' => 'aden.example.net', 'status' => ServerStatus::Published]);
        $stranger = $this->user();
        $data = ['category_id' => $this->gameCategory()->id, 'type' => 'server_news', 'server_id' => $server->id, 'title' => 'Вайп 15 листопада о 19:00', 'body' => 'Повний вайп, новий світ, бонуси за перший тиждень голосування.'];

        $this->actingAs($stranger)->post(route('community.store'), $data)->assertSessionHasErrors();
        $this->actingAs($owner)->post(route('community.store'), $data)->assertRedirect();

        $topic = ForumTopic::where('type', 'server_news')->firstOrFail();
        $this->actingAs($this->moderator());
        Moderation::approve($topic);

        $this->get(route('servers.show', $server))->assertOk()->assertSee('Вайп 15 листопада о 19:00');
    }

    public function test_newbie_reply_with_link_goes_to_review_and_regular_reply_publishes(): void
    {
        $topic = $this->topic($this->user(), ForumTopic::PENDING);
        $this->actingAs($this->moderator());
        Moderation::approve($topic); // автор підписується на свою тему
        $newbie = $this->user();

        $this->actingAs($newbie)->post(route('community.reply', $topic), ['body' => 'Заходьте до нас https://other-top.example/server'])->assertRedirect();
        $post = ForumPost::firstOrFail();
        $this->assertSame('pending', $post->status);
        $this->assertNotNull($post->flag_reason);
        $this->assertSame(0, $topic->fresh()->posts_count);

        $regular = $this->user();
        $regular->forceFill(['forum_posts_count' => 20])->save();
        $this->actingAs($regular)->post(route('community.reply', $topic), ['body' => 'Раджу x10, там найживіше.'])->assertRedirect();
        $this->assertSame(1, $topic->fresh()->posts_count);
        Notification::assertSentTo($topic->user, ForumActivity::class);
    }

    public function test_locked_topic_rejects_replies(): void
    {
        $topic = $this->topic($this->user(), extra: ['is_locked' => true]);
        $this->actingAs($this->user())->post(route('community.reply', $topic), ['body' => 'Можна ще питання?'])->assertForbidden();
    }

    public function test_like_awards_points_once_and_unlike_revokes(): void
    {
        $author = $this->user();
        $topic = $this->topic($author);
        $fan = $this->user(30);

        $this->actingAs($fan)->postJson(route('community.react'), ['target' => 'topic', 'id' => $topic->id, 'value' => 1])
            ->assertOk()->assertJson(['score' => 1, 'mine' => 1]);
        $this->assertSame((int) config('graj.community.bonus.like'), $author->fresh()->bonus_balance);

        $this->actingAs($fan)->postJson(route('community.react'), ['target' => 'topic', 'id' => $topic->id, 'value' => 1])
            ->assertOk()->assertJson(['score' => 0, 'mine' => 0]);
        $this->assertSame(0, $author->fresh()->bonus_balance);

        // Свій допис оцінити не можна; новий акаунт не дає балів
        $this->actingAs($author)->postJson(route('community.react'), ['target' => 'topic', 'id' => $topic->id, 'value' => 1])->assertStatus(422);
        $this->actingAs($this->user(1))->postJson(route('community.react'), ['target' => 'topic', 'id' => $topic->id, 'value' => 1])->assertOk();
        $this->assertSame(0, $author->fresh()->bonus_balance);
    }

    public function test_daily_cap_limits_automatic_points(): void
    {
        config(['graj.community.bonus.daily_cap' => 15]);
        $user = $this->user();

        Bonus::award($user, 'topic', $this->topic($user));
        Bonus::award($user, 'topic', $this->topic($user));
        Bonus::adjust($user, 100, 'Приз конкурсу');

        $this->assertSame(115, $user->fresh()->bonus_balance);
        $this->assertSame('member', Bonus::level($user->fresh())['key']);
    }

    public function test_best_answer_awards_author_of_reply(): void
    {
        $author = $this->user();
        $topic = $this->topic($author, extra: ['type' => 'question']);
        $helper = $this->user();
        $post = $topic->posts()->create(['user_id' => $helper->id, 'body' => '<p>Бери x10.</p>', 'status' => 'published']);

        $this->actingAs($author)->post(route('community.posts.best', $post))->assertRedirect();

        $this->assertSame($post->id, $topic->fresh()->best_post_id);
        $this->assertSame((int) config('graj.community.bonus.best_answer'), $helper->fresh()->bonus_balance);
        $this->actingAs($this->user())->post(route('community.posts.best', $post))->assertForbidden();
    }

    public function test_poll_vote_and_shop_purchase(): void
    {
        $user = $this->user();
        $topic = $this->topic($this->user());
        $poll = $topic->poll()->create(['question' => 'Які рейти?', 'options' => ['x1', 'x10', 'x100']]);

        $this->actingAs($user)->post(route('community.poll', $poll), ['options' => [1]])->assertRedirect();
        $this->assertSame([0 => 0, 1 => 1, 2 => 0], $poll->tally());

        $reward = BonusReward::create(['name' => ['uk' => 'Золотий нік'], 'kind' => 'name_color', 'value' => '#fbbf24', 'cost' => 50, 'duration_days' => 30]);
        $this->actingAs($user)->post(route('cabinet.bonus.buy', $reward))->assertSessionHasErrors('reward');
        Bonus::adjust($user, 80, 'Тест');
        $this->actingAs($user)->post(route('cabinet.bonus.buy', $reward))->assertSessionHasNoErrors();
        $this->assertSame(30, $user->fresh()->bonus_balance);
        $this->assertSame('#fbbf24', $user->fresh()->perks['name_color']['value']);
        $this->actingAs($user)->get(route('cabinet.bonus'))->assertOk();
    }

    public function test_points_pay_part_of_a_service(): void
    {
        $this->seed(TariffSeeder::class);
        config(['graj.community.bonus.point_value' => 0.01, 'graj.community.bonus.max_pay_share' => 50]);
        $owner = User::factory()->create(['balance' => 1000]);
        $server = Server::create(['game_id' => $this->l2->id, 'user_id' => $owner->id, 'name' => 'Giran', 'slug' => 'giran', 'host' => 'giran.example.net', 'status' => ServerStatus::Published]);
        $tariff = Tariff::where('game_id', $this->l2->id)->where('service', 'vip')->firstOrFail();
        Bonus::adjust($owner, 100000, 'Тест');

        $order = app(Billing::class)->purchase($owner, $server, $tariff, points: 100000);

        // Балами — рівно половина ціни, решта — з балансу
        $price = (float) $tariff->price;
        $this->assertEqualsWithDelta($price / 2, (float) $order->amount, 0.01);
        $this->assertSame(100000 - (int) round($price / 2 * 100), $owner->fresh()->bonus_balance);
    }

    public function test_admin_queue_and_permissions(): void
    {
        $pending = $this->topic($this->user(), ForumTopic::PENDING);
        $published = $this->topic($this->user());

        $player = $this->user();
        $this->actingAs($player);
        $this->assertFalse(ForumTopicResource::canViewAny());

        $moderator = $this->moderator();
        $moderator->givePermissionTo('admin.access');
        $this->actingAs($moderator);
        Filament::setCurrentPanel('admin');
        $this->assertTrue(ForumTopicResource::canViewAny());

        Livewire::test(ListForumTopics::class)
            ->assertCanSeeTableRecords([$pending])->assertCanNotSeeTableRecords([$published])
            ->callTableAction('approve', $pending)
            ->set('activeTab', 'published')->assertCanSeeTableRecords([$published, $pending->fresh()]);

        $this->assertSame(ForumTopic::PUBLISHED, $pending->fresh()->status);
    }

    public function test_sitemap_lists_published_topics_only(): void
    {
        $published = $this->topic($this->user());
        $pending = $this->topic($this->user(), ForumTopic::PENDING, ['title' => 'Прихована тема на перевірці']);

        $xml = $this->get(route('seo.sitemap'))->assertOk()->getContent();
        $this->assertStringContainsString(route('community.topic', $published), $xml);
        $this->assertStringNotContainsString(route('community.topic', $pending), $xml);
    }

    public function test_admin_community_pages_open(): void
    {
        $admin = $this->user(30, 'admin');
        $topic = $this->topic($admin);
        $topic->posts()->create(['user_id' => $admin->id, 'body' => '<p>Відповідь</p>', 'status' => 'pending']);
        Bonus::adjust($admin, 10, 'Тест');
        $reward = BonusReward::create(['name' => ['uk' => 'Рамка'], 'kind' => 'frame', 'value' => 'gold', 'cost' => 10]);
        $this->actingAs($admin);

        foreach ([
            '/admin/forum-topics', '/admin/forum-topics/create', '/admin/forum-topics/'.$topic->id.'/edit',
            '/admin/forum-posts', '/admin/forum-categories', '/admin/forum-categories/create',
            '/admin/forum-categories/'.$this->gameCategory()->slug.'/edit',
            '/admin/bonus-transactions', '/admin/bonus-rewards', '/admin/bonus-rewards/'.$reward->id.'/edit',
            '/admin/community-settings', '/admin/reports',
        ] as $url) {
            $this->assertSame(200, $this->get($url)->getStatusCode(), $url);
        }
    }
}
