<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GameSeeder::class);
    }

    private function makeServer(string $name, array $attributes = [], ?string $chronicle = null): Server
    {
        $game = Game::where('slug', 'lineage2')->first();
        $server = Server::create($attributes + [
            'game_id' => $game->id,
            'user_id' => User::factory()->create()->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'host' => str($name)->slug().'.example.net',
            'port' => 7777,
            'status' => ServerStatus::Published,
            'is_online' => true,
            'players' => 120,
            'max_players' => 500,
            'votes_month' => 100,
            'score' => 100,
        ]);

        if ($chronicle) {
            $server->fieldValues()->create(['game_field_id' => $game->fields()->where('key', 'chronicle')->value('id'), 'value' => $chronicle]);
        }

        return $server;
    }

    public function test_server_page_uses_game_style_with_vote_card_and_similar_servers(): void
    {
        $server = $this->makeServer('Aden Test', ['rank' => 2, 'votes_month' => 100], 'interlude');
        $this->makeServer('Leader', ['rank' => 1, 'votes_month' => 150], 'interlude');
        $this->makeServer('Other Chronicle', ['rank' => 3, 'votes_month' => 50], 'classic');

        $this->get(route('servers.show', $server))
            ->assertOk()
            ->assertSee('<body class="g-l2">', false)
            ->assertSee('class="votecard"', false)
            ->assertSee('до 1-го місця 50')
            ->assertSee('data-copy="aden-test.example.net:7777"', false)
            ->assertSee(__('serverpage.similar'))
            ->assertSeeInOrder(['Leader', 'Other Chronicle'])
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_vote_page_shows_form_then_thanks_then_countdown(): void
    {
        $server = $this->makeServer('Aden Test', ['rank' => 1, 'callback_url' => 'https://aden.example.net/vote']);

        $this->get(route('servers.vote', $server))
            ->assertOk()
            ->assertSee('class="vform"', false)
            ->assertSee('data-fingerprint-form', false);

        $this->post(route('servers.vote.store', $server), ['nickname' => 'Taras'])
            ->assertRedirect(route('servers.vote', $server));

        // Одразу після голосу — «Дякуємо», нік для бонусу й відлік до наступного голосу
        $this->withSession(['voted' => ['nickname' => 'Taras']])->get(route('servers.vote', $server))
            ->assertOk()
            ->assertSee(__('serverpage.done_title'))
            ->assertSee(__('serverpage.reward', ['nick' => 'Taras']))
            ->assertSee('id="vcd"', false);

        // Повторний захід — «Ви вже голосували», без форми
        $this->get(route('servers.vote', $server))
            ->assertOk()
            ->assertSee(__('serverpage.wait_title'))
            ->assertDontSee('class="vform"', false);
    }

    public function test_rejected_vote_shows_error_in_form(): void
    {
        $server = $this->makeServer('Closed', ['status' => ServerStatus::Published]);
        $this->post(route('servers.vote.store', $server), ['nickname' => 'Taras']);

        // Другий голос з тієї ж адреси відхиляється — стан «вже голосували»
        $this->post(route('servers.vote.store', $server), ['nickname' => 'Taras'])->assertSessionHasErrors('vote');
    }
}
