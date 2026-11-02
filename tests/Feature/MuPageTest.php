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
 * Сторінка MU Online за демо design/game/mu-b.html «Золотий лицар»: картка-банер з артом, дашборд як у L2,
 * фільтри рядками (сезон, рейти, тип), рядки серверів з теґами й шкалою онлайну, популярні сезони.
 */
class MuPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_shows_banner_dashboard_and_rows(): void
    {
        $this->seed(GameSeeder::class);
        $game = Game::where('slug', 'mu-online')->firstOrFail();
        $server = Server::create([
            'game_id' => $game->id, 'user_id' => User::factory()->create()->id, 'name' => 'Lorencia Legacy', 'slug' => 'lorencia',
            'host' => 'lorencia.example.net', 'port' => 44405, 'status' => ServerStatus::Published,
            'is_online' => true, 'players' => 2410, 'max_players' => 3000, 'votes_month' => 46, 'score' => 46,
        ]);
        foreach (['season' => 'Season 6', 'rates' => 'x100', 'type' => 'Ресети'] as $key => $label) {
            $field = $game->fields()->where('key', $key)->firstOrFail();
            $server->fieldValues()->create(['game_field_id' => $field->id, 'value' => collect($field->options)->firstWhere('label', $label)['value']]);
        }

        $this->get('/mu-online')->assertOk()
            ->assertSee('game-mu', false)
            ->assertSee('images/games/mu/knight.webp', false)
            ->assertSee('<small>MU</small>Online', false)
            ->assertSee('id="g-online"', false)->assertSee('class="frow"', false)->assertSee('aside class="side"', false)
            ->assertSee('Lorencia Legacy')->assertSee('<span class="tag ver">Season 6</span>', false)
            ->assertSee('class="chron-bars"', false);
    }
}
