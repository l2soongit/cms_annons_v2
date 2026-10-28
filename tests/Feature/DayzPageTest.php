<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use App\Monitoring\Drivers\SourceDriver;
use App\Monitoring\QueryResult;
use App\Monitoring\ServerMonitor;
use App\Monitoring\SteamKeywords;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Сторінка DayZ за демо design/game/dayz.html: топографічні карти й перемикачі як фільтри, ігровий час, черга й стан сервера.
 */
class DayzPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GameSeeder::class);
    }

    private function makeServer(string $name, array $attributes = [], array $fields = []): Server
    {
        $game = Game::where('slug', 'dayz')->first();
        $server = Server::create($attributes + [
            'game_id' => $game->id,
            'user_id' => User::factory()->create()->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'host' => str($name)->slug().'.example.net',
            'port' => 2302,
            'status' => ServerStatus::Published,
            'is_online' => true,
            'players' => 40,
            'max_players' => 60,
            'votes_month' => 10,
            'score' => 10,
            'uptime' => 99.5,
        ]);

        foreach ($fields as $key => $value) {
            $server->fieldValues()->create(['game_field_id' => $game->fields()->where('key', $key)->value('id'), 'value' => $value]);
        }

        return $server;
    }

    public function test_page_shows_journal_with_map_world_time_queue_and_condition(): void
    {
        $this->makeServer('Chernarus UA', ['game_time' => '21:40', 'time_accel' => 4, 'queue' => 7, 'checked_at' => now()], ['map' => 'chernarus', 'style' => 'pve', 'perspective' => '1pp', 'mods' => '1']);
        $this->makeServer('Quiet Zone', ['is_online' => false, 'players' => 0, 'uptime' => 80], ['map' => 'livonia']);

        $this->get('/dayz')->assertOk()
            ->assertSee('game-dayz', false)
            // Фільтри — топографічні карти мап і перемикачі
            ->assertSee('class="map"', false)->assertSee('Chernarus')->assertSee('1PP')->assertSee('З модами')
            // Рядок: ігровий час з прискоренням, черга, стан за аптаймом
            ->assertSee('data-time="21:40"', false)->assertSee('×4')->assertSee('черга 7')
            ->assertSee('Бездоганний')->assertSee('Зруйнований')
            ->assertSee('chernarus-ua.example.net:2302');
    }

    public function test_filters_sort_and_free_slots_without_queue(): void
    {
        $this->makeServer('Full Queue', ['queue' => 5, 'players' => 59, 'uptime' => 92], ['map' => 'chernarus', 'perspective' => '1pp']);
        $this->makeServer('Open Woods', ['queue' => null, 'players' => 20, 'uptime' => 99.9], ['map' => 'livonia', 'perspective' => '3pp']);
        $this->makeServer('Third Person', ['players' => 10, 'uptime' => 95], ['map' => 'chernarus', 'perspective' => '3pp']);

        $list = fn (string $query) => $this->get('/dayz?'.$query, ['X-Partial' => 'list'])->assertOk()->getContent();

        $free = $list('free=1');
        $this->assertStringNotContainsString('Full Queue', $free);
        $this->assertStringContainsString('Open Woods', $free);

        $first = $list('perspective=1pp');
        $this->assertStringContainsString('Full Queue', $first);
        $this->assertStringNotContainsString('Open Woods', $first);

        // «Стан»: найстабільніші — першими
        $byUptime = $list('sort=uptime');
        $this->assertLessThan(strpos($byUptime, 'Third Person'), strpos($byUptime, 'Open Woods'));
        $this->assertLessThan(strpos($byUptime, 'Full Queue'), strpos($byUptime, 'Third Person'));
    }

    public function test_steam_keywords_give_queue_time_and_acceleration(): void
    {
        $dayz = SteamKeywords::parse('battleye,no3rd,external,privHive,shard,lqs3,etm4.000000,entm8.000000,mod,14:20');
        $this->assertSame([3, '14:20', 4.0, null, null], [$dayz['queue'], $dayz['time'], $dayz['accel'], $dayz['wiped'], $dayz['modded']]);
        $this->assertSame(['queue' => null, 'time' => null, 'accel' => null, 'wiped' => null, 'modded' => null], SteamKeywords::parse('secure,cs2'));

        // A2S_INFO з розширеним блоком: версія, прапорці 0x80 (порт) і 0x20 (ключові слова)
        $parse = new ReflectionMethod(SourceDriver::class, 'parseSource');
        $packet = "\xFF\xFF\xFF\xFFI\x11"."Chernarus UA\0chernarusplus\0dayz\0DayZ\0"."\xDA\x02"."\x28\x3C\x00dw\x00\x01"."1.26.0\0"."\xA0"."\xFE\x08"."lqs2,etm6.000000,07:05\0";
        $result = $parse->invoke(new SourceDriver, $packet, 20);
        $this->assertSame([40, 60, 'lqs2,etm6.000000,07:05'], [$result->players, $result->maxPlayers, $result->keywords]);

        $server = $this->makeServer('Chernarus UA');
        app(ServerMonitor::class)->record($server, $result);
        $this->assertSame([2, '07:05', 6.0], [$server->queue, $server->game_time, $server->time_accel]);

        // Офлайн — ні черги, ні часу
        app(ServerMonitor::class)->record($server, QueryResult::offline());
        $this->assertSame([null, null], [$server->fresh()->queue, $server->fresh()->game_time]);
    }
}
