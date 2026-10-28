<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Реліз (ТЗ 4): передстартова перевірка сервера і створення першого адміністратора.
 */
class ReleaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, GameSeeder::class]);
    }

    public function test_dev_settings_block_release(): void
    {
        config(['app.debug' => true, 'mail.default' => 'log']);

        $this->artisan('release:check')
            ->expectsOutputToContain('✗ APP_ENV=production')
            ->expectsOutputToContain('✗ APP_DEBUG=false')
            ->expectsOutputToContain('✗ Пошта надсилається')
            ->expectsOutputToContain('✗ Cron запускає schedule:run')
            ->assertFailed();
    }

    public function test_production_settings_pass_and_demo_servers_are_caught(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.debug' => false, 'app.url' => 'https://graj.club', 'session.secure' => true,
            'mail.default' => 'smtp', 'mail.from.address' => 'noreply@graj.club',
            'services.liqpay.public_key' => 'pub', 'services.liqpay.private_key' => 'priv', 'services.liqpay.sandbox' => true,
        ]);
        Cache::forever('schedule:heartbeat', now()->getTimestamp());

        $this->artisan('release:check')
            ->expectsOutputToContain('✓ APP_ENV=production')
            ->expectsOutputToContain('✓ Cron запускає schedule:run')
            ->expectsOutputToContain('✓ Пошта надсилається')
            ->expectsOutputToContain('✗ LiqPay у бойовому режимі')
            ->expectsOutputToContain('✗ Є адміністратор')
            ->expectsOutputToContain('✓ Немає демо-серверів');

        Server::create([
            'game_id' => Game::where('slug', 'lineage2')->value('id'), 'user_id' => User::factory()->create()->id,
            'name' => 'Demo', 'slug' => 'demo', 'host' => 'demo.example.net', 'status' => ServerStatus::Published,
        ]);

        $this->artisan('release:check')->expectsOutputToContain('✗ Немає демо-серверів')->assertFailed();
    }

    public function test_make_admin_creates_or_promotes(): void
    {
        $this->artisan('user:make-admin', ['email' => 'Boss@Graj.club'])
            ->expectsQuestion('Пароль (щонайменше 12 символів)', 'long-enough-pass')
            ->expectsQuestion('Повторіть пароль', 'long-enough-pass')
            ->assertSuccessful();

        $boss = User::where('email', 'boss@graj.club')->firstOrFail();
        $this->assertTrue($boss->hasRole('admin'));
        $this->assertTrue($boss->hasVerifiedEmail());

        $player = User::factory()->create();
        $this->artisan('user:make-admin', ['email' => $player->email])->assertSuccessful();
        $this->assertTrue($player->fresh()->hasRole('admin'));

        $this->artisan('user:make-admin', ['email' => 'short@graj.club'])
            ->expectsQuestion('Пароль (щонайменше 12 символів)', 'short')
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'short@graj.club']);
    }
}
