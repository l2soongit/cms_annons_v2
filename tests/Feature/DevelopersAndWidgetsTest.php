<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Документація API (/developers, OpenAPI) і віджети для сайтів серверів (ТЗ 7.2–7.3).
 */
class DevelopersAndWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private function server(array $attributes = []): Server
    {
        $this->seed(GameSeeder::class);

        return Server::create($attributes + [
            'game_id' => Game::where('slug', 'lineage2')->value('id'),
            'user_id' => User::factory()->create()->id,
            'name' => 'Aden <Legacy>',
            'slug' => 'aden-legacy',
            'host' => 'aden.example.net',
            'status' => ServerStatus::Published,
            'is_online' => true,
            'players' => 312,
            'max_players' => 1000,
            'rank' => 3,
        ]);
    }

    public function test_developers_page_documents_api_and_links_openapi(): void
    {
        $server = $this->server();

        $this->get('/developers')->assertOk()
            ->assertSee('X-Graj-Signature')
            ->assertSee('hash_equals', false)
            ->assertSee('/servers/{id}/votes/check')
            ->assertSee('/games/{slug}/top')
            ->assertSee('Топ-100 серверів гри')
            ->assertSee(route('widgets.status', ['server' => $server->id, 'size' => '468x60']), false)
            ->assertSee(route('developers.openapi'));

        $spec = $this->getJson('/developers/openapi.json')->assertOk()->assertHeader('Access-Control-Allow-Origin', '*')->json();
        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertSame(['/servers/{id}', '/games/{slug}/top', '/servers/{id}/votes', '/servers/{id}/votes/check', '/servers/{id}/online'], array_keys($spec['paths']));
        $this->assertArrayHasKey('voteCallback', $spec['webhooks']);
    }

    public function test_footer_links_to_developers(): void
    {
        $this->server();

        $this->get('/search')->assertOk()->assertSee(route('developers'));
    }

    public function test_widgets_are_cached_svg_with_escaped_live_data(): void
    {
        $server = $this->server();

        $status = $this->get(route('widgets.status', ['server' => $server->id, 'size' => '350x80']))->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('max-age=300', $status->headers->get('Cache-Control'));
        $svg = $status->getContent();
        $this->assertStringContainsString('width="350" height="80"', $svg);
        $this->assertStringContainsString('Aden &lt;Legacy&gt;', $svg);
        $this->assertStringContainsString('312 / 1000 онлайн', $svg);
        $this->assertStringContainsString('#3', $svg);
        $this->assertNotFalse(simplexml_load_string($svg), 'SVG має бути коректним XML');

        $vote = $this->get(route('widgets.vote', ['server' => $server->id, 'size' => 'bogus']))->assertOk()->getContent();
        $this->assertStringContainsString('width="120" height="40"', $vote);
        $this->assertStringContainsString('Голосувати', $vote);
        $this->assertNotFalse(simplexml_load_string($vote));
        $this->assertNotFalse(simplexml_load_string($this->get(route('widgets.status', ['server' => $server->id, 'size' => '88x31']))->getContent()));
    }

    public function test_widget_of_blocked_server_shows_unavailable_and_owner_gets_embed_codes(): void
    {
        $server = $this->server(['status' => ServerStatus::Blocked]);

        $this->get(route('widgets.status', ['server' => $server->id]))->assertOk()->assertSee('Недоступний');

        $server->update(['status' => ServerStatus::Published]);
        $this->actingAs($server->owner)->get(route('cabinet.servers.show', $server))->assertOk()
            ->assertSee('Віджети для сайту')
            ->assertSee('[url='.route('servers.vote', $server).'][img]', false);
    }
}
