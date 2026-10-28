<?php

namespace Tests\Feature;

use App\Billing\Billing;
use App\Billing\BillingException;
use App\Billing\ExchangeRates;
use App\Billing\Gateways\LiqPayGateway;
use App\Billing\Gateways\TestGateway;
use App\Billing\Money;
use App\Billing\PaymentResult;
use App\Enums\ServerStatus;
use App\Filament\Resources\OrderResource;
use App\Models\Game;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\Server;
use App\Models\Tariff;
use App\Models\User;
use App\Notifications\ServiceNotice;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Баланс і платні послуги (ТЗ 3.7): поповнення через платіжну систему, оплата з балансу, промокоди,
 * VIP-місця, виділення, анонс, автопродовження, нагадування, повернення.
 */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, GameSeeder::class, TariffSeeder::class]);
        config(['services.liqpay.public_key' => 'sandbox_pub', 'services.liqpay.private_key' => 'sandbox_priv', 'services.liqpay.sandbox' => true]);
    }

    private function server(array $attributes = [], float $balance = 0): Server
    {
        $owner = User::factory()->create(['balance' => $balance]);
        $name = $attributes['name'] ?? 'Aden '.fake()->unique()->numberBetween(1, 99999);

        return Server::create($attributes + [
            'game_id' => Game::where('slug', 'lineage2')->value('id'),
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'host' => str($name)->slug().'.example.net',
            'port' => 7777,
            'status' => ServerStatus::Published,
            'is_online' => true,
            'players' => 100,
            'max_players' => 1000,
            'votes_month' => 5,
            'score' => 5,
        ]);
    }

    private function tariff(string $service, ?int $days = 30): Tariff
    {
        return Tariff::where('game_id', Game::where('slug', 'lineage2')->value('id'))->where('service', $service)
            ->when($days, fn ($q) => $q->where('days', $days))->firstOrFail();
    }

    private function liqpayCallback(array $params): array
    {
        $data = base64_encode(json_encode($params));

        return ['data' => $data, 'signature' => base64_encode(sha1('sandbox_priv'.$data.'sandbox_priv', true))];
    }

    public function test_liqpay_checkout_is_signed_and_callback_credits_balance_once(): void
    {
        $user = User::factory()->create();
        $deposit = app(Billing::class)->startDeposit($user, 50000, 'liqpay');

        $checkout = app(LiqPayGateway::class)->checkout($deposit);
        $params = json_decode(base64_decode($checkout->fields['data']), true);
        $this->assertSame('POST', $checkout->method);
        $this->assertSame(base64_encode(sha1('sandbox_priv'.$checkout->fields['data'].'sandbox_priv', true)), $checkout->fields['signature']);
        $this->assertSame(['500.00', 'UAH', '1', route('billing.callback', 'liqpay')], [$params['amount'], $params['currency'], $params['sandbox'], $params['server_url']]);

        $callback = $this->liqpayCallback(['order_id' => $params['order_id'], 'status' => 'sandbox', 'amount' => 500, 'payment_id' => 777]);
        $this->post('/api/billing/callback/liqpay', $callback)->assertOk();
        $this->post('/api/billing/callback/liqpay', $callback)->assertOk();

        $this->assertSame('500.00', $user->fresh()->balance);
        $this->assertSame(['succeeded', '777'], [$deposit->fresh()->status, $deposit->fresh()->external_id]);
    }

    public function test_liqpay_callback_with_bad_signature_or_wrong_amount_does_not_credit(): void
    {
        $user = User::factory()->create();
        $deposit = app(Billing::class)->startDeposit($user, 50000, 'liqpay');
        $orderId = $deposit->id.'-'.$deposit->created_at->timestamp;

        $forged = $this->liqpayCallback(['order_id' => $orderId, 'status' => 'success', 'amount' => 500]);
        $forged['signature'] = 'forged';
        $this->post('/api/billing/callback/liqpay', $forged)->assertStatus(400);

        $this->post('/api/billing/callback/liqpay', $this->liqpayCallback(['order_id' => $orderId, 'status' => 'success', 'amount' => 5]))->assertOk();

        $this->assertSame('0.00', $user->fresh()->balance);
        $this->assertSame('failed', $deposit->fresh()->status);
    }

    public function test_cabinet_deposit_through_test_gateway(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('cabinet.balance.deposit'), ['amount' => 250, 'gateway' => 'test']);
        $response->assertRedirectContains('/billing/test/');
        $this->actingAs($user)->get($response->headers->get('Location'))->assertOk()->assertSee('Оплатити');

        $deposit = $user->transactions()->first();
        $this->actingAs($user)->post(route('billing.test.pay'), [
            'transaction' => $deposit->id, 'status' => PaymentResult::SUCCEEDED, 'signature' => app(TestGateway::class)->sign($deposit->id, PaymentResult::SUCCEEDED),
        ])->assertRedirect(route('cabinet.balance'));

        $this->assertSame('250.00', $user->fresh()->balance);
        $this->actingAs($user)->get(route('cabinet.balance'))->assertOk()->assertSee('Поповнення')->assertSee('250,00');

        $this->actingAs($user)->post(route('cabinet.balance.deposit'), ['amount' => 10, 'gateway' => 'test'])->assertSessionHasErrors('amount');
    }

    public function test_vip_purchase_charges_balance_and_pins_server_above_ranking(): void
    {
        $server = $this->server(['name' => 'Giran VIP'], balance: 2000);

        $this->actingAs($server->owner)->post(route('cabinet.servers.orders', $server), ['tariff_id' => $this->tariff('vip')->id, 'auto_renew' => 1])
            ->assertSessionHasNoErrors();

        $order = Order::first();
        $this->assertSame(['1000.00', true], [$server->owner->fresh()->balance, $order->auto_renew]);
        $this->assertDatabaseHas('transactions', ['order_id' => $order->id, 'type' => 'charge', 'amount' => '-1000.00']);
        $this->get('/lineage2')->assertOk()->assertSee('Giran VIP');
        $this->actingAs($server->owner)->get(route('cabinet.orders.receipt', $order))->assertOk()->assertSee('000,00');

        // Повторна купівля продовжує від кінця поточної
        app(Billing::class)->purchase($server->owner, $server, $this->tariff('vip'));
        $this->assertTrue(Order::latest('id')->first()->starts_at->equalTo($order->ends_at));
    }

    public function test_purchase_fails_without_funds_and_when_vip_slots_are_taken(): void
    {
        $poor = $this->server(balance: 10);
        $this->expectExceptionMessage('Недостатньо коштів');
        app(Billing::class)->purchase($poor->owner, $poor, $this->tariff('vip'));
    }

    public function test_vip_slots_are_limited_per_game(): void
    {
        Game::where('slug', 'lineage2')->update(['vip_slots' => 1]);
        $first = $this->server(balance: 5000);
        $second = $this->server(balance: 5000);

        app(Billing::class)->purchase($first->owner, $first, $this->tariff('vip'));

        try {
            app(Billing::class)->purchase($second->owner, $second, $this->tariff('vip'));
            $this->fail('Друге VIP-місце не мало продатися');
        } catch (BillingException $e) {
            $this->assertStringContainsString('VIP-місця', $e->getMessage());
            $this->assertSame('5000.00', $second->owner->fresh()->balance);
        }
    }

    public function test_promo_code_discount_and_limits(): void
    {
        $server = $this->server(balance: 1000);
        $promo = PromoCode::create(['code' => 'START20', 'discount_percent' => 20, 'max_uses' => 1, 'service' => 'highlight']);

        $order = app(Billing::class)->purchase($server->owner, $server, $this->tariff('highlight'), 'start20');
        $this->assertSame(['400.00', 1], [$order->amount, $promo->fresh()->used]);

        $this->expectException(BillingException::class);
        app(Billing::class)->purchase($server->owner, $server, $this->tariff('highlight'), 'START20');
    }

    public function test_highlight_marks_server_in_lists(): void
    {
        $server = $this->server(['name' => 'Bright Aden'], balance: 1000);

        app(Billing::class)->purchase($server->owner, $server, $this->tariff('highlight', 7));

        $this->assertTrue($server->fresh()->isHighlighted());
        $this->assertMatchesRegularExpression('/class="srv hl"[^>]*data-name="'.$server->id.'"/', $this->get('/lineage2')->getContent());
    }

    public function test_coming_soon_needs_opening_date_and_shows_on_home(): void
    {
        $server = $this->server(['name' => 'Soon Aden'], balance: 1000);
        $tariff = $this->tariff('coming_soon', null);

        try {
            app(Billing::class)->purchase($server->owner, $server, $tariff);
            $this->fail('Анонс без дати відкриття не мав продатися');
        } catch (BillingException) {
        }

        $this->get('/')->assertDontSee('id="soon"', false);
        $server->update(['opens_at' => now()->addDays(5)]);
        $order = app(Billing::class)->purchase($server->owner, $server, $tariff);

        $this->assertTrue($order->ends_at->isSameDay(now()->addDays(5)));
        $this->get('/')->assertOk()->assertSee('id="soon"', false)->assertSee('Soon Aden');
    }

    public function test_expiry_renews_from_balance_or_notifies(): void
    {
        Notification::fake();
        $renewing = $this->server(balance: 2000);
        $ending = $this->server(balance: 2000);
        $broke = $this->server(balance: 1000);
        $billing = app(Billing::class);

        $billing->purchase($renewing->owner, $renewing, $this->tariff('highlight', 7), autoRenew: true);
        $billing->purchase($ending->owner, $ending, $this->tariff('highlight', 7));
        $billing->purchase($broke->owner, $broke, $this->tariff('vip', 30), autoRenew: true);

        $this->travel(5)->days();
        // Виділення на 7 днів — за 2 дні до кінця; VIP на 30 днів ще не нагадується
        $this->assertSame(2, $billing->remindExpiring());
        $this->assertSame(0, $billing->remindExpiring());
        Notification::assertSentTo($renewing->owner, ServiceNotice::class, fn ($n) => $n->kind === 'will_renew');
        Notification::assertSentTo($ending->owner, ServiceNotice::class, fn ($n) => $n->kind === 'expiring');

        $this->travel(26)->days();
        $billing->expire();

        // Виділення на 7 днів — 150 ₴: перша оплата й автопродовження
        $this->assertSame('1700.00', $renewing->owner->fresh()->balance);
        $this->assertSame(1, Order::where('server_id', $renewing->id)->where('status', 'active')->count());
        $this->assertTrue($renewing->fresh()->isHighlighted());
        Notification::assertSentTo($renewing->owner, ServiceNotice::class, fn ($n) => $n->kind === 'renewed');
        Notification::assertSentTo($ending->owner, ServiceNotice::class, fn ($n) => $n->kind === 'expired');
        Notification::assertSentTo($broke->owner, ServiceNotice::class, fn ($n) => $n->kind === 'renew_failed');
        $this->assertFalse($ending->fresh()->isHighlighted());
    }

    public function test_admin_cancels_order_with_refund(): void
    {
        $server = $this->server(balance: 1000);
        $order = app(Billing::class)->purchase($server->owner, $server, $this->tariff('highlight', 7));

        app(Billing::class)->cancel($order, refund: true);

        $this->assertSame(['refunded', '1000.00'], [$order->fresh()->status, $server->owner->fresh()->balance]);
        $this->assertFalse($server->fresh()->isHighlighted());
    }

    public function test_billing_admin_pages_render_for_permitted_staff_only(): void
    {
        $server = $this->server(balance: 1000);
        app(Billing::class)->purchase($server->owner, $server, $this->tariff('highlight', 7));
        $admin = User::factory()->create()->assignRole('admin');

        foreach (['/admin/tariffs', '/admin/tariffs/create', '/admin/promo-codes', '/admin/orders', '/admin/transactions', '/admin/finance', '/admin'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->actingAs(User::factory()->create()->assignRole('moderator'))->get(OrderResource::getUrl())->assertForbidden();
        $this->actingAs($server->owner)->get(route('cabinet.servers.show', $server))->assertOk()->assertSee('Просування')->assertSee('Автопродовження');
    }

    public function test_nbu_rates_are_loaded_for_deposit_currencies(): void
    {
        Http::fake(['bank.gov.ua/*' => Http::response([
            ['cc' => 'USD', 'rate' => 41.2512, 'exchangedate' => today()->format('d.m.Y')],
            ['cc' => 'EUR', 'rate' => 44.1, 'exchangedate' => today()->format('d.m.Y')],
            ['cc' => 'PLN', 'rate' => 10.3, 'exchangedate' => today()->format('d.m.Y')],
        ])]);

        $this->artisan('billing:rates')->assertSuccessful();

        $rates = app(ExchangeRates::class);
        $this->assertSame([41.2512, 44.1], [$rates->rate('USD'), $rates->rate('EUR')]);
        $this->assertNull($rates->rate('PLN'));
        $this->assertSame(['UAH', 'USD', 'EUR'], $rates->depositCurrencies());
    }

    public function test_foreign_currency_deposit_charges_currency_and_credits_hryvnias(): void
    {
        DB::table('exchange_rates')->insert(['currency' => 'USD', 'rate' => 41.25, 'rate_date' => today()->toDateString()]);
        $user = User::factory()->create();
        $deposit = app(Billing::class)->startDeposit($user, 1000, 'liqpay', 'USD');

        $this->assertSame(['412.50', 'UAH', '10.00', 'USD'], [$deposit->amount, $deposit->currency, $deposit->paid_amount, $deposit->paid_currency]);

        $params = json_decode(base64_decode(app(LiqPayGateway::class)->checkout($deposit)->fields['data']), true);
        $this->assertSame(['10.00', 'USD'], [$params['amount'], $params['currency']]);

        // Та сама сума, але в гривнях — не зараховується
        $wrong = $this->liqpayCallback(['order_id' => $params['order_id'], 'status' => 'success', 'amount' => 10, 'currency' => 'UAH']);
        $this->post('/api/billing/callback/liqpay', $wrong)->assertOk();
        $this->assertSame(['0.00', 'failed'], [$user->fresh()->balance, $deposit->fresh()->status]);

        $second = app(Billing::class)->startDeposit($user, 1000, 'liqpay', 'USD');
        $orderId = $second->id.'-'.$second->created_at->timestamp;
        $this->post('/api/billing/callback/liqpay', $this->liqpayCallback(['order_id' => $orderId, 'status' => 'success', 'amount' => 10, 'currency' => 'USD']))->assertOk();
        $this->assertSame('412.50', $user->fresh()->balance);

        $this->actingAs($user)->get(route('cabinet.balance'))->assertOk()->assertSee(Money::formatIn(1000, 'USD'), false)->assertSee('USD');
    }

    public function test_currency_without_fresh_rate_or_out_of_range_is_rejected(): void
    {
        $user = User::factory()->create();
        DB::table('exchange_rates')->insert(['currency' => 'EUR', 'rate' => 44, 'rate_date' => today()->subDays(10)->toDateString()]);

        foreach ([[1000, 'EUR', 'currency'], [1000, 'RUB', 'currency'], [100, 'UAH', 'deposit_range']] as [$cents, $currency, $error]) {
            try {
                app(Billing::class)->startDeposit($user, $cents, 'liqpay', $currency);
                $this->fail("{$currency} мав бути відхилений");
            } catch (BillingException $e) {
                $this->assertSame(__("billing.errors.{$error}", ['min' => Money::format(5000, true), 'max' => Money::format(5000000, true)]), $e->getMessage());
            }
        }

        // Межі поповнення — у гривнях після перерахунку: 1 $ = 41,25 ₴ — замало
        DB::table('exchange_rates')->insert(['currency' => 'USD', 'rate' => 41.25, 'rate_date' => today()->toDateString()]);
        $this->expectException(BillingException::class);
        app(Billing::class)->startDeposit($user, 100, 'liqpay', 'USD');
    }
}
