<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GameSeeder::class);
    }

    private function makeServer(array $attributes = []): Server
    {
        return Server::create($attributes + [
            'game_id' => Game::where('slug', 'lineage2')->value('id'),
            'user_id' => User::factory()->create()->id,
            'name' => 'Test Server',
            'slug' => 'test-server',
            'host' => 'play.example.net',
            'status' => ServerStatus::Published,
            'votes_month' => 10,
            'score' => 10,
        ]);
    }

    public function test_home_lists_games(): void
    {
        $this->makeServer();

        $this->get('/')
            ->assertOk()
            ->assertSee('Lineage 2')
            ->assertSee('Test Server');
    }

    public function test_game_page_ranks_servers_by_score(): void
    {
        $this->makeServer(['name' => 'Second', 'slug' => 'second', 'score' => 5]);
        $this->makeServer(['name' => 'First', 'slug' => 'first', 'score' => 50]);

        $this->get('/lineage2')
            ->assertOk()
            ->assertSeeInOrder(['First', 'Second']);
    }

    public function test_game_page_filters_by_field(): void
    {
        $game = Game::where('slug', 'lineage2')->first();
        $chronicle = $game->fields()->where('key', 'chronicle')->first();

        $this->makeServer(['name' => 'Interlude One', 'slug' => 'interlude-one'])
            ->fieldValues()->create(['game_field_id' => $chronicle->id, 'value' => 'interlude']);
        $this->makeServer(['name' => 'Classic One', 'slug' => 'classic-one'])
            ->fieldValues()->create(['game_field_id' => $chronicle->id, 'value' => 'classic']);

        $this->get('/lineage2/interlude')
            ->assertOk()
            ->assertSee('Interlude One')
            ->assertDontSee('Classic One');
    }

    public function test_unpublished_server_is_not_found(): void
    {
        $this->makeServer(['status' => ServerStatus::Pending]);

        $this->get('/server/test-server')->assertNotFound();
    }

    public function test_server_page_shows_address(): void
    {
        $this->makeServer(['port' => 7777]);

        $this->get('/server/test-server')
            ->assertOk()
            ->assertSee('play.example.net:7777');
    }

    public function test_unknown_game_is_not_found(): void
    {
        $this->get('/no-such-game')->assertNotFound();
    }
}
