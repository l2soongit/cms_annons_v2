<?php

namespace Tests\Feature;

use App\Backup\BackupTarget;
use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use Tests\TestCase;

/**
 * Бекап бази (ТЗ 4): дамп відновлюється в порожню базу, старі копії видаляються, копія йде поза сервер.
 */
class BackupTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('app/private/backups');
        File::deleteDirectory($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_dump_restores_into_empty_database(): void
    {
        $owner = User::factory()->create(['name' => "O'Brien; DROP TABLE users;\n--"]);
        $this->seed(GameSeeder::class);
        foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
            Server::create([
                'game_id' => Game::where('slug', 'lineage2')->value('id'),
                'user_id' => $owner->id,
                'name' => $name,
                'slug' => strtolower($name),
                'host' => strtolower($name).'.example.net',
                'status' => ServerStatus::Published,
            ]);
        }

        $this->artisan('db:backup')->assertSuccessful();

        $files = File::glob($this->dir.'/db-*.sql.gz');
        $this->assertCount(1, $files);

        $restored = new PDO('sqlite::memory:');
        $restored->exec(gzdecode(file_get_contents($files[0])));

        foreach (['users', 'games', 'servers', 'migrations'] as $table) {
            $this->assertSame(
                DB::table($table)->count(),
                (int) $restored->query("select count(*) from {$table}")->fetchColumn(),
                "Table {$table} differs after restore",
            );
        }
        $this->assertSame($owner->name, $restored->query("select name from users where id = {$owner->id}")->fetchColumn());
        $this->assertGreaterThan(0, Game::count());
    }

    public function test_old_local_copies_are_rotated(): void
    {
        File::ensureDirectoryExists($this->dir);
        touch($old = $this->dir.'/db-2020-01-01-033000.sql.gz', now()->subDays(15)->getTimestamp());
        touch($fresh = $this->dir.'/db-2020-01-02-033000.sql.gz', now()->subDays(13)->getTimestamp());

        $this->artisan('db:backup')->assertSuccessful();

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($fresh);
    }

    public function test_copy_is_uploaded_off_server_and_remote_rotated(): void
    {
        $target = new class implements BackupTarget
        {
            public array $uploaded = [];

            public ?Carbon $prunedBefore = null;

            public function upload(string $localFile): void
            {
                $this->uploaded[] = basename($localFile);
            }

            public function prune(Carbon $before): int
            {
                $this->prunedBefore = $before;

                return 2;
            }
        };
        $this->app->instance(BackupTarget::class, $target);

        $this->artisan('db:backup')->expectsOutputToContain('видалено старих віддалених копій: 2')->assertSuccessful();

        $this->assertCount(1, $target->uploaded);
        $this->assertTrue($target->prunedBefore->isSameDay(now()->subDays(14)));
    }

    public function test_upload_failure_keeps_local_copy_and_fails_the_run(): void
    {
        $this->app->instance(BackupTarget::class, new class implements BackupTarget
        {
            public function upload(string $localFile): void
            {
                throw new RuntimeException('connection refused');
            }

            public function prune(Carbon $before): int
            {
                return 0;
            }
        });

        $this->artisan('db:backup')->assertFailed();
        $this->assertCount(1, File::glob($this->dir.'/db-*.sql.gz'));
    }
}
