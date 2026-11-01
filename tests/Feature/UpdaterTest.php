<?php

namespace Tests\Feature;

use App\Models\User;
use App\Update\Updater;
use Database\Seeders\RoleSeeder;
use FilesystemIterator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Оновлення сайту з архівів GitHub Releases: заміна файлів, збереження .env і storage, відкат, черга з адмінки, автооновлення.
 */
class UpdaterTest extends TestCase
{
    use RefreshDatabase;

    private string $site;

    protected function setUp(): void
    {
        parent::setUp();

        // Сайт, встановлений з архіву v1
        $this->site = sys_get_temp_dir().'/graj-site-'.uniqid();
        $this->file('VERSION', "v1\n");
        $this->file('artisan', '<?php exit(0);');
        $this->file('vendor/autoload.php', '<?php');
        $this->file('public/index.php', 'old index');
        $this->file('app/Old.php', 'old');
        $this->file('.env', 'APP_KEY=secret');
        $this->file('storage/app/public/logo.png', 'logo');
        $this->file('bootstrap/cache/config.php', 'stale cache');
    }

    protected function tearDown(): void
    {
        $this->rm($this->site);
        parent::tearDown();
    }

    private function file(string $rel, string $content): void
    {
        @mkdir(dirname($this->site.'/'.$rel), 0777, true);
        file_put_contents($this->site.'/'.$rel, $content);
    }

    private function rm(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }

    /** Архів релізу v2 */
    private function release(string $version = 'v2', array $extra = []): string
    {
        $file = sys_get_temp_dir().'/graj-release-'.uniqid().'.zip';
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::CREATE);
        foreach ([
            'VERSION' => "{$version}\n", 'artisan' => '<?php exit(0);', 'vendor/autoload.php' => '<?php', 'public/index.php' => 'new index',
            'app/New.php' => 'new', 'public/install.php' => 'installer', '.env' => 'APP_KEY=from-archive', ...$extra,
        ] as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $file;
    }

    /** @var list<string> команди завершення, які виконав би сайт */
    private array $ran = [];

    /** Updater із фейковим виконавцем: справжні migrate/optimize записали б кеш тестової конфігурації в робочий проєкт */
    private function updater(): Updater
    {
        return new Updater($this->site, function (string $command) {
            $this->ran[] = $command;

            return [0, ''];
        });
    }

    public function test_stage_replaces_code_but_keeps_env_storage_and_installer_out(): void
    {
        $u = $this->updater();

        $this->assertSame('v2', $u->stage($this->release(), 'manual'));

        $this->assertSame('v2', $u->currentVersion());
        $this->assertStringEqualsFile($this->site.'/public/index.php', 'new index');
        $this->assertFileExists($this->site.'/app/New.php');
        $this->assertStringEqualsFile($this->site.'/.env', 'APP_KEY=secret');
        $this->assertStringEqualsFile($this->site.'/storage/app/public/logo.png', 'logo');
        $this->assertFileDoesNotExist($this->site.'/public/install.php');
        $this->assertFileDoesNotExist($this->site.'/bootstrap/cache/config.php');
        $this->assertSame(['from' => 'v1', 'to' => 'v2'], array_intersect_key($u->pending(), ['from' => 1, 'to' => 1]));
        $this->assertFileExists($u->dir().'/'.$u->pending()['backup']);

        // Друге оновлення, поки перше не завершено, не починається
        $this->expectException(RuntimeException::class);
        $u->stage($this->release('v3'));
    }

    public function test_finalize_records_history_and_rollback_restores_previous_code(): void
    {
        $u = $this->updater();
        $u->stage($this->release(), 'manual');

        $this->assertNotEmpty($u->finalize());
        $this->assertSame(['migrate', 'optimize:clear', 'optimize'], $this->ran);
        $this->assertNull($u->pending());
        $this->assertSame(['from' => 'v1', 'to' => 'v2', 'ok' => true], array_intersect_key($u->history()[0], ['from' => 1, 'to' => 1, 'ok' => 1]));

        $this->assertSame('v1', $u->rollback());
        $u->finalize();
        $this->assertStringEqualsFile($this->site.'/public/index.php', 'old index');
        $this->assertSame('v1', $u->currentVersion());
        $this->assertStringEqualsFile($this->site.'/.env', 'APP_KEY=secret');
        $this->assertSame('rollback', $u->history()[0]['reason']);
    }

