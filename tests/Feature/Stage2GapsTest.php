<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Livewire\Cabinet\ServerForm;
use App\Models\Game;
use App\Models\GameField;
use App\Models\Server;
use App\Models\User;
use App\Notifications\ServerModerated;
use App\Notifications\UserWarned;
use App\Servers\ServerModeration;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Прогалини етапу 2 за ТЗ: поле «так / ні», ролі з дозволами, санкції, сортування, поля ігор.
 */
class Stage2GapsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, GameSeeder::class]);
    }

    private function server(string $game = 'dayz', array $attributes = []): Server
    {
        $name = $attributes['name'] ?? 'Chernarus '.fake()->unique()->numberBetween(1, 9999);

        return Server::create($attributes + [
            'game_id' => Game::where('slug', $game)->value('id'),
            'user_id' => User::factory()->create()->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'host' => str($name)->slug().'.example.net',
            'port' => 2302,
            'status' => ServerStatus::Published,
            'is_online' => true,
            'players' => 10,
            'max_players' => 60,
            'votes_month' => 5,
            'score' => 5,
        ]);
    }

    public function test_boolean_field_has_yes_no_options_and_filters_servers(): void
    {
        $mods = GameField::where('key', 'mods')->first();
        $this->assertSame('boolean', $mods->type);
        $this->assertSame(['1', '0'], collect($mods->options)->pluck('value')->all());

        $modded = $this->server(attributes: ['name' => 'Modded Zone']);
        $modded->fieldValues()->create(['game_field_id' => $mods->id, 'value' => '1']);
        $vanilla = $this->server(attributes: ['name' => 'Vanilla Zone']);
        $vanilla->fieldValues()->create(['game_field_id' => $mods->id, 'value' => '0']);

        $this->get('/dayz?mods=1')->assertOk()->assertSee('Modded Zone')->assertDontSee('Vanilla Zone');
        $this->get('/dayz?mods=0')->assertOk()->assertSee('Vanilla Zone')->assertDontSee('Modded Zone');
    }

    public function test_cabinet_form_saves_boolean_and_unchecked_box_as_no(): void
    {
        $owner = User::factory()->create();
        $game = Game::where('slug', 'dayz')->first();

        Livewire::actingAs($owner)->test(ServerForm::class)
            ->set('game_id', $game->id)
            ->set('name', 'Livonia Mods')
            ->set('host', '8.8.8.8')
            ->set('description_uk', str_repeat('Опис сервера з модами. ', 4))
            ->set('fields.map', 'livonia')
            ->set('fields.style', 'pve')
            ->set('fields.mods', true)
            ->call('save')
            ->assertHasNoErrors();

        $server = Server::where('name', 'Livonia Mods')->first();
        $this->assertSame('1', $server->fieldValues()->whereHas('field', fn ($q) => $q->where('key', 'mods'))->value('value'));

        $this->actingAs($owner);
        Livewire::test(ServerForm::class, ['server' => $server])
            ->assertSet('fields.mods', true)
            ->set('fields.mods', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('0', $server->fieldValues()->whereHas('field', fn ($q) => $q->where('key', 'mods'))->value('value'));
    }

    public function test_seeded_fields_from_spec_exist(): void
    {
        $license = Game::where('slug', 'minecraft')->first()->fields()->where('key', 'license')->first();

        $this->assertSame(['Ліцензія', 'Піратка'], collect($license->options)->pluck('label')->all());
        $this->assertFalse($license->is_required);
    }

    public function test_moderator_gets_default_permissions_and_admin_has_all(): void
    {
        $moderator = User::factory()->create()->assignRole('moderator');
        $admin = User::factory()->create()->assignRole('admin');

        $this->assertTrue($moderator->can('servers.moderate'));
        $this->assertFalse($moderator->can('games.manage'));
        $this->assertTrue($admin->can('roles.manage'));

        $this->actingAs($moderator)->get('/admin/servers')->assertOk();
        $this->actingAs($moderator)->get('/admin/games')->assertForbidden();
        $this->actingAs($moderator)->get('/admin/roles')->assertForbidden();
        $this->actingAs($admin)->get('/admin/roles')->assertOk()->assertSee('moderator');
    }

    public function test_admin_creates_role_that_opens_only_its_sections(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'support', 'permissions' => Permission::whereIn('name', ['admin.access', 'reviews.moderate'])->pluck('id')->all()])
            ->call('create')
            ->assertHasNoFormErrors();

        $support = User::factory()->create()->assignRole(Role::findByName('support'));

        $this->actingAs($support)->get('/admin/reviews')->assertOk();
        $this->actingAs($support)->get('/admin/servers')->assertForbidden();
        $this->assertTrue($support->isStaff());
    }

    public function test_temporary_server_block_is_released_automatically(): void
    {
        Notification::fake();
        $server = $this->server();

        app(ServerModeration::class)->block($server, 'Накрутка онлайну', 7);
        $this->assertSame(ServerStatus::Blocked, $server->fresh()->status);
        Notification::assertSentTo($server->owner, ServerModerated::class, fn ($n) => $n->decision === 'blocked_temp' && $n->until !== null);

        $this->travel(6)->days();
        $this->artisan('servers:release-blocks');
        $this->assertSame(ServerStatus::Blocked, $server->fresh()->status);

        $this->travel(2)->days();
        $this->artisan('servers:release-blocks');
        $this->assertSame(ServerStatus::Published, $server->fresh()->status);
        $this->assertNull($server->fresh()->blocked_until);
        Notification::assertSentTo($server->owner, ServerModerated::class, fn ($n) => $n->decision === 'unblocked');
    }

    public function test_permanent_block_is_not_released(): void
    {
        $server = $this->server();
        app(ServerModeration::class)->block($server, 'Заборонений вміст');

        $this->travel(400)->days();
        $this->artisan('servers:release-blocks');

        $this->assertSame(ServerStatus::Blocked, $server->fresh()->status);
    }

    public function test_warnings_are_logged_and_sent(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->create()->assignRole('moderator'));
        $server = $this->server();

        app(ServerModeration::class)->warn($server, 'Неповний опис');

        $this->assertSame(ServerStatus::Published, $server->fresh()->status);
        $this->assertDatabaseHas('moderation_logs', ['subject_id' => $server->id, 'action' => 'warn', 'reason' => 'Неповний опис']);
        Notification::assertSentTo($server->owner, ServerModerated::class, fn ($n) => $n->decision === 'warned');

        $player = User::factory()->create();
        $player->notify(new UserWarned('Спам у відгуках'));
        Notification::assertSentTo($player, UserWarned::class);
    }

    public function test_themed_game_pages_offer_all_sorts_from_spec(): void
    {
        foreach (['lineage2', 'gta', 'counter-strike', 'minecraft'] as $slug) {
            $this->get("/{$slug}")->assertOk()
                ->assertSee("/{$slug}?sort=new", false)
                ->assertSee("/{$slug}?sort=opening", false);
        }
    }
}
