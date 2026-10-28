<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Filament\Resources\ServerResource\Pages\ListServers;
use App\Livewire\Cabinet\ServerForm;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use App\Models\Vote;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Критерій приймання ТЗ: повний цикл через ті самі точки входу, що й люди —
 * реєстрація → додавання сервера в кабінеті → модерація в адмінці → публікація →
 * голос гравця → підписаний callback на сервер → зміна місця в рейтингу.
 */
class FullCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_to_rank_change(): void
    {
        $this->seed([RoleSeeder::class, GameSeeder::class]);
        Http::fake(['93.184.216.34/*' => Http::response('ok')]);
        $game = Game::where('slug', 'lineage2')->firstOrFail();

        // Конкурент уже в рейтингу з одним голосом
        $rival = Server::create([
            'game_id' => $game->id, 'user_id' => User::factory()->create()->id, 'name' => 'Rival', 'slug' => 'rival',
            'host' => '8.8.8.8', 'port' => 7777, 'status' => ServerStatus::Published,
        ]);
        Vote::create(['server_id' => $rival->id, 'ip_hash' => 'r1', 'created_at' => now()]);
        $this->artisan('rating:recalculate')->assertSuccessful();
        $this->assertSame(1, $rival->fresh()->rank);

        // 1. Реєстрація власника
        $this->post('/register', [
            'name' => 'Owner', 'email' => 'owner@example.com', 'rules' => '1',
            'password' => 'secret-password-1', 'password_confirmation' => 'secret-password-1',
        ])->assertRedirect();
        $owner = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($owner);
        $this->get(route('cabinet'))->assertRedirect(route('verification.notice'));

        // …і підтвердження email за посиланням з листа
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $owner->id, 'hash' => sha1($owner->email)]);
        $this->get($link)->assertRedirect();
        $this->assertTrue(($owner = $owner->fresh())->hasVerifiedEmail());
        $this->get(route('cabinet'))->assertOk();

        // 2. Додавання сервера в кабінеті
        Livewire::actingAs($owner)->test(ServerForm::class)
            ->set('game_id', $game->id)
            ->set('name', 'Aden Classic')
            ->set('host', '8.8.4.4')
            ->set('port', 7777)
            ->set('descriptions.uk', str_repeat('Класичний сервер Interlude для всієї родини. ', 3))
            ->set('fields.chronicle', 'interlude')
            ->set('fields.rates', 'x100')
            ->set('fields.type', 'pvp')
            ->call('save')
            ->assertHasNoErrors();

        $server = Server::where('name', 'Aden Classic')->firstOrFail();
        $this->assertSame(ServerStatus::Pending, $server->status);
        $this->assertTrue($owner->fresh()->hasRole('owner'));
        $this->get(route('servers.show', $server))->assertNotFound();

        $this->actingAs($owner)->post(route('cabinet.servers.callback', $server), ['callback_url' => 'http://93.184.216.34/vote'])
            ->assertSessionHasNoErrors();
        $this->assertSame('http://93.184.216.34/vote', $server->fresh()->callback_url);

        // 3. Модерація: модератор схвалює з черги в адмінці
        $moderator = User::factory()->create()->assignRole('moderator');
        Livewire::actingAs($moderator)->test(ListServers::class)
            ->callTableAction('approve', $server)
            ->assertHasNoTableActionErrors();

        // 4. Публікація: сторінка сервера й список гри
        $server->refresh();
        $this->assertSame(ServerStatus::Published, $server->status);
        auth()->logout();
        $this->get(route('servers.show', $server))->assertOk()->assertSee('Aden Classic');
        $this->get('/lineage2')->assertOk()->assertSee('Aden Classic');

        // 5. Голоси двох гравців з різних адрес
        foreach (['Hero' => '93.10.1.1', 'Mage' => '94.20.2.2'] as $nick => $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->post(route('servers.vote.store', $server), ['nickname' => $nick])
                ->assertRedirect(route('servers.show', $server))
                ->assertSessionHasNoErrors();
        }
        $this->assertSame(2, $server->votes()->where('status', 'counted')->count());

        // 6. Callback: підписаний запит з ніком на сервер власника
        $this->assertSame(['delivered', 'delivered'], $server->votes()->pluck('callback_status')->all());
        Http::assertSent(fn ($request) => $request['nickname'] === 'Mage'
            && $request->header('X-Graj-Signature')[0] === hash_hmac('sha256', $request->body(), $server->fresh()->callback_secret));

        // 7. Рейтинг: новий сервер обходить конкурента
        $this->artisan('rating:recalculate')->assertSuccessful();
        $this->assertSame(1, $server->fresh()->rank);
        $this->assertSame(2, $rival->fresh()->rank);
        $this->assertSame(2, $server->fresh()->votes_month);
    }
}
