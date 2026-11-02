<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LogReader;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Сторінка «Логи» в адмінці: лише адміністратор, записи з файлу, фільтр рівня, пошук, захист від ../ і очищення.
 */
class LogsPageTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('framework/testing/logs-'.uniqid());
        File::ensureDirectoryExists($this->dir);
        File::put($this->dir.'/laravel-2026-11-02.log', implode("\n", [
            '[2026-11-02 10:00:00] production.INFO: Server synced {"id":5}',
            '[2026-11-02 10:05:00] production.ERROR: SQLSTATE[HY000] connection refused {"exception":"[object] (PDOException(code: 2002))"}',
            '[stacktrace]',
            '#0 /app/vendor/laravel/framework/src/Database/Connection.php(100): connect()',
            '[2026-11-02 10:06:00] production.WARNING: Vote callback slow',
            '',
        ]));
        File::put($this->dir.'/old.log', "[2026-11-01 09:00:00] production.INFO: Older file\n");
        touch($this->dir.'/old.log', time() - 3600);
        $this->app->instance(LogReader::class, new LogReader($this->dir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_reader_parses_entries_newest_first_with_filters(): void
    {
        $reader = app(LogReader::class);

        $this->assertSame(['laravel-2026-11-02.log', 'old.log'], array_column($reader->files(), 'name'));

        $all = $reader->read('laravel-2026-11-02.log');
        $this->assertSame(['warning', 'error', 'info'], array_column($all['entries'], 'level'));
        $this->assertSame(['warning' => 1, 'error' => 1, 'info' => 1], $all['counts']);
        // Коротке повідомлення без JSON-контексту, стек — у деталях
        $this->assertSame('SQLSTATE[HY000] connection refused', $all['entries'][1]['message']);
        $this->assertStringContainsString('Connection.php(100)', $all['entries'][1]['details']);

        $this->assertCount(1, $reader->read('laravel-2026-11-02.log', 'error')['entries']);
        $this->assertSame('Vote callback slow', $reader->read('laravel-2026-11-02.log', null, 'vote')['entries'][0]['message']);

        // Назва з ../ або чужий файл не читаються
        $this->assertNull($reader->path('../.env'));
        $this->assertNull($reader->path('missing.log'));
        $this->assertSame([], $reader->read('../../.env')['entries']);
    }

    public function test_only_admin_sees_logs_page(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');

        $this->actingAs($admin)->get('/admin/logs')->assertOk()
            ->assertSee('Логи сайту')
            ->assertSee('SQLSTATE[HY000] connection refused')
            ->assertSee('Помилка · 1');
        $this->actingAs($admin)->get('/admin/logs?level=warning')->assertOk()
            ->assertSee('Vote callback slow')->assertDontSee('SQLSTATE[HY000] connection refused');
        $this->actingAs($moderator)->get('/admin/logs')->assertForbidden();
    }

    public function test_clear_empties_file(): void
    {
        $this->assertTrue(app(LogReader::class)->clear('old.log'));
        $this->assertSame('', File::get($this->dir.'/old.log'));
        $this->assertFalse(app(LogReader::class)->clear('../old.log'));
    }
}
