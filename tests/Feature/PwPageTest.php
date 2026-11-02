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
 * Сторінка Perfect World за демо design/game/pw-a.html «Резонанс душі»: картка-банер з артом, дашборд як у L2,
 * фільтри рядками (версія, рейти, тип), рядки серверів з теґами й шкалою онлайну, популярні версії.
 */
class PwPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_shows_banner_dashboard_and_rows(): void
    {
        $this->seed(GameSeeder::class);
        $game = Game::where('slug', 'perfect-world')->firstOrFail();
        $server = Server::create([
            'game_id' => $game->id, 'user_id' => User::factory()->create()->id, 'name' => 'Atlas Legacy', 'slug' => 'atlas',
            'host' => 'atlas.example.net', 'port' => 29000, 'status' => ServerStatus::Published,
            'is_online' => true, 'players' => 2410, 'max_players' => 3000, 'votes_month' => 46, 'score' => 46,
        ]);
        foreach (['version' => '1.5.5', 'rates' => 'x5', 'type' => 'PvP'] as $key => $label) {
            $field = $game->fields()->where('key', $key)->firstOrFail();
            $server->fieldValues()->create(['game_field_id' => $field->id, 'value' => collect($field->options)->firstWhere('label', $label)['value']]);
        }

        $this->get('/perfect-world')->assertOk()
            ->assertSee('game-pw', false)
            ->assertSee('images/games/pw/resonance.webp', false)
            ->assertSee('<small>Perfect</small>World', false)
            ->assertSee('id="g-online"', false)->assertSee('class="frow"', false)->assertSee('aside class="side"', false)
            ->assertSee('Atlas Legacy')->assertSee('<span class="tag ver">1.5.5</span>', false)
            ->assertSee('class="chron-bars"', false);
    }
}
