<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use App\Notifications\ServerModerated;
use App\Notifications\UserWarned;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Сповіщення на сайті (дзвіночок і список) і Discord-вебхук власника (ТЗ 3.8, 7.4).
 */
class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, GameSeeder::class]);
    }

    private function server(User $owner): Server
    {
        return Server::create([
            'game_id' => Game::where('slug', 'lineage2')->value('id'),
            'user_id' => $owner->id,
            'name' => 'Bell Server',
            'slug' => 'bell-server',
            'host' => 'bell.example.net',
            'status' => ServerStatus::Published,
        ]);
    }

    public function test_bell_counts_unread_and_inbox_opens_target(): void
    {
        $owner = User::factory()->create();
        $server = $this->server($owner);
        $owner->notify(new ServerModerated($server, 'approved'));
        $owner->notify(new UserWarned('Спам'));

        $this->actingAs($owner)->get(route('home'))->assertOk()->assertSee('class="notif-bell"', false)->assertSee('2 непрочитані');
        $this->actingAs($owner)->get(route('games.show', 'lineage2'))->assertOk()->assertSee('notif-bell', false);

        $this->actingAs($owner)->get(route('cabinet.inbox'))->assertOk()->assertSee('Bell Server')->assertSee('Спам');

        $note = $owner->notifications()->where('type', ServerModerated::class)->first();
        $this->actingAs($owner)->get(route('cabinet.inbox.open', $note->id))->assertRedirect(route('cabinet.servers.show', $server));
        $this->assertNotNull($note->fresh()->read_at);

        $this->actingAs($owner)->post(route('cabinet.inbox.read-all'))->assertRedirect();
        $this->assertSame(0, $owner->unreadNotifications()->count());
    }

    public function test_inbox_never_redirects_outside_the_site(): void
    {
        $user = User::factory()->create();
        $note = $user->notifications()->create(['id' => (string) str()->uuid(), 'type' => 'X', 'data' => ['text' => 'Hi', 'url' => 'https://evil.example.com']]);

        $this->actingAs($user)->get(route('cabinet.inbox.open', $note->id))->assertRedirect(route('cabinet.inbox'));
        $this->actingAs(User::factory()->create())->get(route('cabinet.inbox.open', $note->id))->assertNotFound();
    }

    public function test_discord_webhook_is_tested_saved_encrypted_and_used(): void
    {
        Http::fake(['discord.com/*' => Http::response(null, 204)]);
        $owner = User::factory()->create();
        $hook = 'https://discord.com/api/webhooks/123456/abc-DEF_789';

        $this->actingAs($owner)->put(route('cabinet.notifications.discord'), ['discord_webhook' => 'https://evil.example.com/hook'])
            ->assertSessionHasErrors('discord_webhook');

        $this->actingAs($owner)->put(route('cabinet.notifications.discord'), ['discord_webhook' => $hook])->assertSessionHasNoErrors();
        $this->assertSame($hook, $owner->fresh()->discord_webhook);
        $this->assertNotSame($hook, DB::table('users')->where('id', $owner->id)->value('discord_webhook'));
        Http::assertSent(fn ($r) => $r->url() === $hook && str_contains($r['content'], 'підключено'));

        // Подія з увімкненим Discord іде у вебхук
        $this->actingAs($owner)->put(route('cabinet.notifications.update'), ['prefs' => ['moderation' => ['discord']]])->assertSessionHasNoErrors();
        $owner->refresh()->notify(new ServerModerated($this->server($owner), 'approved'));
        Http::assertSent(fn ($r) => $r->url() === $hook && str_contains($r['content'], 'Bell Server') && $r['allowed_mentions'] === ['parse' => []]);
    }
}
