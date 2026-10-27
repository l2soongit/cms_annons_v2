<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Filament\Resources\GameResource;
use App\Filament\Resources\ServerResource;
use App\Jobs\DeliverVoteCallback;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use App\Models\Vote;
use App\Notifications\ServerModerated;
use App\Rating\RatingCalculator;
use App\Servers\ServerModeration;
use App\Servers\ServerSubmission;
use App\Support\PublicHost;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Stage2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, GameSeeder::class]);
        Notification::fake();
    }

    private function server(array $attributes = []): Server
    {
        return Server::create($attributes + [
            'game_id' => Game::where('slug', 'lineage2')->value('id'),
            'user_id' => User::factory()->create()->id,
            'name' => 'Aden',
            'slug' => 'aden-'.uniqid(),
            'host' => '8.8.8.8',
            'port' => 7777,
            'status' => ServerStatus::Published,
            'callback_secret' => str_repeat('s', 40),
            'verify_token' => 'abc',
        ]);
    }

    // --- Голосування ---

    public function test_vote_is_counted_and_repeat_is_rejected(): void
    {
        $server = $this->server();

        $this->post(route('servers.vote.store', $server), ['nickname' => 'Hero'])
            ->assertRedirect(route('servers.show', $server));
        $this->assertSame(1, $server->votes()->where('status', 'counted')->count());

        $this->post(route('servers.vote.store', $server), ['nickname' => 'Hero'])
            ->assertSessionHasErrors('vote');
        $this->assertSame(1, $server->votes()->count());
    }

    public function test_vote_allowed_again_after_interval(): void
    {
        $server = $this->server();
        $this->post(route('servers.vote.store', $server));

        $this->travel(config('graj.voting.interval_hours') + 1)->hours();

        $this->post(route('servers.vote.store', $server))->assertSessionHasNoErrors();
        $this->assertSame(2, $server->votes()->count());
    }

    public function test_votes_over_subnet_limit_are_suspicious(): void
    {
        config(['graj.voting.subnet_limit' => 2]);
        $server = $this->server();

        foreach (['10.1.1.1', '10.1.1.2', '10.1.1.3'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->post(route('servers.vote.store', $server));
        }

        $this->assertSame(2, $server->votes()->where('status', 'counted')->count());
        $this->assertSame(1, $server->votes()->where('status', 'suspicious')->count());
    }

    public function test_cannot_vote_for_unpublished_server(): void
    {
        $server = $this->server(['status' => ServerStatus::Pending]);

        $this->post(route('servers.vote.store', $server))->assertSessionHasErrors('vote');
    }

    public function test_vote_dispatches_signed_callback(): void
    {
        Http::fake(['93.184.216.34/*' => Http::response('ok')]);
        $server = $this->server(['callback_url' => 'http://93.184.216.34/vote']);

        $this->post(route('servers.vote.store', $server), ['nickname' => 'Hero']);

        $vote = $server->votes()->first();
        $this->assertSame('delivered', $vote->fresh()->callback_status);

        Http::assertSent(function ($request) use ($server) {
            return $request['nickname'] === 'Hero'
                && $request->header('X-Graj-Signature')[0] === hash_hmac('sha256', $request->body(), $server->callback_secret);
        });
    }

    public function test_callback_to_private_address_is_refused(): void
    {
        Queue::fake();
        $server = $this->server(['callback_url' => 'http://127.0.0.1/vote']);
        $vote = Vote::create(['server_id' => $server->id, 'ip_hash' => 'x', 'created_at' => now(), 'callback_status' => 'pending']);

        Http::fake();
        (new DeliverVoteCallback($vote->id, '1.2.3.4'))->handle();

        Http::assertNothingSent();
        $this->assertSame('failed', $vote->fresh()->callback_status);
    }

    // --- Рейтинг ---

    public function test_rating_orders_by_votes_and_closes_month(): void
    {
        $a = $this->server(['name' => 'A']);
        $b = $this->server(['name' => 'B']);
        foreach (range(1, 3) as $i) {
            Vote::create(['server_id' => $b->id, 'ip_hash' => "b{$i}", 'created_at' => now()]);
        }
        Vote::create(['server_id' => $a->id, 'ip_hash' => 'a1', 'created_at' => now()]);
        Vote::create(['server_id' => $a->id, 'ip_hash' => 'a2', 'created_at' => now(), 'status' => 'annulled']);

        app(RatingCalculator::class)->recalculate();

        $this->assertSame(1, $b->fresh()->rank);
        $this->assertSame(2, $a->fresh()->rank);
        $this->assertSame(1, $a->fresh()->votes_month);

        $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addMinutes(5));
        app(RatingCalculator::class)->closeMonth();

        $this->assertDatabaseHas('monthly_winners', ['server_id' => $b->id, 'place' => 1, 'votes' => 3]);
        $this->assertSame(0, $b->fresh()->votes_month);
    }

    // --- Кабінет і модерація ---

    public function test_guest_is_redirected_from_cabinet(): void
    {
        $this->get(route('cabinet'))->assertRedirect(route('login'));
    }

    public function test_owner_cannot_manage_foreign_server(): void
    {
        $server = $this->server();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('cabinet.servers.show', $server))->assertForbidden();
        $this->actingAs($server->owner)->get(route('cabinet.servers.show', $server))->assertOk();
    }

    public function test_new_server_goes_to_moderation_and_owner_role_is_assigned(): void
    {
        $user = User::factory()->create();
        $game = Game::where('slug', 'lineage2')->first();

        $server = app(ServerSubmission::class)->create($user, [
            'game_id' => $game->id, 'name' => 'New One', 'host' => '8.8.4.4', 'port' => 7777,
        ], ['chronicle' => 'interlude']);

        $this->assertSame(ServerStatus::Pending, $server->status);
        $this->assertSame('new-one', $server->slug);
        $this->assertTrue($user->hasRole('owner'));
        $this->assertSame(1, $server->fieldValues()->count());
    }

    public function test_key_field_change_of_published_server_waits_for_moderator(): void
    {
        $server = $this->server(['name' => 'Old Name']);

        $needsReview = app(ServerSubmission::class)->update($server, ['name' => 'New Name', 'discord' => 'https://discord.gg/x'], []);

        $server->refresh();
        $this->assertTrue($needsReview);
        $this->assertSame('Old Name', $server->name);
        $this->assertSame('https://discord.gg/x', $server->discord);
        $this->assertSame(['name' => 'New Name'], $server->pending_changes);

        $this->actingAs(User::factory()->create()->assignRole('moderator'));
        app(ServerModeration::class)->approve($server);

        $this->assertSame('New Name', $server->fresh()->name);
        $this->assertNull($server->fresh()->pending_changes);
        $this->assertDatabaseHas('moderation_logs', ['subject_id' => $server->id, 'action' => 'approve_changes']);
    }

    public function test_rejection_stores_reason_and_notifies_owner(): void
    {
        $server = $this->server(['status' => ServerStatus::Pending]);

        app(ServerModeration::class)->reject($server, 'Неповний опис');

        $this->assertSame(ServerStatus::Rejected, $server->fresh()->status);
        $this->assertSame('Неповний опис', $server->fresh()->rejection_reason);
        Notification::assertSentTo($server->owner, ServerModerated::class);
    }

    public function test_admin_panel_requires_staff_role(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create()->assignRole('moderator'))->get('/admin/servers')->assertOk();
    }

    public function test_all_admin_and_cabinet_pages_render(): void
    {
        $server = $this->server();
        $admin = User::factory()->create()->assignRole('admin');

        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get(route('servers.vote', $server))->assertOk();

        foreach (['/admin', '/admin/servers', '/admin/reviews', '/admin/reports', '/admin/votes', '/admin/users', '/admin/games', '/admin/moderation-logs',
            ServerResource::getUrl('edit', ['record' => $server]),
            GameResource::getUrl('edit', ['record' => $server->game])] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        $this->actingAs($server->owner)->get(route('cabinet'))->assertOk()->assertSee('Aden');
        $this->actingAs($server->owner)->get(route('cabinet.servers.create'))->assertOk();
        $this->actingAs($server->owner)->get(route('cabinet.servers.edit', $server))->assertOk();
    }

    // --- Відгуки ---

    public function test_review_with_link_goes_to_premoderation(): void
    {
        $server = $this->server();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('reviews.store', $server), [
            'rating' => 5, 'body' => 'Чудовий сервер, заходьте на https://spam.example',
        ]);
        $this->assertDatabaseHas('reviews', ['server_id' => $server->id, 'status' => 'pending']);

        $this->actingAs($user)->post(route('reviews.store', $server), [
            'rating' => 4, 'body' => 'Стабільний онлайн і адекватна адміністрація.',
        ]);
        $this->assertDatabaseHas('reviews', ['server_id' => $server->id, 'status' => 'published', 'rating' => 4]);
        $this->assertSame(1, $server->reviews()->count());
    }

    public function test_owner_cannot_review_own_server(): void
    {
        $server = $this->server();

        $this->actingAs($server->owner)
            ->post(route('reviews.store', $server), ['rating' => 5, 'body' => 'Найкращий сервер у світі, без сумнівів.'])
            ->assertSessionHasErrors('body');
    }

    // --- API ---

    public function test_api_requires_server_key_and_checks_votes(): void
    {
        $server = $this->server();
        $this->post(route('servers.vote.store', $server), ['nickname' => 'Hero']);

        $this->getJson("/api/v1/servers/{$server->id}/votes/check?nickname=Hero")->assertUnauthorized();

        $this->withToken($server->callback_secret)
            ->getJson("/api/v1/servers/{$server->id}/votes/check?nickname=Hero")
            ->assertOk()->assertJson(['voted' => true]);

        $this->withToken($server->callback_secret)
            ->getJson("/api/v1/servers/{$server->id}/votes/check?nickname=Nobody")
            ->assertJson(['voted' => false]);
    }

    public function test_api_push_online(): void
    {
        $server = $this->server();

        $this->withToken($server->callback_secret)
            ->postJson("/api/v1/servers/{$server->id}/online", ['online' => true, 'players' => 120, 'max_players' => 500])
            ->assertOk();

        $this->assertTrue($server->fresh()->is_online);
        $this->assertSame(120, $server->fresh()->players);
    }

    // --- Безпека ---

    public function test_private_addresses_are_not_public(): void
    {
        foreach (['127.0.0.1', '10.0.0.5', '192.168.1.1', '169.254.169.254', 'localhost', '::1'] as $host) {
            $this->assertFalse(PublicHost::isPublic($host), $host);
        }
        $this->assertTrue(PublicHost::isPublic('8.8.8.8'));
        $this->assertFalse(PublicHost::isPublicUrl('file:///etc/passwd'));
    }

    public function test_registration_assigns_player_role(): void
    {
        $this->post('/register', [
            'name' => 'NewPlayer',
            'email' => 'new@example.com',
            'password' => 'secret-password-1',
            'password_confirmation' => 'secret-password-1',
            'rules' => '1',
        ])->assertRedirect();

        $this->assertTrue(User::where('email', 'new@example.com')->first()->hasRole('player'));
    }
}
