<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Tests\TestCase;

/**
 * Сторінка «Безпека» в кабінеті: усі стани 2FA (вимкнено, підтвердження з QR, увімкнено) відкриваються.
 */
class SecurityPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_renders_in_every_two_factor_state(): void
    {
        $this->withoutExceptionHandling();
        $this->seed(RoleSeeder::class);
        config(['graj.security.require_staff_2fa' => true]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get('/cabinet/security')->assertOk();

        app(EnableTwoFactorAuthentication::class)($admin);
        $this->actingAs($admin->fresh())->withSession(['auth.password_confirmed_at' => time()])->get('/cabinet/security')->assertOk()->assertSee('<svg', false);

        // Колись дата підтвердження приходила рядком і сторінка падала з 500
        $admin->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->actingAs($admin->fresh())->withSession(['auth.password_confirmed_at' => time()])->get('/cabinet/security')->assertOk()->assertSee(now()->format('d.m.Y'));
    }
}
