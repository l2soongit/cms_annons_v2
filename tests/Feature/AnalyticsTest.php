<?php

namespace Tests\Feature;

use App\Analytics\Report;
use App\Analytics\Rollup;
use App\Analytics\Visitor;
use App\Enums\ServerStatus;
use App\Filament\Pages\Analytics;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Власна аналітика: запис переглядів на сервері, події з браузера, денні зведення, звіти в адмінці й кабінеті.
 */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([GameSeeder::class, RoleSeeder::class]);
        $this->server = Server::create([
            'game_id' => Game::where('slug', 'lineage2')->value('id'),
            'user_id' => User::factory()->create()->id,
            'name' => 'Aden Stats',
            'slug' => 'aden-stats',
            'host' => 'play.example.net',
            'website' => 'https://aden.example.net',
            'status' => ServerStatus::Published,
            'is_online' => true,
            'players' => 100,
            'max_players' => 500,
        ]);
    }

    private function visit(string $url, string $ua = self::CHROME, array $headers = [], string $ip = '203.0.113.5')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->withHeaders(['User-Agent' => $ua] + $headers)->get($url);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_page_views_are_recorded_with_source_device_and_context(): void
    {
        $this->visit('/?utm_source=telegram&utm_campaign=launch')->assertOk();
        $this->visit('/server/aden-stats', self::IPHONE, ['Referer' => 'https://www.google.com/search?q=l2', 'CF-IPCountry' => 'UA', 'Accept-Language' => 'uk-UA,uk;q=0.9'], '198.51.100.7')->assertOk();
        $this->visit('/server/aden-stats', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')->assertOk();
        // Не перегляди: JSON, адмінка, Livewire
        $this->withHeaders(['User-Agent' => self::CHROME])->getJson('/live/feed')->assertOk();

        $rows = DB::table('analytics_views')->orderBy('id')->get();
        $this->assertCount(3, $rows);
        $this->assertSame(['campaign', 'telegram', 'launch', 'desktop', 'Chrome', 'Windows'], [$rows[0]->source, $rows[0]->utm_source, $rows[0]->utm_campaign, $rows[0]->device, $rows[0]->browser, $rows[0]->os]);
        $this->assertSame(['search', 'google.com', 'mobile', 'Safari', 'iOS', 'UA', 'uk', $this->server->id, 'servers.show'], [$rows[1]->source, $rows[1]->referrer, $rows[1]->device, $rows[1]->browser, $rows[1]->os, $rows[1]->country, $rows[1]->lang, (int) $rows[1]->server_id, $rows[1]->route]);
        $this->assertSame(['bot', 'Google'], [$rows[2]->device, $rows[2]->browser]);
        // IP не зберігається — лише 16 символів хешу
        $this->assertSame(16, strlen($rows[0]->visitor));
        $this->assertStringNotContainsString('203.0.113.5', json_encode($rows));
    }

    public function test_staff_and_owner_views_do_not_count(): void
    {
        $this->actingAs($this->admin());
        $this->visit('/')->assertOk();
        $this->assertSame(0, DB::table('analytics_views')->count());

        $this->actingAs($this->server->owner);
        $this->visit('/server/aden-stats')->assertOk();
        $this->assertNull(DB::table('analytics_views')->value('server_id'), 'власник не накручує собі перегляди');
    }

    public function test_visitor_classifies_sources_and_bots(): void
    {
        $source = fn (?string $referer, string $url = 'http://localhost/') => Visitor::from(Request::create($url, server: ['HTTP_USER_AGENT' => self::CHROME]), $referer)->source;

        $this->assertSame('direct', $source(null));
        $this->assertSame('search', $source('https://www.bing.com/search?q=x'));
        $this->assertSame('social', $source('https://m.facebook.com/'));
        $this->assertSame('messenger', $source('https://t.me/graj_club'));
        $this->assertSame('referral', $source('https://forum.l2top.example/thread/1'));
        $this->assertSame('internal', $source(url('/lineage2'), url('/')));
        $this->assertSame('Anthropic', Visitor::bot('Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0)'));
        $this->assertSame('Інший робот', Visitor::bot('python-requests/2.31'));
        $this->assertNull(Visitor::bot(self::CHROME));
    }

    public function test_browser_events_are_accepted_only_for_known_names(): void
    {
        $this->withHeaders(['User-Agent' => self::CHROME])->post('/a/e', ['n' => 'copy_ip', 's' => (string) $this->server->id])->assertNoContent();
        $this->withHeaders(['User-Agent' => self::CHROME])->post('/a/e', ['n' => 'vote_click', 's' => 'aden-stats'])->assertNoContent();
        $this->withHeaders(['User-Agent' => self::CHROME])->post('/a/e', ['n' => 'outbound', 'l' => 'https://www.example.org/page'])->assertNoContent();
        $this->withHeaders(['User-Agent' => self::CHROME])->post('/a/e', ['n' => 'register'])->assertNoContent(); // лише з сервера
        $this->withHeaders(['User-Agent' => self::CHROME])->post('/a/e', ['n' => 'drop table'])->assertNoContent();

        $events = DB::table('analytics_events')->orderBy('id')->get();
        $this->assertSame(['copy_ip', 'vote_click', 'outbound'], $events->pluck('name')->all());
        $this->assertSame([$this->server->id, $this->server->id], [(int) $events[0]->server_id, (int) $events[1]->server_id]);
        $this->assertSame('example.org', $events[2]->label);

        $this->withHeaders(['User-Agent' => self::CHROME])->get('/search?q=Interlude')->assertOk();
        $this->assertSame('interlude ∅', DB::table('analytics_events')->where('name', 'search')->value('label'));
    }

    public function test_rollup_builds_daily_report_with_sessions_funnel_and_server_scope(): void
    {
        // Відвідувач 1: головна → сервер → «Голосувати» (один візит із 2 переглядів)
        $this->visit('/', self::CHROME, ['Referer' => 'https://www.google.com/']);
        $this->visit('/server/aden-stats', self::CHROME, ['Referer' => url('/')]);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])->withHeaders(['User-Agent' => self::CHROME])->post('/a/e', ['n' => 'vote_click', 's' => 'aden-stats']);
        // Відвідувач 2: одразу на сервер з Telegram — відмова
        $this->visit('/server/aden-stats', self::IPHONE, ['Referer' => 'https://t.me/somechannel'], '198.51.100.9');

        $this->artisan('analytics:rollup')->assertSuccessful();

        $site = Report::period('7');
        $t = $site->totals();
        $this->assertSame([3, 2, 2, 1], [$t['views'], $t['visitors'], $t['sessions'], $t['bounces']]);
        $this->assertSame(50.0, $t['bounce_rate']);
        $this->assertEqualsCanonicalizing(['search', 'messenger'], $site->top('source')->pluck('key')->all());
        $this->assertEquals(['visit' => 2, 'server_view' => 2, 'vote_click' => 1], array_intersect_key($site->sums('funnel', 'visitors'), ['visit' => 1, 'server_view' => 1, 'vote_click' => 1]));
        $this->assertSame(3, array_sum($site->hours()));

        $server = Report::period('7', server: $this->server->id);
        $this->assertSame(2, $server->totals()['visitors']);
        // Для власника перехід з каталогу — окреме джерело
        $this->assertEqualsCanonicalizing(['internal', 'messenger'], $server->top('source')->pluck('key')->all());
        $this->assertSame(['vote_click' => 1], $server->sums('event'));

        // Повторне зведення не дублює рядки
        $before = DB::table('analytics_stats')->count();
        app(Rollup::class)->day(today());
        $this->assertSame($before, DB::table('analytics_stats')->count());

        // Старі сирі дані видаляються, зведення лишаються
        DB::table('analytics_views')->update(['created_at' => now()->subDays(200)]);
        app(Rollup::class)->prune();
        $this->assertSame(0, DB::table('analytics_views')->count());
        $this->assertSame($before, DB::table('analytics_stats')->count());
    }

    public function test_admin_analytics_page_renders_every_tab_and_exports_csv(): void
    {
        $this->visit('/server/aden-stats', self::CHROME, ['Referer' => 'https://www.google.com/']);
        $this->artisan('analytics:rollup');
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/analytics')->assertOk()->assertSee('Відвідуваність')->assertSee('Пошукові системи');

        foreach (array_keys(Analytics::TABS) as $tab) {
            Livewire::actingAs($admin)->test(Analytics::class)->call('setTab', $tab)->assertOk()->assertSet('tab', $tab);
        }
        Livewire::actingAs($admin)->test(Analytics::class)->call('setPeriod', 'custom')->set('from', today()->subDays(3)->toDateString())->assertOk();

        $csv = Livewire::actingAs($admin)->test(Analytics::class)->call('export');
        $csv->assertFileDownloaded('analytics-'.today()->subDays(29)->toDateString().'-'.today()->toDateString().'.csv');

        // Модератор без дозволу «Аналітика» сторінку не бачить
        $moderator = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $moderator->assignRole('moderator');
        $this->actingAs($moderator)->get('/admin/analytics')->assertForbidden();
    }

    public function test_owner_sees_server_analytics_in_cabinet(): void
    {
        $this->visit('/server/aden-stats', self::IPHONE, ['Referer' => 'https://discord.com/channels/1', 'CF-IPCountry' => 'PL'], '198.51.100.20');
        $this->artisan('analytics:rollup');
        $owner = $this->server->owner;
        $owner->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($owner)->get(route('cabinet.servers.analytics', [$this->server, 'period' => '7']))->assertOk()
            ->assertSee('Аналітика сервера')->assertSee('Месенджери')->assertSee('Телефон')->assertSee('discord.com');

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('cabinet.servers.analytics', $this->server))->assertForbidden();
    }
}
