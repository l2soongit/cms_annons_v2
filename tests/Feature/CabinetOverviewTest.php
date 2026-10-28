<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «Огляд» кабінету: місце й відрив до сусіда, показники, список справ, перемикання між своїми серверами.
 */
class CabinetOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function server(User $owner, string $name, float $score, int $rank, ServerStatus $status = ServerStatus::Published): Server
    {
        return Server::create([
            'game_id' => Game::where('slug', 'lineage2')->value('id'), 'user_id' => $owner->id, 'name' => $name, 'slug' => str($name)->slug(),
            'host' => str($name)->slug().'.example.org', 'status' => $status, 'score' => $score, 'votes_month' => (int) $score,
            'rank' => $status === ServerStatus::Published ? $rank : null, 'is_online' => true, 'players' => 120, 'max_players' => 500,
        ]);
    }

    public function test_overview_shows_place_gap_and_todo_for_selected_own_server(): void
    {
        $this->seed([RoleSeeder::class, GameSeeder::class]);
        $owner = User::factory()->create();
        $rival = User::factory()->create();
        $this->server($rival, 'Leader Srv', 500, 1);
        $this->server($rival, 'Second Srv', 300, 2);
        $mine = $this->server($owner, 'My Aden', 250, 3);
        $pending = $this->server($owner, 'My Draft', 0, 0, ServerStatus::Pending);
        $mine->votes()->create(['ip_hash' => 'x', 'nickname' => 'Elf_Mira', 'status' => 'counted', 'callback_status' => 'delivered', 'created_at' => now()->subHour()]);

        $this->actingAs($owner)->get('/cabinet')->assertOk()
            ->assertSee('#3')->assertSee('Second Srv')->assertSee('My Aden')
            // До 2-го місця: 300 − 250 + 1
            ->assertSee(trans_choice('cabinet.overview.points', 51, ['n' => 51]))
            ->assertSee('Elf_Mira')->assertSee(__('cabinet.votes.callback_delivered'))
            ->assertSee(__('cabinet.overview.t_verified'))->assertSee(route('cabinet.servers.show', $mine).'#s-verify', false)
            ->assertSee('120');

        // Сервер на модерації: без рейтингу, з поясненням
        $this->actingAs($owner)->get('/cabinet?server='.$pending->id)->assertOk()
            ->assertSee(__('cabinet.overview.not_ranked', ['status' => mb_strtolower(ServerStatus::Pending->label())]));

        // Чужий сервер у ?server= ігнорується — показується свій
        $foreign = Server::where('name', 'Leader Srv')->first();
        $this->actingAs($owner)->get('/cabinet?server='.$foreign->id)->assertOk()->assertSee('My Aden')->assertDontSee(__('cabinet.overview.leader'));
    }

    public function test_leader_sees_lead_and_empty_cabinet_still_invites_to_add_server(): void
    {
        $this->seed([RoleSeeder::class, GameSeeder::class]);
        $owner = User::factory()->create();
        $this->server($owner, 'Top One', 400, 1);
        $this->server(User::factory()->create(), 'Runner Up', 150, 2);

        $this->actingAs($owner)->get('/cabinet')->assertOk()->assertSee(__('cabinet.overview.leader'))
            ->assertSee(trans_choice('cabinet.overview.points', 250, ['n' => 250]));

        $this->actingAs(User::factory()->create())->get('/cabinet')->assertOk()->assertSee(__('cabinet.empty_title'));
    }
}
