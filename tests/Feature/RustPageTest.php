<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use App\Monitoring\Drivers\SourceDriver;
use App\Monitoring\ServerMonitor;
use App\Monitoring\SteamKeywords;
use App\Support\RustWipes;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Сторінка Rust за демо design/game/rust.html: верстак-фільтри, вайп-календар, цикл вайпу й форс-вайп, дата вайпу з A2S.
 */
class RustPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GameSeeder::class);
    }

    private function makeServer(string $name, array $attributes = [], array $fields = []): Server
    {
        $game = Game::where('slug', 'rust')->first();
        $server = Server::create($attributes + [
            'game_id' => $game->id,
            'user_id' => User::factory()->create()->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'host' => str($name)->slug().'.example.net',
            'port' => 28015,
            'status' => ServerStatus::Published,
            'is_online' => true,
            'players' => 100,
            'max_players' => 200,
            'votes_month' => 10,
            'score' => 10,
        ]);

        foreach ($fields as $key => $value) {
            $server->fieldValues()->create(['game_field_id' => $game->fields()->where('key', $key)->value('id'), 'value' => $value]);
        }

        return $server->load('fieldValues.field');
    }

    public function test_forced_wipe_is_first_thursday_19_london(): void
    {
        $this->travelTo(Carbon::parse('2026-10-28 12:00', 'UTC'));
        $this->assertSame('2026-11-05 19:00', RustWipes::forced()->setTimezone('UTC')->format('Y-m-d H:i'));

        // Улітку Лондон на годину попереду UTC; у сам день вайпу до 19:00 — ще цей вайп
        $this->travelTo(Carbon::parse('2026-07-02 10:00', 'UTC'));
        $this->assertSame('2026-07-02 18:00', RustWipes::forced()->setTimezone('UTC')->format('Y-m-d H:i'));
    }

    public function test_page_shows_wipe_cycle_calendar_and_workbench(): void
    {
        $this->makeServer('Kyiv Solo Weekly', ['last_wiped_at' => now()->subDays(3), 'modded' => false, 'queue' => 4], ['wipe' => 'shhotiznia', 'group' => 'solo', 'rate' => 'x2', 'map_size' => '4000']);
        $this->makeServer('Karpaty Monthly', ['last_wiped_at' => now()->subHours(5), 'modded' => true], ['wipe' => 'shhomisiacia', 'group' => 'bez-limitu']);

        $this->get('/rust')->assertOk()
            ->assertSee('game-rust', false)
            // Верстак: креслення з днями, групи, рейти, розмір мапи
            ->assertSee('class="bpc"', false)->assertSee('7д')->assertSee('class="grp"', false)->assertSee('4к')
            // Рядок: цикл вайпу, свіжий вайп, Oxide, черга, client.connect
            ->assertSee('Свіжий вайп')->assertSee('Oxide · плагіни')->assertSee('черга 4')->assertSee('client.connect kyiv-solo-weekly.example.net:28015')
            // Календар: щотижневий вайп через 4 дні
            ->assertSee('data-go=', false)
            ->assertSee('id="forced"', false);
    }

    public function test_fresh_and_vanilla_filters_and_wipe_sort(): void
    {
        $this->makeServer('Old Wipe', ['last_wiped_at' => now()->subDays(5), 'modded' => true], ['wipe' => 'shhotiznia']);
        $this->makeServer('New Wipe', ['last_wiped_at' => now()->subHours(3), 'modded' => false], ['wipe' => 'shhotiznia']);
        $this->makeServer('Unknown Wipe', [], ['wipe' => 'raz-na-2-tizni']);

        $list = fn (string $query) => $this->get('/rust?'.$query, ['X-Partial' => 'list'])->assertOk()->getContent();

        $fresh = $list('fresh=1');
        $this->assertStringContainsString('New Wipe', $fresh);
        $this->assertStringNotContainsString('Old Wipe', $fresh);

        $vanilla = $list('vanilla=1');
        $this->assertStringNotContainsString('Old Wipe', $vanilla);
        $this->assertStringContainsString('Unknown Wipe', $vanilla);

        $byWipe = $list('sort=wipe');
        $this->assertLessThan(strpos($byWipe, 'Old Wipe'), strpos($byWipe, 'New Wipe'));
        $this->assertLessThan(strpos($byWipe, 'Unknown Wipe'), strpos($byWipe, 'Old Wipe'));
    }

    public function test_rust_keywords_give_wipe_time_queue_and_mods(): void
    {
        $born = now()->subHours(20)->getTimestamp();
        $parsed = SteamKeywords::parse("mp200,cp150,qp12,v2591,born{$born},gm_rust,oxide,modded");
        $this->assertSame([12, $born, true], [$parsed['queue'], $parsed['wiped']->getTimestamp(), $parsed['modded']]);
        $this->assertFalse(SteamKeywords::parse('mp100,cp20,qp0,born'.$born)['modded']);
        $this->assertNull(SteamKeywords::parse('secure,cs2')['modded']);

        $parse = new ReflectionMethod(SourceDriver::class, 'parseSource');
        $packet = "\xFF\xFF\xFF\xFFI\x11"."Rusty UA\0Procedural Map\0rust\0Rust\0"."\x00\x00"."\x96\xC8\x00dw\x00\x01"."2591\0"."\xA0"."\x6F\x6D"."mp200,cp150,qp3,born{$born},oxide\0";
        $result = $parse->invoke(new SourceDriver, $packet, 30);

        $server = $this->makeServer('Rusty UA', [], ['wipe' => 'shhotiznia']);
        app(ServerMonitor::class)->record($server, $result);
        $this->assertSame([3, $born, true], [$server->queue, $server->last_wiped_at->getTimestamp(), $server->modded]);
        // Наступний вайп щотижневого — через 7 днів від останнього
        $this->assertSame($born + 7 * 86400, RustWipes::next($server->fresh()->load('fieldValues.field'))->getTimestamp());
    }
}
