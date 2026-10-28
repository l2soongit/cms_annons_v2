<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use App\Models\Vote;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameThemePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GameSeeder::class);
    }

    private function makeServer(string $name, array $attributes = [], ?string $chronicle = null): Server
    {
        $game = Game::where('slug', $attributes['game'] ?? 'lineage2')->first();
        unset($attributes['game']);

        $server = Server::create($attributes + [
            'game_id' => $game->id,
            'user_id' => User::factory()->create()->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'host' => 'play.example.net',
            'status' => ServerStatus::Published,
            'is_online' => true,
            'players' => 100,
            'max_players' => 500,
            'votes_month' => 10,
            'score' => 10,
        ]);

        if ($chronicle) {
            $server->fieldValues()->create(['game_field_id' => $game->fields()->where('key', 'chronicle')->value('id'), 'value' => $chronicle]);
        }

        return $server;
    }

    public function test_lineage2_page_uses_its_own_design_with_admin_texts(): void
    {
        $this->makeServer('Aden Test', [], 'interlude');

        $this->get('/lineage2')
            ->assertOk()
            ->assertSee('class="ghero"', false)
            ->assertSee('game-l2', false)
            ->assertSee('Фентезі MMORPG')
            ->assertSee('<span class="w gold" style="--i:1">II</span>', false)
            ->assertSee('Aden Test')
            ->assertSee('?chronicle=interlude', false);
    }

    public function test_filters_counts_and_partial_list(): void
    {
        $this->makeServer('Interlude One', [], 'interlude');
        $this->makeServer('Classic One', [], 'classic');

        $this->get('/lineage2?chronicle=interlude')
            ->assertOk()
            ->assertSee('Interlude One')
            ->assertDontSee('Classic One')
            ->assertSee('Знайдено')
            ->assertSee('Скинути фільтри');

        // Оновлення без перезавантаження отримує лише список, без шапки й банера
        $this->get('/lineage2?q=Classic', ['X-Partial' => 'list'])
            ->assertOk()
            ->assertSee('Classic One')
            ->assertDontSee('Interlude One')
            ->assertDontSee('class="ghero"', false);
    }

    public function test_game_without_own_design_keeps_generic_page(): void
    {
        $this->makeServer('Block Test', ['game' => 'dayz']);

        $this->get('/dayz')
            ->assertOk()
            ->assertSee('Block Test')
            ->assertDontSee('class="ghero"', false);
    }

    public function test_gta_page_shows_cards_with_connect_command(): void
    {
        $game = Game::where('slug', 'gta')->first();
        $server = $this->makeServer('Kyiv Test', ['game' => 'gta', 'port' => 30120]);
        $server->fieldValues()->create(['game_field_id' => $game->fields()->where('key', 'platform')->value('id'), 'value' => 'fivem']);

        $this->get('/gta')
            ->assertOk()
            ->assertSee('game-gta', false)
            ->assertSee('class="srv gcard lead"', false)
            ->assertSee('data-connect="connect play.example.net:30120"', false)
            ->assertSee('data-v="FiveM"', false)
            ->assertSee('Рольові й мультиплеєрні сервери');
    }

    public function test_live_feed_can_be_limited_to_one_game(): void
    {
        $l2 = $this->makeServer('Aden Test');
        $gta = $this->makeServer('Kyiv Test', ['game' => 'gta', 'players' => 50]);
        Vote::create(['server_id' => $l2->id, 'ip_hash' => 'a', 'status' => 'counted', 'created_at' => now()]);
        Vote::create(['server_id' => $gta->id, 'ip_hash' => 'b', 'status' => 'counted', 'created_at' => now()]);

        $this->getJson(route('home.live', ['game' => 'lineage2']))
            ->assertOk()
            ->assertJsonCount(1, 'votes')
            ->assertJsonPath('votes.0.server', 'Aden Test')
            ->assertJsonPath('online', 100);
    }
}
