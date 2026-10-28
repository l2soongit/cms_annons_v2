<?php

namespace Tests\Feature;

use App\Billing\Billing;
use App\Enums\ServerStatus;
use App\Models\Banner;
use App\Models\Game;
use App\Models\Server;
use App\Models\Tariff;
use App\Models\User;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Рекламні місця на всіх сторінках: шапка — скрізь, банер гри — лише на сторінці своєї гри,
 * сайдбар — на картці сервера. Три банери від трьох різних серверів, щоб не спрацьовувало правило «один сервер — один банер».
 */
class BannerPlacementTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, GameSeeder::class, TariffSeeder::class]);

        $billing = app(Billing::class);
        $book = function (string $placement, string $alt, ?string $game = null) use ($billing) {
            $server = $this->server($game ?? 'lineage2');
            $tariff = Tariff::where('service', 'banner')->where('placement', $placement)->where('days', 1)
                ->where('game_id', $game ? Game::where('slug', $game)->value('id') : null)->firstOrFail();

            return $billing->bookBanner($server->owner, $server, $tariff, today(), "banners/{$placement}.webp", "https://{$placement}.example.net", $alt);
        };

        $book('header', 'BANNER-HEADER');
        $book('game', 'BANNER-GAME-L2', 'lineage2');
        $book('sidebar', 'BANNER-SIDEBAR');
        $this->server = $this->server('lineage2');
    }

    private function server(string $game): Server
    {
        $name = 'Srv '.fake()->unique()->numberBetween(1, 99999);

        return Server::create([
            'game_id' => Game::where('slug', $game)->value('id'),
            'user_id' => User::factory()->create(['balance' => 100000])->id,
            'name' => $name, 'slug' => str($name)->slug(), 'host' => str($name)->slug().'.example.org',
            'website' => 'https://'.str($name)->slug().'.example.org', 'status' => ServerStatus::Published,
        ]);
    }

    public function test_header_banner_is_on_every_public_page(): void
    {
        $pages = ['/', '/en', '/search', '/developers', '/page/rules', '/page/privacy', '/server/'.$this->server->slug];
        foreach (Game::where('is_active', true)->pluck('slug') as $slug) {
            $pages[] = "/{$slug}";
            $pages[] = "/en/{$slug}";
        }

        $missing = array_values(array_filter($pages, fn ($url) => ! str_contains($this->get($url)->assertOk()->getContent(), 'BANNER-HEADER')));
        $this->assertSame([], $missing, 'Немає банера в шапці на сторінках');

        // На сторінці голосування шапки немає: там своє платне місце під кнопкою
        $this->get('/server/'.$this->server->slug.'/vote')->assertOk()->assertDontSee('BANNER-HEADER', false);

        $owner = $this->server->owner;
        $owner->forceFill(['email_verified_at' => now()])->save();
        // У кабінеті реклами немає, а залогінений гравець на публічних сторінках бачить її, як і гість
        foreach (['/cabinet', '/cabinet/ads', '/cabinet/balance', '/cabinet/profile', '/cabinet/support'] as $url) {
            $this->actingAs($owner)->get($url)->assertOk()->assertDontSee('BANNER-HEADER', false);
        }
        $this->actingAs($owner)->get('/page/rules')->assertOk()->assertSee('BANNER-HEADER', false);
    }

    public function test_vote_page_has_its_own_paid_placement_below_the_form(): void
    {
        $voteUrl = '/server/'.$this->server->slug.'/vote';
        $this->get($voteUrl)->assertOk()->assertDontSee('gj-place-vote', false)->assertDontSee(__('billing.ads.free_title'));

        // Покупець шапки купує й сторінку голосування на ті самі дати: шапки там немає, тож конфлікту нема
        $headerServer = Banner::where('alt', 'BANNER-HEADER')->firstOrFail()->server;
        $tariff = Tariff::where('service', 'banner')->where('placement', 'vote')->where('days', 1)->firstOrFail();
        app(Billing::class)->bookBanner($headerServer->owner, $headerServer, $tariff, today(), 'banners/vote.webp', 'https://vote.example.net', 'BANNER-VOTE');

        $html = $this->get($voteUrl)->assertOk()->assertSee('BANNER-VOTE', false)->assertDontSee('BANNER-HEADER', false)->getContent();
        $this->assertGreaterThan(strpos($html, __('vote.submit')), strpos($html, 'BANNER-VOTE'), 'Банер має стояти під кнопкою');
        $this->get('/server/'.$this->server->slug)->assertOk()->assertDontSee('BANNER-VOTE', false);
        $this->get('/lineage2')->assertOk()->assertDontSee('BANNER-VOTE', false);
    }

    public function test_game_banner_only_on_its_game_and_sidebar_on_server_card(): void
    {
        $this->get('/lineage2')->assertOk()->assertSee('BANNER-GAME-L2', false);
        $this->get('/en/lineage2')->assertOk()->assertSee('BANNER-GAME-L2', false);
        foreach (Game::where('is_active', true)->where('slug', '!=', 'lineage2')->pluck('slug') as $slug) {
            $this->get("/{$slug}")->assertOk()->assertDontSee('BANNER-GAME-L2', false);
        }
        $this->get('/')->assertDontSee('BANNER-GAME-L2', false)->assertDontSee('BANNER-SIDEBAR', false);

        $this->get('/server/'.$this->server->slug)->assertOk()->assertSee('BANNER-SIDEBAR', false);
    }

    public function test_game_side_column_is_a_separate_paid_placement(): void
    {
        // Сайдбар серверів у бічну колонку гри не потрапляє; поки місце вільне — запрошення купити рекламу
        $this->get('/lineage2')->assertOk()->assertDontSee('BANNER-SIDEBAR', false)
            ->assertSee(__('billing.ads.free_title'))->assertSee(route('cabinet.ads'), false);

        $server = $this->server('lineage2');
        $tariff = Tariff::where('service', 'banner')->where('placement', 'game_side')->where('days', 1)->where('game_id', $server->game_id)->firstOrFail();
        app(Billing::class)->bookBanner($server->owner, $server, $tariff, today(), 'banners/side.webp', 'https://side.example.net', 'BANNER-SIDE-L2');

        $this->get('/lineage2')->assertOk()->assertSee('BANNER-SIDE-L2', false)->assertDontSee(__('billing.ads.free_title'));
        $this->get('/server/'.$this->server->slug)->assertOk()->assertDontSee('BANNER-SIDE-L2', false);
        foreach (Game::where('is_active', true)->where('slug', '!=', 'lineage2')->pluck('slug') as $slug) {
            $this->get("/{$slug}")->assertOk()->assertDontSee('BANNER-SIDE-L2', false);
        }

        // Ігри без бічної колонки в стилі цього місця не мають
        $this->assertFalse(Tariff::where('placement', 'game_side')->where('game_id', Game::where('slug', 'minecraft')->value('id'))->exists());
        $owner = $server->owner;
        $owner->forceFill(['email_verified_at' => now()])->save();
        // Біля кожного місця — схема сторінки з підписом, де стоятиме банер
        $this->actingAs($owner)->get('/cabinet/ads')->assertOk()->assertSee(__('billing.ads.placements.game_side'))
            ->assertSee(__('billing.ads.where.game_side'))->assertSee(__('billing.ads.where.header'))->assertSee('<svg viewBox="0 0 200 130"', false);
    }

    public function test_every_themed_game_page_has_its_game_slot(): void
    {
        foreach (Game::where('is_active', true)->where('slug', '!=', 'lineage2')->get() as $game) {
            $server = $this->server($game->slug);
            $tariff = Tariff::where('service', 'banner')->where('placement', 'game')->where('days', 1)->where('game_id', $game->id)->first();
            $this->assertNotNull($tariff, "Немає тарифу банера для гри {$game->slug}");

            app(Billing::class)->bookBanner($server->owner, $server, $tariff, today(), 'banners/g.webp', 'https://g.example.net', "SLOT-{$game->slug}");
            $this->get("/{$game->slug}")->assertOk()->assertSee("SLOT-{$game->slug}", false);
        }
    }

    public function test_impressions_and_clicks_are_counted(): void
    {
        $header = Banner::where('alt', 'BANNER-HEADER')->firstOrFail();

        $this->get('/');
        $this->get('/search');
        $this->assertSame(2, $header->fresh()->impressions);

        $this->get(route('ads.click', $header))->assertRedirect('https://header.example.net');
        $this->assertSame(1, $header->fresh()->clicks);
    }

    public function test_expired_or_future_banner_is_not_shown(): void
    {
        $this->travel(2)->days();
        $this->get('/')->assertOk()->assertDontSee('BANNER-HEADER', false);
        $this->get('/lineage2')->assertOk()->assertDontSee('BANNER-GAME-L2', false);

        $header = Banner::where('alt', 'BANNER-HEADER')->firstOrFail();
        $this->get(route('ads.click', $header))->assertRedirect('https://header.example.net');
        $this->assertSame(0, $header->fresh()->clicks);
    }
}
