<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use App\Support\WowFactions;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Сторінка World of Warcraft за демо design/game/wow.html: «Альянс проти Орди», війна фракцій з балансу через API,
 * вкладки версій, фільтри донату й контенту, «рівні фракції», set realmlist.
 */
class WowPageTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GameSeeder::class);
        $this->game = Game::where('slug', 'world-of-warcraft')->firstOrFail();
    }

    private function realm(string $name, array $attributes = [], array $fields = []): Server
    {
        $server = Server::create($attributes + [
            'game_id' => $this->game->id,
            'user_id' => User::factory()->create()->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'host' => 'logon.'.str($name)->slug().'.example.net',
            'port' => 3724,
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

    public function test_page_shows_faction_war_versions_and_realm_rows(): void
    {
        $this->realm('Frostmourne', ['alliance' => 470, 'horde' => 530, 'votes_month' => 50, 'score' => 50, 'port' => 3725],
            ['expansion' => 'WotLK 3.3.5a', 'rates' => 'x5', 'type' => 'PvP', 'content' => 'Blizzlike', 'donate' => 'Впливає на гру']);
        $this->realm('Azeroth Legacy', ['alliance' => 520, 'horde' => 480], ['expansion' => 'Classic 1.12', 'rates' => 'x1', 'donate' => 'Без донату']);
        $this->realm('Pandaria UA', [], ['expansion' => 'MoP 5.4.8']);

        $this->get('/world-of-warcraft')->assertOk()
            ->assertSee('game-wow', false)
            // Банер і війна фракцій: сума лише з реалмів, що передають баланс (990 / 1010)
            ->assertSee('data-f="alli"', false)->assertSee('data-count="990"', false)->assertSee('data-count="1010"', false)
            ->assertSee('--split:49.5%', false)
            // Вкладки версій: назва й збірка
            ->assertSee('WotLK<small>3.3.5a</small>', false)
            // Рядок: realmlist (нестандартний порт — з портом), донат червоним, баланс, реалм без балансу
            ->assertSee('set realmlist logon.frostmourne.example.net:3725')
            ->assertSee('set realmlist logon.azeroth-legacy.example.net')
            ->assertSee('class="p2w"', false)->assertSee('class="free"', false)
            ->assertSee('data-alli="47"', false)
            ->assertSee('Баланс фракцій не передається');
    }

    public function test_balanced_factions_filter(): void
    {
        $this->realm('Even Realm', ['alliance' => 450, 'horde' => 550]);
        $this->realm('Horde Realm', ['alliance' => 200, 'horde' => 800]);
        $this->realm('Silent Realm');

        $this->get('/world-of-warcraft?balanced=1')->assertOk()
            ->assertSee('Even Realm')->assertDontSee('Horde Realm')->assertDontSee('Silent Realm');
    }

    public function test_no_faction_data_shows_how_to_send_it(): void
    {
        $this->realm('Silent Realm');

        $this->get('/world-of-warcraft')->assertOk()
            ->assertSee('Поки жоден реалм не передає баланс фракцій')
            ->assertDontSee('data-count="0"', false);
    }

    public function test_api_accepts_faction_online(): void
    {
        $server = $this->realm('Api Realm', ['callback_secret' => str_repeat('s', 40)]);

        $this->withToken($server->callback_secret)
            ->postJson("/api/v1/servers/{$server->id}/online", ['online' => true, 'players' => 3000, 'alliance' => 1410, 'horde' => 1590])
            ->assertOk();
        $this->assertSame([1410, 1590], [$server->fresh()->alliance, $server->fresh()->horde]);
        $this->assertSame(47, WowFactions::share($server->fresh()));

        // Без фракцій — попередній баланс лишається
        $this->withToken($server->callback_secret)->postJson("/api/v1/servers/{$server->id}/online", ['online' => true, 'players' => 2900])->assertOk();
        $this->assertSame(1410, $server->fresh()->alliance);

        $this->assertNull(WowFactions::share(new Server(['alliance' => null, 'horde' => 10])));
        $this->assertSame('#1eff00', WowFactions::rateColor('x5'));
        $this->assertSame('#ff8000', WowFactions::rateColor('x1000'));
    }
}
