<?php

namespace Tests\Feature;

use App\Billing\Billing;
use App\Billing\BillingException;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Рекламні банери (ТЗ 3.7): бронювання дат на місці, оплата з балансу, показ, лічильники, модерація.
 */
class BannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed([RoleSeeder::class, GameSeeder::class, TariffSeeder::class]);
    }

    private function server(string $game = 'lineage2', float $balance = 10000): Server
    {
        $name = 'Ad Server '.fake()->unique()->numberBetween(1, 99999);

        return Server::create([
            'game_id' => Game::where('slug', $game)->value('id'),
            'user_id' => User::factory()->create(['balance' => $balance])->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'host' => str($name)->slug().'.example.net',
            'website' => 'https://'.str($name)->slug().'.example.net',
            'status' => ServerStatus::Published,
        ]);
    }

    private function tariff(string $placement, int $days, ?string $game = null): Tariff
    {
        return Tariff::where('service', 'banner')->where('placement', $placement)->where('days', $days)
            ->where('game_id', $game ? Game::where('slug', $game)->value('id') : null)->firstOrFail();
    }

    public function test_owner_books_header_banner_from_cabinet(): void
    {
        $server = $this->server();

        $this->actingAs($server->owner)->get(route('cabinet.ads'))->assertOk()->assertSee('Шапка всіх сторінок')->assertSee('усі дні вільні');

        $this->actingAs($server->owner)->post(route('cabinet.ads.store'), [
            'server_id' => $server->id,
            'tariff_id' => $this->tariff('header', 7)->id,
            'starts_at' => today()->toDateString(),
            'image' => UploadedFile::fake()->image('banner.png', 1200, 150),
            'link' => 'site',
            'alt' => 'Найкращий сервер',
        ])->assertRedirect(route('cabinet.ads'))->assertSessionHasNoErrors();

        $banner = Banner::first();
        $this->assertSame(['header', null, $server->website, '9300.00'], [$banner->placement, $banner->game_id, $banner->url, $server->owner->fresh()->balance]);
        Storage::disk('public')->assertExists($banner->image);

        // Показ на публічних сторінках рахується, клік веде на сайт сервера
        $this->get('/')->assertOk()->assertSee('Найкращий сервер')->assertSee('rel="sponsored noopener"', false)
            // Класи з EasyList (##.ad-slot, ##.ad-header) — блокувальник ховає банер одразу після завантаження
            ->assertSee('gj-place-header', false)->assertDontSee('ad-slot', false)->assertDontSee('ad-header', false);
        $this->get('/lineage2')->assertOk()->assertSee(route('ads.click', $banner));
        $this->get(route('ads.click', $banner))->assertRedirect($server->website);
        $this->assertSame([2, 1], [$banner->fresh()->impressions, $banner->fresh()->clicks]);

        // У кабінеті реклами немає
        $this->actingAs($server->owner)->get(route('cabinet'))->assertDontSee(route('ads.click', $banner));
    }

    public function test_dates_on_the_same_placement_cannot_overlap(): void
    {
        $billing = app(Billing::class);
        $first = $this->server();
        $second = $this->server();

        $billing->bookBanner($first->owner, $first, $this->tariff('sidebar', 7), today()->addDays(2), 'banners/a.webp', 'https://a.example.net');

        try {
            $billing->bookBanner($second->owner, $second, $this->tariff('sidebar', 1), today()->addDays(8), 'banners/b.webp', 'https://b.example.net');
            $this->fail('Дати перетинаються — бронювання мало бути відхилене');
        } catch (BillingException $e) {
            $this->assertStringContainsString('заброньовані', $e->getMessage());
            $this->assertSame('10000.00', $second->owner->fresh()->balance);
        }

        // Наступний день після закінчення і інше місце — вільні
        $billing->bookBanner($second->owner, $second, $this->tariff('sidebar', 1), today()->addDays(9), 'banners/b.webp', 'https://b.example.net');
        $billing->bookBanner($second->owner, $second, $this->tariff('header', 1), today()->addDays(3), 'banners/c.webp', 'https://c.example.net');

        $this->assertCount(8, $billing->busyDays('sidebar', null));
        $this->assertCount(1, $billing->busyDays('header', null));
        $this->assertContains(today()->addDays(8)->toDateString(), $billing->busyDays('sidebar', null));
    }

    public function test_game_page_banner_shows_only_on_its_game(): void
    {
        $server = $this->server('minecraft');
        $banner = app(Billing::class)->bookBanner($server->owner, $server, $this->tariff('game', 1, 'minecraft'), today(), 'banners/mc.webp', 'https://mc.example.net', 'Minecraft реклама');

        $this->assertSame($server->game_id, $banner->game_id);
        $this->get('/minecraft')->assertOk()->assertSee('Minecraft реклама');
        $this->get('/lineage2')->assertOk()->assertDontSee('Minecraft реклама');

        // Банер гри Minecraft не можна купити для сервера Lineage 2
        $l2 = $this->server('lineage2');
        $this->expectException(BillingException::class);
        app(Billing::class)->bookBanner($l2->owner, $l2, $this->tariff('game', 1, 'minecraft'), today()->addDays(3), 'banners/x.webp', 'https://x.example.net');
    }

    public function test_hidden_or_refunded_banner_is_not_shown_and_frees_dates(): void
    {
        $server = $this->server();
        $billing = app(Billing::class);
        $banner = $billing->bookBanner($server->owner, $server, $this->tariff('header', 7), today(), 'banners/a.webp', 'https://a.example.net', 'Прихований банер');

        $banner->update(['is_active' => false]);
        $this->get('/')->assertDontSee('Прихований банер');
        $this->get(route('ads.click', $banner))->assertNotFound();

        $banner->update(['is_active' => true]);
        $billing->cancel($banner->order, refund: true);
        $this->get('/')->assertDontSee('Прихований банер');
        $this->assertSame([], $billing->busyDays('header', null));
        $this->assertSame('10000.00', $server->owner->fresh()->balance);
    }

    public function test_banner_cannot_be_bought_as_regular_service_or_too_far_ahead(): void
    {
        $server = $this->server();

        try {
            app(Billing::class)->purchase($server->owner, $server, $this->tariff('header', 7));
            $this->fail('Банер купується лише через бронювання');
        } catch (BillingException) {
        }

        $this->expectException(BillingException::class);
        app(Billing::class)->bookBanner($server->owner, $server, $this->tariff('header', 7), today()->addDays(90), 'banners/a.webp', 'https://a.example.net');
    }

    public function test_admin_sees_banners_with_stats(): void
    {
        $server = $this->server();
        app(Billing::class)->bookBanner($server->owner, $server, $this->tariff('header', 1), today(), 'banners/a.webp', 'https://a.example.net');

        $this->actingAs(User::factory()->create()->assignRole('admin'))->get('/admin/banners')->assertOk()->assertSee($server->name)->assertSee('CTR');
    }
}
