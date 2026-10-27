<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use App\Monitoring\Drivers\SourceDriver;
use App\Monitoring\QueryResult;
use App\Monitoring\ServerMonitor;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class CounterStrikePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GameSeeder::class);
    }

    private function makeServer(string $name, array $attributes = [], array $fields = []): Server
    {
        $game = Game::where('slug', 'counter-strike')->first();
        $server = Server::create($attributes + [
            'game_id' => $game->id,
            'user_id' => User::factory()->create()->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'host' => str($name)->slug().'.example.net',
            'port' => 27015,
            'status' => ServerStatus::Published,
            'is_online' => true,
            'players' => 10,
            'max_players' => 32,
            'votes_month' => 10,
            'score' => 10,
        ]);

        foreach ($fields as $key => $value) {
            $server->fieldValues()->create(['game_field_id' => $game->fields()->where('key', $key)->value('id'), 'value' => $value]);
        }

        return $server;
    }

    public function test_page_shows_scoreboard_with_map_ping_and_connect_command(): void
    {
        $this->makeServer('Kyiv Retake', ['current_map' => 'de_mirage', 'ping_ms' => 14, 'vac' => true], ['version' => 'cs2', 'mode' => 'retake', 'tickrate' => '128']);

        $this->get('/counter-strike')
            ->assertOk()
            ->assertSee('game-cs', false)
            ->assertSee('Меню закупівлі')
            ->assertSee('Mirage')
            ->assertSee('de_mirage')
            ->assertSee('14 мс')
            ->assertSee('128 tick')
            ->assertSee('data-connect="connect kyiv-retake.example.net:27015"', false)
            ->assertSee('<span class="w" style="--i:0">Counter-</span><span class="w accent" style="--i:1">Strike</span>', false);
    }

    public function test_buy_menu_filters_combine(): void
    {
        $this->makeServer('Fast Vac', ['ping_ms' => 20, 'vac' => true], ['tickrate' => '128']);
        $this->makeServer('Slow Vac', ['ping_ms' => 120, 'vac' => true], ['tickrate' => '128']);
        $this->makeServer('No Vac', ['ping_ms' => 10, 'vac' => false], ['tickrate' => '64']);

        $this->get('/counter-strike?vac=1&lowping=1')
            ->assertOk()
            ->assertSee('Fast Vac')
            ->assertDontSee('Slow Vac')
            ->assertDontSee('No Vac');

        $this->get('/counter-strike?tick128=1', ['X-Partial' => 'list'])
            ->assertOk()
            ->assertSee('Fast Vac')
            ->assertSee('Slow Vac')
            ->assertDontSee('No Vac');
    }

    public function test_search_finds_server_by_address(): void
    {
        $this->makeServer('Public Dust', ['host' => '185.10.20.30']);
        $this->makeServer('Other Server');

        $this->get('/counter-strike?q=185.10.20')
            ->assertOk()
            ->assertSee('Public Dust')
            ->assertDontSee('Other Server');
    }

    public function test_source_reply_gives_map_and_vac(): void
    {
        $parse = new ReflectionMethod(SourceDriver::class, 'parseSource');
        $packet = "\xFF\xFF\xFF\xFFI\x11"."Kyiv Retake\0de_mirage\0csgo\0Counter-Strike 2\0"."\xDA\x02"."\x12\x14\x00d\x6C\x00\x01";

        $result = $parse->invoke(new SourceDriver, $packet, 15);

        $this->assertSame(['de_mirage', true, 18, 20, 15], [$result->map, $result->vac, $result->players, $result->maxPlayers, $result->pingMs]);

        $gold = new ReflectionMethod(SourceDriver::class, 'parseGoldSource');
        $packet = "\xFF\xFF\xFF\xFFm"."1.2.3.4:27015\0Oldschool\0de_dust2\0cstrike\0Counter-Strike\0"."\x1C\x20\x2Fdl\x00\x00\x00\x00";

        $result = $gold->invoke(new SourceDriver, $packet, 30);

        $this->assertSame(['de_dust2', false, 28, 32], [$result->map, $result->vac, $result->players, $result->maxPlayers]);
    }

    public function test_monitor_keeps_last_map_while_offline(): void
    {
        $server = $this->makeServer('Kyiv Retake');
        $monitor = app(ServerMonitor::class);

        $monitor->record($server, new QueryResult(true, 18, 20, null, 15, 'de_mirage', true));
        $this->assertSame(['de_mirage', 15, true], [$server->current_map, $server->ping_ms, $server->vac]);

        $monitor->record($server, QueryResult::offline());
        $this->assertSame(['de_mirage', null, true], [$server->fresh()->current_map, $server->fresh()->ping_ms, $server->fresh()->vac]);
    }
}
