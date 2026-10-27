<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\ServerInvitation;
use App\Models\User;
use App\Models\Vote;
use App\Monitoring\QueryResult;
use App\Monitoring\ServerMonitor;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\CoOwnerInvited;
use App\Notifications\NewReview;
use App\Notifications\ServerWentOffline;
use App\Support\HtmlSanitizer;
use App\Support\ImageOptimizer;
use App\Support\Settings;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Tests\TestCase;

class Stage2CompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, GameSeeder::class]);
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

    // --- Безпека й профіль ---

    public function test_staff_without_2fa_is_sent_to_security_page(): void
    {
        config(['graj.security.require_staff_2fa' => true]);
        $moderator = User::factory()->create()->assignRole('moderator');

        $this->actingAs($moderator)->get('/admin')->assertRedirect(route('cabinet.security'));

        $moderator->forceFill(['two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($moderator)->get('/admin')->assertOk();
    }

    public function test_profile_pages_render_and_update(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('cabinet.profile'))->assertOk();
        $this->actingAs($user)->get(route('cabinet.notifications'))->assertOk();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get(route('cabinet.security'))->assertOk();

        $this->actingAs($user)->put('/user/profile-information', ['name' => 'Renamed', 'email' => $user->email, 'locale' => 'en'])
            ->assertSessionHasNoErrors();
        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_account_deletion_keeps_votes_anonymous(): void
    {
        $server = $this->server();
        $voter = User::factory()->create();
        Vote::create(['server_id' => $server->id, 'user_id' => $voter->id, 'ip_hash' => 'x', 'created_at' => now()]);

        $this->actingAs($voter)->delete(route('cabinet.profile.destroy'), ['password' => 'password'])->assertRedirect(route('home'));

        $this->assertModelMissing($voter);
        $this->assertDatabaseHas('votes', ['server_id' => $server->id, 'user_id' => null]);
    }

    public function test_social_login_creates_and_links_account(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);

        $social = (new SocialUser)->map(['id' => 'g-123', 'email' => 'Player@Example.com', 'name' => 'Player One', 'nickname' => null]);
        Socialite::shouldReceive('driver->user')->andReturn($social);

        $this->get(route('social.callback', 'google'))->assertRedirect(config('fortify.home'));

        $user = User::where('email', 'player@example.com')->first();
        $this->assertSame('g-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('player'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_social_login_unavailable_without_keys(): void
    {
        $this->get(route('social.redirect', 'discord'))->assertNotFound();
    }

    // --- Опис і зображення ---

    public function test_description_html_is_sanitized(): void
    {
        $dirty = '<p>Hi<script>alert(1)</script> <a href="javascript:alert(1)">x</a> <a href="https://ok.example">ok</a></p><img src=x onerror=alert(1)><iframe src="https://evil"></iframe>';
        $clean = HtmlSanitizer::clean($dirty);

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringContainsString('rel="nofollow', $clean);
        $this->assertSame('Hi x ok', HtmlSanitizer::text($clean));
    }

    public function test_uploaded_images_become_webp(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('shot.png', 2400, 1200);

        $path = ImageOptimizer::store($file, 'servers/1', 'screenshot');

        $this->assertStringEndsWith('.webp', $path);
        [$width] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(1920, $width);
    }

    // --- Каталог ---

    public function test_search_finds_servers_by_name_and_escapes_wildcards(): void
    {
        $this->server(['name' => 'Giran Nights', 'slug' => 'giran']);
        $this->server(['name' => 'Oren', 'slug' => 'oren']);

        $this->get('/search?q=giran')->assertOk()->assertSee('Giran Nights')->assertDontSee('Oren');
        $this->get('/search?q=%25%25')->assertOk()->assertDontSee('Giran Nights');
    }

    public function test_game_page_sorts_by_online(): void
    {
        $this->server(['name' => 'Quiet', 'slug' => 'quiet', 'is_online' => true, 'players' => 5, 'score' => 100]);
        $this->server(['name' => 'Busy', 'slug' => 'busy', 'is_online' => true, 'players' => 900, 'score' => 1]);

        $this->get('/lineage2?sort=online')->assertSeeInOrder(['Busy', 'Quiet']);
        $this->get('/lineage2')->assertSeeInOrder(['Quiet', 'Busy']);
    }

    public function test_server_page_supports_chart_periods(): void
    {
        $server = $this->server();

        foreach (['day', 'week', 'month', 'bogus'] as $period) {
            $this->get(route('servers.show', $server).'?period='.$period)->assertOk();
        }
    }

    // --- Співвласники ---

    public function test_invite_accept_and_transfer_ownership(): void
    {
        Notification::fake();
        $server = $this->server();
        $owner = $server->owner;
        $friend = User::factory()->create(['email' => 'friend@example.com']);

        $this->actingAs($owner)->post(route('cabinet.servers.owners.invite', $server), ['email' => 'Friend@example.com'])
            ->assertSessionHasNoErrors();

        $token = null;
        Notification::assertSentOnDemand(CoOwnerInvited::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->actingAs($friend)->get(route('cabinet.invitations.accept', $token))->assertRedirect(route('cabinet.servers.show', $server));
        $this->assertTrue($friend->canManage($server));
        $this->assertSame(0, ServerInvitation::count());

        // Співвласник не може керувати списком
        $this->actingAs($friend)->post(route('cabinet.servers.transfer', $server), ['user_id' => $friend->id, 'password' => 'password'])->assertForbidden();

        $this->actingAs($owner)->post(route('cabinet.servers.transfer', $server), ['user_id' => $friend->id, 'password' => 'password'])
            ->assertSessionHasNoErrors();

        $server->refresh();
        $this->assertSame($friend->id, $server->user_id);
        $this->assertTrue($owner->canManage($server));
    }

    public function test_invitation_for_other_email_is_refused(): void
    {
        $server = $this->server();
        $token = 'plain-token';
        ServerInvitation::create(['server_id' => $server->id, 'invited_by' => $server->user_id, 'email' => 'a@example.com', 'token' => hash('sha256', $token), 'expires_at' => now()->addDay()]);

        $this->actingAs(User::factory()->create(['email' => 'b@example.com']))->get(route('cabinet.invitations.accept', $token))
            ->assertSessionHas('error');
    }

    // --- Сповіщення ---

    public function test_owner_notified_once_when_server_stays_offline(): void
    {
        Notification::fake();
        $server = $this->server(['is_online' => true]);
        $monitor = app(ServerMonitor::class);

        $monitor->record($server, QueryResult::offline());
        Notification::assertNothingSent();

        $this->travel(20)->minutes();
        $monitor->record($server->fresh(), QueryResult::offline());
        $monitor->record($server->fresh(), QueryResult::offline());
        Notification::assertSentToTimes($server->owner, ServerWentOffline::class, 1);

        $monitor->record($server->fresh(), new QueryResult(true, 10, 100));
        $this->assertNull($server->fresh()->offline_notified_at);
    }

    public function test_new_review_notifies_owner_with_chosen_channels(): void
    {
        Notification::fake();
        $server = $this->server();
        $server->owner->forceFill(['notification_prefs' => ['new_review' => ['telegram']], 'telegram_chat_id' => '42'])->save();

        $this->actingAs(User::factory()->create())->post(route('reviews.store', $server), ['rating' => 5, 'body' => 'Чудовий сервер з адекватною адміністрацією.']);

        Notification::assertSentTo($server->owner, NewReview::class, function ($n, array $channels) {
            return in_array('database', $channels) && in_array(TelegramChannel::class, $channels) && ! in_array('mail', $channels);
        });
    }

    public function test_telegram_webhook_links_chat_by_token(): void
    {
        config(['graj.telegram.webhook_secret' => 'hook', 'graj.telegram.bot_token' => 't', 'graj.telegram.bot_username' => 'grajbot']);
        Http::fake();
        $user = User::factory()->create(['telegram_link_token' => 'link123']);

        $this->postJson('/api/telegram/webhook/wrong', [])->assertNotFound();
        $this->postJson('/api/telegram/webhook/hook', ['message' => ['chat' => ['id' => 777], 'text' => '/start link123']])->assertOk();

        $this->assertSame('777', $user->fresh()->telegram_chat_id);
        $this->assertNull($user->fresh()->telegram_link_token);
    }

    // --- Налаштування й приватність ---

    public function test_admin_settings_override_config(): void
    {
        Settings::save(['voting.interval_hours' => 24, 'reviews.stop_words' => ['казино']]);

        $this->assertSame(24, config('graj.voting.interval_hours'));
        $this->assertSame(['казино'], config('graj.reviews.stop_words'));

        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin)->get('/admin/settings')->assertOk();
        $this->actingAs(User::factory()->create()->assignRole('moderator'))->get('/admin/settings')->assertForbidden();
    }

    public function test_old_vote_identifiers_are_erased(): void
    {
        $server = $this->server();
        $old = Vote::create(['server_id' => $server->id, 'ip_hash' => 'old', 'fingerprint' => 'fp', 'created_at' => now()->subDays(100)]);
        $fresh = Vote::create(['server_id' => $server->id, 'ip_hash' => 'new', 'created_at' => now()]);

        $this->artisan('servers:aggregate')->assertSuccessful();

        $this->assertSame('', $old->fresh()->ip_hash);
        $this->assertNull($old->fresh()->fingerprint);
        $this->assertSame('new', $fresh->fresh()->ip_hash);
    }
}
