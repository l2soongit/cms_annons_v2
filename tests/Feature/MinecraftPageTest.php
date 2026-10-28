<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use App\Monitoring\Drivers\MinecraftDriver;
use App\Monitoring\QueryResult;
use App\Monitoring\ServerMonitor;
use App\Support\Motd;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class MinecraftPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GameSeeder::class);
    }

    private function makeServer(string $name, array $attributes = [], array $fields = []): Server
    {
        $game = Game::where('slug', 'minecraft')->first();
        $server = Server::create($attributes + [
            'game_id' => $game->id,
            'user_id' => User::factory()->create()->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'host' => str($name)->slug().'.example.net',
            'port' => 25565,
            'status' => ServerStatus::Published,
            'is_online' => true,
            'players' => 10,
            'max_players' => 100,
            'votes_month' => 10,
            'score' => 10,
        ]);

        foreach ($fields as $key => $value) {
            $server->fieldValues()->create(['game_field_id' => $game->fields()->where('key', $key)->value('id'), 'value' => $value]);
        }

        return $server;
    }

    public function test_page_shows_server_list_with_colored_motd_and_copy_ip(): void
    {
        $this->makeServer('Karpaty SMP', ['motd' => "§aКарпати §l<SMP>\n§7Виживання", 'ping_ms' => 18], ['edition' => 'javabedrock', 'version' => '121', 'mode' => 'vizivannia']);

        $this->get('/minecraft')
            ->assertOk()
            ->assertSee('game-mc', false)
            ->assertSee('Press+Start+2P', false)
            ->assertSee('<span class="w" style="--i:0">Mine</span><span class="w" style="--i:1">craft</span>', false)
            ->assertSee('<span style="color:#55ff55">Карпати </span><span style="color:#55ff55;font-weight:700">&lt;SMP&gt;</span><br><span style="color:#aaaaaa">Виживання</span>', false)
            ->assertSee('data-ip="karpaty-smp.example.net:25565"', false)
            ->assertSee('Java+Bedrock')
            ->assertSee('class="ping p5"', false)
            ->assertSee('Пісочниця · Java і Bedrock');
    }

    public function test_inventory_filters_by_edition_and_version(): void
    {
        $this->makeServer('Java New', [], ['edition' => 'java', 'version' => '121']);
        $this->makeServer('Java Old', [], ['edition' => 'java', 'version' => '18']);
        $this->makeServer('Phone Land', [], ['edition' => 'bedrock', 'version' => '121']);

        $this->get('/minecraft?edition=java&version=121')
            ->assertOk()
            ->assertSee('Java New')
            ->assertDontSee('Java Old')
            ->assertDontSee('Phone Land');

        $this->get('/minecraft?edition=bedrock', ['X-Partial' => 'list'])
            ->assertOk()
            ->assertSee('Phone Land')
            ->assertDontSee('Java New')
            ->assertDontSee('class="mhero"', false);
    }

    public function test_java_status_components_become_color_codes(): void
    {
        $convert = new ReflectionMethod(MinecraftDriver::class, 'chatToLegacy');
        $motd = $convert->invoke(null, ['text' => '', 'extra' => [
            ['text' => 'Karpaty ', 'color' => 'green', 'bold' => true],
            ['text' => 'SMP', 'color' => 'gold', 'extra' => [['text' => '!', 'obfuscated' => true]]],
            "\nplain",
        ]]);

        $this->assertSame("§r§a§lKarpaty §r§6SMP§r§6§k!§r\nplain", $motd);
        $this->assertSame("Karpaty SMP!\nplain", Motd::plain($motd));
    }

    public function test_motd_html_escapes_text_and_keeps_two_lines(): void
    {
        $this->assertSame('<span style="color:#ff5555">&lt;b&gt;</span><br><span class="obf" style="color:#aaaaaa">x</span>', Motd::html("§c<b>\n§kx\nthird line"));
        $this->assertSame('', Motd::html('§a§l'));
    }

    public function test_monitor_keeps_last_motd_while_offline(): void
    {
        $server = $this->makeServer('Karpaty SMP');
        $monitor = app(ServerMonitor::class);

        $monitor->record($server, new QueryResult(true, 5, 100, 'Karpaty', 20, motd: '§aKarpaty'));
        $this->assertSame('§aKarpaty', $server->motd);

        $monitor->record($server, QueryResult::offline());
        $this->assertSame('§aKarpaty', $server->fresh()->motd);
    }
}
