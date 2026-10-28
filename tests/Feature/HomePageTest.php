<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Filament\Pages\HomePageSettings;
use App\Models\Game;
use App\Models\Server;
use App\Models\Tariff;
use App\Models\User;
use App\Models\Vote;
use App\Support\HomePageData;
use App\Support\ImageOptimizer;
use App\Support\Settings;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GameSeeder::class);
    }

    private function makeServer(array $attributes = []): Server
    {
        return Server::create($attributes + [
            'game_id' => Game::where('slug', 'lineage2')->value('id'),
            'user_id' => User::factory()->create()->id,
            'name' => 'Aden Test',
            'slug' => 'aden-test',
            'host' => 'play.example.net',
            'status' => ServerStatus::Published,
            'is_online' => true,
            'players' => 120,
            'max_players' => 500,
            'votes_month' => 42,
            'score' => 42,
        ]);
    }

    public function test_home_shows_games_ranking_and_counters(): void
    {
        $this->makeServer();

        $this->get('/')
            ->assertOk()
            ->assertSee('Lineage 2')
            ->assertSee('Aden Test')
            ->assertSee('g-l2', false)
            ->assertSee('data-count="120"', false);
    }

    public function test_home_hides_unpublished_servers(): void
    {
        $this->makeServer(['status' => ServerStatus::Pending, 'name' => 'Hidden One']);

        $this->get('/')->assertOk()->assertDontSee('Hidden One');
    }

    public function test_texts_and_sections_come_from_admin_settings(): void
    {
        $this->makeServer();

        Settings::save([
            'home.hero_title' => ['uk' => 'Тестовий заголовок', 'en' => ''],
            'home.sections' => ['arena', 'games'],
        ]);
        HomePageData::forget();

        $this->get('/')
            ->assertOk()
            ->assertSee('Тестовий заголовок')
            ->assertDontSee('id="plans"', false)
            ->assertDontSee('id="top"', false);
    }

    public function test_admin_can_save_home_page_settings(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(HomePageSettings::class)
            ->set('data.home.hero_accent.uk', 'перемагають')
            ->set('data.home.quick_links.uk', ['Interlude x5', 'Анархія'])
            ->set('data.home.quick_links.en', ['Interlude x5', 'Anarchy'])
            ->set('data.home.quick_links.pl', [])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('перемагають', config('graj.home.hero_accent.uk'));
        $this->assertSame(['Interlude x5', 'Anarchy'], config('graj.home.quick_links.en'));
        // Кожна мова — свої підказки; без перекладу — основною мовою
        $this->get('/en')->assertOk()->assertSee('Anarchy')->assertDontSee('Анархія');
        $this->get('/pl')->assertOk()->assertSee('Анархія');
    }

    public function test_live_feed_returns_new_counted_votes_only(): void
    {
        $server = $this->makeServer();
        $old = Vote::create(['server_id' => $server->id, 'ip_hash' => 'a', 'status' => 'counted', 'nickname' => 'Old', 'created_at' => now()->subHour()]);
        Vote::create(['server_id' => $server->id, 'ip_hash' => 'b', 'status' => 'counted', 'nickname' => 'Taras', 'created_at' => now()]);
        Vote::create(['server_id' => $server->id, 'ip_hash' => 'c', 'status' => 'suspicious', 'nickname' => 'Bot', 'created_at' => now()]);

        $this->getJson(route('home.live', ['after' => $old->id]))
            ->assertOk()
            ->assertJsonCount(1, 'votes')
            ->assertJsonPath('votes.0.nick', 'Taras')
            ->assertJsonPath('votes.0.server', 'Aden Test')
            ->assertJsonPath('votes.0.theme', 'l2')
            ->assertJsonPath('servers.'.$server->id, 42)
            ->assertJsonPath('online', 120);
    }

    public function test_custom_images_replace_animated_backgrounds(): void
    {
        $this->makeServer();
        Game::where('slug', 'lineage2')->update(['cover' => 'games/l2.webp']);
        Settings::save(['home.hero_image' => 'home/hero.webp', 'home.cta_image' => 'home/cta.webp']);
        HomePageData::forget();

        $this->get('/')
            ->assertOk()
            ->assertSee('class="hero-img"', false)
            ->assertSee('/storage/home/hero.webp', false)
            ->assertSee('/storage/home/cta.webp', false)
            ->assertSee('/storage/games/l2.webp', false);
    }

    public function test_owner_logo_banner_and_site_logo_are_shown(): void
    {
        $server = $this->makeServer(['opens_at' => now()->addDays(3)]);
        // Анонс на головній — платна послуга «Скоро відкриття»
        $tariff = Tariff::create(['game_id' => $server->game_id, 'service' => 'coming_soon', 'name' => ['uk' => 'Анонс'], 'price' => 300]);
        $server->orders()->create(['user_id' => $server->user_id, 'tariff_id' => $tariff->id, 'amount' => 300, 'starts_at' => now(), 'ends_at' => now()->addDays(3)]);
        $server->media()->create(['type' => 'logo', 'path' => 'servers/logo.webp']);
        $server->media()->create(['type' => 'banner', 'path' => 'servers/banner.webp']);
        Game::where('slug', 'lineage2')->update(['icon' => 'games/icons/l2.webp']);
        Settings::save(['home.logo_image' => 'home/logo.webp', 'home.og_image' => 'home/og.webp']);
        HomePageData::forget();

        $this->get('/')
            ->assertOk()
            ->assertSee('class="slogo"', false)
            ->assertSee('/storage/servers/logo.webp', false)
            ->assertSee('class="soon-cover"', false)
            ->assertSee('/storage/games/icons/l2.webp', false)
            ->assertSee('<img src="'.url('/storage/home/logo.webp').'" alt="graj.club"', false)
            ->assertSee('<meta property="og:image" content="'.url('/storage/home/og.webp').'">', false);

        $this->assertSame(url('/storage/servers/banner.webp'), HomePageData::get()['arena']['all']['rows'][0]['image']);
    }

    public function test_uploaded_cover_is_converted_to_webp_and_resized(): void
    {
        Storage::fake('public');

        $path = ImageOptimizer::store(UploadedFile::fake()->image('cover.jpg', 3000, 1500), 'games', 'cover');

        $this->assertStringEndsWith('.webp', $path);
        [$width] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(1600, $width);
    }

    public function test_replacing_game_cover_deletes_old_file_and_refreshes_home(): void
    {
        Storage::fake('public');
        $this->makeServer();
        $game = Game::where('slug', 'lineage2')->first();
        $game->update(['cover' => 'games/old.webp']);
        Storage::disk('public')->put('games/old.webp', 'x');
        HomePageData::get();

        $game->update(['cover' => 'games/new.webp']);

        Storage::disk('public')->assertMissing('games/old.webp');
        $this->assertStringEndsWith('games/new.webp', collect(HomePageData::get()['games'])->firstWhere('name', 'Lineage 2')['image']);
    }

    public function test_replaced_home_image_is_deleted(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('home/old.webp', 'x');
        Settings::save(['home.hero_image' => 'home/old.webp']);

        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(HomePageSettings::class)
            ->set('data.home.hero_image', null)
            ->call('save')
            ->assertHasNoErrors();

        Storage::disk('public')->assertMissing('home/old.webp');
        $this->assertNull(config('graj.home.hero_image'));
    }

    public function test_game_without_theme_gets_neutral_style(): void
    {
        $game = Game::where('slug', 'lineage2')->first();
        $game->update(['theme' => null]);

        $this->assertSame('default', $game->themeKey());
        $this->assertSame('L2', $game->shortName());
        $this->assertSame('GTA', Game::where('slug', 'gta')->first()->shortName());
    }
}
