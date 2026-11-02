<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Filament\Pages\Settings as SettingsPage;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use App\Support\PageCache;
use App\Support\Settings;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PageCacheTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GameSeeder::class);
        $this->server = Server::create([
            'game_id' => Game::where('slug', 'lineage2')->value('id'),
            'user_id' => User::factory()->create()->id,
            'name' => 'Aden Cache',
            'slug' => 'aden-cache',
            'host' => 'play.example.net',
            'status' => ServerStatus::Published,
            'is_online' => true,
            'players' => 120,
        ]);
    }

    public function test_guest_gets_cached_page_with_own_csrf_token(): void
    {
        $url = route('servers.show', $this->server);

        $first = $this->get($url)->assertOk()->assertHeader('X-Page-Cache', 'MISS');
        $this->assertStringNotContainsString(PageCache::TOKEN_MARK, $first->getContent());

        // Зміна без подій моделі (як опитування онлайну) — гість бачить збережену сторінку
        DB::table('servers')->where('id', $this->server->id)->update(['name' => 'Renamed Quietly']);

        $this->flushSession();
        $this->startSession();
        $token = csrf_token();
        $second = $this->get($url)->assertOk()->assertHeader('X-Page-Cache', 'HIT')->assertSee('Aden Cache')->assertDontSee('Renamed Quietly');
        $this->assertStringNotContainsString(PageCache::TOKEN_MARK, $second->getContent());
        $this->assertStringContainsString('content="'.$token.'"', $second->getContent());
    }

    public function test_cache_is_per_language_and_skipped_for_users(): void
    {
        $this->get('/lineage2')->assertHeader('X-Page-Cache', 'MISS');
        $this->get('/en/lineage2')->assertHeader('X-Page-Cache', 'MISS');
        $this->get('/lineage2')->assertHeader('X-Page-Cache', 'HIT');

        $this->actingAs(User::factory()->create())->get('/lineage2')->assertOk()->assertHeaderMissing('X-Page-Cache');
    }

    public function test_content_changes_and_settings_flush_cache(): void
    {
        $url = route('servers.show', $this->server);
        $this->get($url)->assertHeader('X-Page-Cache', 'MISS');

        $this->server->update(['name' => 'Aden Reborn']);
        $this->get($url)->assertHeader('X-Page-Cache', 'MISS')->assertSee('Aden Reborn');

        Settings::save(['page_cache.ttl' => 60]);
        $this->get($url)->assertHeader('X-Page-Cache', 'MISS');

        Settings::save(['page_cache.enabled' => false]);
        $this->get($url)->assertOk()->assertHeaderMissing('X-Page-Cache');
    }

    public function test_flash_messages_are_not_cached(): void
    {
        $this->withSession(['_flash.old' => ['status'], 'status' => 'Дякуємо!'])
            ->get(route('servers.show', $this->server))->assertOk()->assertHeaderMissing('X-Page-Cache');
    }

    public function test_admin_configures_and_flushes_page_cache(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SettingsPage::class)
            ->set('data.page_cache.enabled', false)
            ->set('data.page_cache.ttl', 300)
            ->call('save')->assertHasNoErrors()
            ->callAction('flushPageCache')->assertNotified();

        $this->assertFalse(config('graj.page_cache.enabled'));
        $this->assertSame(300, config('graj.page_cache.ttl'));
    }
}
