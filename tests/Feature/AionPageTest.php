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
 * Сторінка Aion за демо design/game/aion-a.html: «Світло і Тьма» — банер двох рас, дашборд (сервери, онлайн, голоси),
 * баланс рас з API (elyos / asmodians → alliance / horde), вкладки версій, рядки з часткою рас і адресою.
 */
class AionPageTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GameSeeder::class);
        $this->game = Game::where('slug', 'aion')->firstOrFail();
    }

    private function server(string $name, array $attributes = [], array $fields = []): Server
    {
        $server = Server::create($attributes + [
            'game_id' => $this->game->id,
            'user_id' => User::factory()->create()->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'host' => str($name)->slug().'.example.net',
            'port' => 2106,
            'status' => ServerStatus::Published,
            'is_online' => true,
            'players' => 1000,
            'max_players' => 5000,
            'votes_month' => 10,
            'score' => 10,
        ]);

        foreach ($fields as $key => $label) {
            $field = $this->game->fields()->where('key', $key)->firstOrFail();
            $server->fieldValues()->create(['game_field_id' => $field->id, 'value' => collect($field->options)->firstWhere('label', $label)['value']]);
        }

        return $server;
    }

    public function test_page_shows_races_dashboard_versions_and_rows(): void
    {
        $this->server('Atreia Legacy', ['alliance' => 460, 'horde' => 540, 'votes_month' => 50, 'score' => 50, 'port' => 7777], ['version' => '4.8', 'rates' => 'x5', 'type' => 'PvP']);
        $this->server('Kaisinel Classic', ['alliance' => 520, 'horde' => 480], ['version' => 'Classic', 'rates' => 'x1']);
        $this->server('Silent Realm', [], ['version' => '2.7']);

        $this->get('/aion')->assertOk()
            ->assertSee('game-aion', false)
            ->assertSee('images/games/aion/faces.webp', false)
            // Банер: Асмодіанці (horde) і Елійці (alliance) лише з серверів, що передають баланс
            ->assertSee('data-f="asmo"', false)->assertSee('data-count="1020"', false)->assertSee('data-count="980"', false)
            ->assertSee('--split:49%', false)
            // Дашборд, як на сторінках інших ігор
            ->assertSee('id="g-online"', false)->assertSee('id="g-votes"', false)
            // Рядки: нестандартний порт — з портом, стандартний — без; частка Елійців; без балансу — підпис
            ->assertSee('atreia-legacy.example.net:7777')
            ->assertSee('<code>kaisinel-classic.example.net</code>', false)
            ->assertSee('data-ely="46"', false)
            ->assertSee('Баланс рас не передається');
    }

    public function test_api_accepts_race_online(): void
    {
        $server = $this->server('Api Server', ['callback_secret' => str_repeat('s', 40)]);

        $this->withToken($server->callback_secret)
            ->postJson("/api/v1/servers/{$server->id}/online", ['online' => true, 'players' => 3000, 'elyos' => 1410, 'asmodians' => 1590])
            ->assertOk();

        $this->assertSame([1410, 1590], [$server->fresh()->alliance, $server->fresh()->horde]);
    }

    public function test_balanced_races_filter(): void
    {
        $this->server('Even Server', ['alliance' => 450, 'horde' => 550]);
        $this->server('Asmo Server', ['alliance' => 200, 'horde' => 800]);

        $this->get('/aion?balanced=1')->assertOk()
            ->assertSee('Even Server')->assertDontSee('Asmo Server');
    }
}
