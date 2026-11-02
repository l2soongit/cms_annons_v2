<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Filament\Resources\ServerResource\Pages\ListServers;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Сервери в адмінці: вкладки «Усі», «На перевірці» й кожна гра, групи за статусом.
 * Перемикання вкладок без перезавантаження не падає (параметр модифікатора запиту має зватися $query).
 */
class AdminServersListTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabs_and_grouping_switch_without_errors(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(GameSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $gta = Game::where('slug', 'gta')->firstOrFail();
        $l2 = Game::where('slug', 'lineage2')->firstOrFail();
        $mine = Server::create(['game_id' => $gta->id, 'user_id' => $admin->id, 'name' => 'Grove Street RP', 'slug' => 'grove', 'host' => 'grove.example.net', 'status' => ServerStatus::Published]);
        $waiting = Server::create(['game_id' => $l2->id, 'user_id' => $admin->id, 'name' => 'Aden New', 'slug' => 'aden', 'host' => 'aden.example.net', 'status' => ServerStatus::Pending]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ListServers::class)
            ->assertCanSeeTableRecords([$mine, $waiting])
            ->set('activeTab', 'gta')->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$waiting])
            ->set('activeTab', 'queue')->assertCanSeeTableRecords([$waiting])->assertCanNotSeeTableRecords([$mine])
            ->set('tableGrouping', 'game.slug')->assertOk();
    }
}
