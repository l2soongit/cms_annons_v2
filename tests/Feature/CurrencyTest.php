<?php

namespace Tests\Feature;

use App\Billing\Money;
use App\Filament\Pages\Settings as SettingsPage;
use App\Models\Game;
use App\Models\ModerationLog;
use App\Models\Tariff;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, GameSeeder::class, TariffSeeder::class]);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    private function vip(): Tariff
    {
        return Tariff::where('game_id', Game::where('slug', 'lineage2')->value('id'))->where('service', 'vip')->where('days', 30)->firstOrFail();
    }

    public function test_site_works_in_dollars_after_update(): void
    {
        $this->assertSame('USD', Money::base());
        $this->assertSame('24,00 $', str_replace("\u{00A0}", ' ', Money::format($this->vip()->price)));
        $this->get('/')->assertOk()->assertSee("24\u{00A0}$", false)->assertDontSee('₴');
    }

    public function test_admin_switches_currency_and_all_sums_are_converted(): void
    {
        DB::table('exchange_rates')->insert([
            ['currency' => 'USD', 'rate' => 41.25, 'rate_date' => today()->toDateString()],
            ['currency' => 'EUR', 'rate' => 44, 'rate_date' => today()->toDateString()],
        ]);
        $owner = User::factory()->create(['balance' => 100]);
        // Поповнення гривнями (412,50 ₴ → 10 $) і поповнення доларами
        $uah = Transaction::create(['user_id' => $owner->id, 'type' => 'deposit', 'amount' => 10, 'currency' => 'USD', 'paid_amount' => 412.5, 'paid_currency' => 'UAH', 'rate' => 1 / 41.25, 'status' => 'succeeded']);
        $usd = Transaction::create(['user_id' => $owner->id, 'type' => 'deposit', 'amount' => 100, 'currency' => 'USD', 'status' => 'succeeded']);
        $charge = Transaction::create(['user_id' => $owner->id, 'type' => 'charge', 'amount' => -24, 'currency' => 'USD', 'status' => 'succeeded']);

        Livewire::actingAs($this->admin)->test(SettingsPage::class)
            ->set('data.billing.currency', 'UAH')
            ->call('save')->assertHasNoErrors()->assertNotified('Валюту змінено на UAH');

        $this->assertSame('UAH', Money::base());
        $this->assertSame('4125.00', $owner->fresh()->balance);
        $this->assertSame('990.00', $this->vip()->fresh()->price);
        // Гривневе поповнення стало «в основній валюті», доларове — «в іноземній» за курсом
        $this->assertSame(['412.50', 'UAH', 'UAH', 1.0], [$uah->fresh()->amount, $uah->fresh()->currency, $uah->fresh()->paid_currency, round((float) $uah->fresh()->rate, 6)]);
        $this->assertSame(['4125.00', '100.00', 'USD', 41.25], [$usd->fresh()->amount, $usd->fresh()->paid_amount, $usd->fresh()->paid_currency, (float) $usd->fresh()->rate]);
        $this->assertSame('-990.00', $charge->fresh()->amount);
        $this->assertSame([41, 49706], [(int) config('graj.billing.min_deposit'), (int) config('graj.billing.max_deposit')]);
        $this->assertTrue(ModerationLog::where('action', 'currency_switch')->exists());

        $this->get('/')->assertOk()->assertSee("990\u{00A0}₴", false);
    }

    public function test_currency_is_not_switched_without_rate(): void
    {
        $owner = User::factory()->create(['balance' => 100]);

        Livewire::actingAs($this->admin)->test(SettingsPage::class)
            ->set('data.billing.currency', 'EUR')
            ->call('save')->assertNotified('Валюту не змінено');

        $this->assertSame('USD', Money::base());
        $this->assertSame('100.00', $owner->fresh()->balance);
        $this->assertSame('24.00', $this->vip()->fresh()->price);
    }
}