    public function test_manual_upload_clears_cache_and_queues_migrations(): void
    {
        $this->file('bootstrap/cache/routes-v7.php', 'old routes');
        $this->file('bootstrap/cache/filament/panels/admin.php', 'old pages');

        // Перший запуск нового коду після заливання архіву
        $this->assertTrue(Updater::adoptManualUpload($this->site));
        $this->assertFileDoesNotExist($this->site.'/bootstrap/cache/routes-v7.php');
        $this->assertFileDoesNotExist($this->site.'/bootstrap/cache/filament/panels/admin.php');
        $u = $this->updater();
        $this->assertSame(['to' => 'v1', 'reason' => 'upload'], array_intersect_key($u->pending(), ['to' => 1, 'reason' => 1]));

        // Далі — нічого, поки версія та сама
        $u->finalize();
        $this->assertSame(['migrate', 'optimize:clear', 'optimize'], $this->ran);
        $this->assertFalse(Updater::adoptManualUpload($this->site));
        $this->assertNull($u->pending());

        // Наступна версія, залита вручну
        $this->file('VERSION', "v2\n");
        $this->assertTrue(Updater::adoptManualUpload($this->site));
        $this->assertSame(['from' => 'v1', 'to' => 'v2'], array_intersect_key($u->pending(), ['from' => 1, 'to' => 1]));
    }

    public function test_bad_archives_and_git_checkout_are_refused(): void
    {
        $u = $this->updater();

        $bad = sys_get_temp_dir().'/graj-bad-'.uniqid().'.zip';
        file_put_contents($bad, 'not a zip');
        try {
            $u->stage($bad);
            $this->fail('пошкоджений архів прийнято');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('zip', $e->getMessage());
        }

        $zip = new ZipArchive;
        $foreign = sys_get_temp_dir().'/graj-foreign-'.uniqid().'.zip';
        $zip->open($foreign, ZipArchive::CREATE);
        $zip->addFromString('index.php', 'other site');
        $zip->close();
        try {
            $u->stage($foreign);
            $this->fail('чужий архів прийнято');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('не архів graj.club', $e->getMessage());
        }
        $this->assertStringEqualsFile($this->site.'/public/index.php', 'old index');

        mkdir($this->site.'/.git');
        $this->expectExceptionMessage('git pull');
        $u->stage($this->release());
    }

    public function test_latest_release_is_read_from_github_and_downloaded(): void
    {
        $zip = $this->release('v2026.11.02-abc1234');
        Http::fake([
            'api.github.com/repos/l2soongit/cms_annons_v2/releases/latest' => Http::response([
                'tag_name' => 'v2026.11.02-abc1234', 'name' => 'v2026.11.02-abc1234', 'body' => "- Новий слайдер\n- Ігри", 'published_at' => '2026-11-02T10:00:00Z',
                'assets' => [['name' => 'graj-club-v2026.11.02-abc1234.zip', 'url' => 'https://api.github.com/x', 'browser_download_url' => 'https://github.com/dl/graj.zip', 'size' => filesize($zip)]],
            ]),
            'github.com/dl/graj.zip' => Http::response(file_get_contents($zip)),
        ]);
        $u = $this->updater();

        $latest = $u->latest();
        $this->assertSame('v2026.11.02-abc1234', $latest['tag']);
        $this->assertTrue($u->hasUpdate());

        $this->assertSame('v2026.11.02-abc1234', $u->updateToLatest('auto'));
        $this->assertFalse($u->hasUpdate());
        $this->assertNull($u->updateToLatest(), 'та сама версія вдруге не ставиться');
    }

    public function test_cron_tick_runs_queued_upload_and_auto_update(): void
    {
        $u = $this->updater();
        $this->app->instance(Updater::class, $u);
        config(['graj.update.check_minutes' => 60]);

        // Нічого не в черзі й автооновлення вимкнено — cron нічого не робить і в GitHub не ходить
        Http::fake();
        $this->artisan('site:update --tick')->assertSuccessful();
        Http::assertNothingSent();

        // Архів, завантажений в адмінці
        $u->queue(['type' => 'file', 'file' => $this->release('v5')]);
        $this->artisan('site:update --tick')->assertSuccessful();
        $this->assertSame('v5', $u->currentVersion());
        $this->assertArrayNotHasKey('queued', $u->state());

        // Наступний запуск cron (вже з новим кодом) завершує оновлення: міграції й кеш
        $this->artisan('site:update --tick')->assertSuccessful();
        $this->assertNull($u->pending());
        $this->assertSame(['to' => 'v5', 'reason' => 'upload', 'ok' => true], array_intersect_key($u->history()[0], ['to' => 1, 'reason' => 1, 'ok' => 1]));

        // Автооновлення перевіряє GitHub не частіше за check_minutes
        $u->setAuto(true);
        $u->setState(['last_check' => time()]);
        $this->artisan('site:update --tick')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_updates_page_is_for_admins_only(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $admin->assignRole('admin');
        $moderator = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $moderator->assignRole('moderator');

        $this->actingAs($admin)->get('/admin/updates')->assertOk()->assertSee('Оновлення сайту')->assertSee('git pull');
        $this->actingAs($moderator)->get('/admin/updates')->assertForbidden();
    }
}
