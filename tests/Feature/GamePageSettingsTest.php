<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Filament\Resources\GameResource\Pages\EditGame;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GamePageSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, GameSeeder::class]);
    }

    private function makeServer(string $game, string $name, array $fields = [], array $attributes = []): Server
    {
        $game = Game::where('slug', $game)->first();
        $server = Server::create($attributes + [
            'game_id' => $game->id,
            'user_id' => User::factory()->create()->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'host' => str($name)->slug().'.example.net',
            'port' => 7777,
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

    public function test_admin_can_hide_blocks_and_override_theme_texts(): void
    {
        $game = Game::where('slug', 'counter-strike')->first();
        $game->update(['page' => [
            'blocks' => ['season' => false, 'fame' => false, 'live' => false],
            'texts' => ['uk' => ['cs_buy' => 'Арсенал', 'cs_board' => 'Рейтинг серверів']],
        ]]);

        $this->get('/counter-strike')
            ->assertOk()
            ->assertSee('Арсенал')
            ->assertSee('Рейтинг серверів')
            ->assertDontSee('<h3>Меню закупівлі', false)
            ->assertDontSee('id="season"', false)
            ->assertDontSee('MVP місяця')
            ->assertSee('Популярні карти')
            ->assertSee('"enabled":false', false);
    }

    public function test_minecraft_splash_lines_come_from_admin(): void
    {
        Game::where('slug', 'minecraft')->first()->update(['page' => ['texts' => ['uk' => ['mc_splash' => "Перший напис!\n\nДругий напис!"]]]]);

        $this->get('/minecraft')
            ->assertOk()
            ->assertSee('<span class="splash" id="splash" aria-hidden="true">Перший напис!</span>', false)
            ->assertSee('"splash":'.json_encode(['Перший напис!', 'Другий напис!']), false);
    }

    public function test_option_styling_and_connect_command_come_from_field_options(): void
    {
        $game = Game::where('slug', 'gta')->first();
        $field = $game->fields()->where('key', 'platform')->first();
        $field->update(['options' => collect($field->options)->map(fn ($o) => $o['value'] === 'sa-mp'
            ? ['color' => '#123456', 'connect' => 'samp://{address}'] + $o : $o)->all()]);
        $this->makeServer('gta', 'Lviv Streets', ['platform' => 'sa-mp']);

        $this->get('/gta')
            ->assertOk()
            ->assertSee('style="--p:#123456"', false)
            ->assertSee('data-connect="samp://lviv-streets.example.net:7777"', false);
    }

    public function test_counter_strike_version_switch_uses_short_name_note_and_color(): void
    {
        $this->makeServer('counter-strike', 'Oldschool', ['version' => 'cs-16']);

        $this->get('/counter-strike')
            ->assertOk()
            ->assertSee('style="--v:#f2a33a"', false)
            ->assertSee('1.6<small>GoldSrc</small>', false)
            ->assertSee('style="--side:#f2a33a"', false);
    }

    public function test_popular_block_uses_field_chosen_in_admin(): void
    {
        $this->makeServer('lineage2', 'Aden', ['chronicle' => 'interlude', 'rates' => 'x50']);
        Game::where('slug', 'lineage2')->first()->update(['page' => ['popular_field' => 'rates', 'soon_limit' => 1]]);

        $this->get('/lineage2')
            ->assertOk()
            ->assertSee('Популярність: рейти');
    }

    public function test_admin_saves_page_settings_from_game_form(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $game = Game::where('slug', 'minecraft')->first();

        $this->actingAs($admin);
        Livewire::test(EditGame::class, ['record' => $game->getRouteKey()])
            ->assertSee('Тексти стилю')
            ->assertSee('Також спробуй анархію!')
            ->set('data.page.blocks.fame', false)
            ->set('data.page.soon_limit', 2)
            ->set('data.page.texts.uk.mc_soon', 'Нові світи')
            ->call('save')
            ->assertHasNoFormErrors();

        $game->refresh();
        $this->assertFalse($game->showsBlock('fame'));
        $this->assertTrue($game->showsBlock('soon'));
        $this->assertSame(2, $game->soonLimit());
        $this->assertSame('version', $game->popularFieldKey());
        $this->assertSame('Нові світи', $game->pageText('mc.soon'));
    }
}
