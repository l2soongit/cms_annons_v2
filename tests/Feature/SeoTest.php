<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEO (ТЗ 4): ЧПУ фільтрів, редиректи на канонічні адреси, sitemap з мовами, robots.txt, schema.org.
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GameSeeder::class);
    }

    private function server(string $name, array $fields, array $attributes = []): Server
    {
        $game = Game::where('slug', 'lineage2')->first();
        $server = Server::create($attributes + [
            'game_id' => $game->id,
            'user_id' => User::factory()->create()->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'host' => str($name)->slug().'.example.net',
            'status' => ServerStatus::Published,
            'description' => ['uk' => 'Опис сервера '.$name],
        ]);
        foreach ($fields as $key => $value) {
            $server->fieldValues()->create(['game_field_id' => $game->fields()->where('key', $key)->value('id'), 'value' => $value]);
        }

        return $server;
    }

    public function test_filters_live_in_the_path_with_their_own_title(): void
    {
        $this->server('Aden Interlude', ['chronicle' => 'interlude', 'rates' => 'x100']);
        $this->server('Giran Classic', ['chronicle' => 'classic', 'rates' => 'x1']);

        $this->get('/lineage2/interlude')->assertOk()
            ->assertSee('Aden Interlude')->assertDontSee('Giran Classic')
            ->assertSee('<title>Сервери Lineage 2 Interlude — рейтинг і онлайн | graj.club</title>', false)
            ->assertSee('<link rel="canonical" href="'.url('/lineage2/interlude').'">', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            // Посилання фільтрів на сторінці — теж ЧПУ
            ->assertSee('href="'.url('/lineage2/interlude/x100').'"', false);

        $this->get('/lineage2/interlude/x100')->assertOk()->assertSee('Aden Interlude')->assertSee('Lineage 2 Interlude x100');
        $this->get('/en/lineage2/interlude')->assertOk()->assertSee('Aden Interlude')->assertSee('<html lang="en"', false);
        $this->get('/lineage2/bogus')->assertNotFound();
    }

    public function test_old_and_non_canonical_addresses_redirect_permanently(): void
    {
        $this->get('/lineage2?chronicle=interlude&sort=online&type=pvp')->assertStatus(301)
            ->assertRedirect(url('/lineage2/interlude').'?sort=online&type=pvp');
        $this->get('/lineage2/x100/interlude')->assertStatus(301)->assertRedirect(url('/lineage2/interlude/x100'));

        // Оновлення списку без перезавантаження не перенаправляється
        $this->get('/lineage2?chronicle=interlude', ['X-Partial' => 'list'])->assertOk();
    }

    public function test_sitemap_lists_pages_in_every_language(): void
    {
        $server = $this->server('Aden Interlude', ['chronicle' => 'interlude']);

        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8')->getContent();

        $this->assertNotFalse(simplexml_load_string($xml));
        foreach ([url('/'), url('/lineage2'), url('/lineage2/interlude'), route('servers.show', $server), url('/page/rules'), url('/en/lineage2/interlude')] as $loc) {
            $this->assertStringContainsString('<loc>'.$loc.'</loc>', $xml);
        }
        $this->assertStringContainsString('hreflang="en" href="'.url('/en/server/'.$server->slug).'"', $xml);
        // Хроніки без серверів у карту не потрапляють
        $this->assertStringNotContainsString(url('/lineage2/c4').'<', $xml);
    }

    public function test_robots_blocks_indexing_outside_production(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');

        $this->app['env'] = 'production';
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /cabinet')->assertSee('Sitemap: '.route('seo.sitemap'));
    }

    public function test_server_page_has_rating_markup_and_social_tags(): void
    {
        $server = $this->server('Aden Interlude', ['chronicle' => 'interlude']);
        $server->reviews()->create(['user_id' => User::factory()->create()->id, 'rating' => 5, 'body' => 'Чудово', 'status' => 'published']);
        $server->reviews()->create(['user_id' => User::factory()->create()->id, 'rating' => 4, 'body' => 'Добре', 'status' => 'published']);

        $this->get(route('servers.show', $server))->assertOk()
            ->assertSee('"@type":"AggregateRating","ratingValue":4.5,"ratingCount":2', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('<meta property="og:title"', false);
    }
}
