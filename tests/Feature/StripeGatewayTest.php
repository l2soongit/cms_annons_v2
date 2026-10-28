<?php

namespace Tests\Feature;

use App\Billing\Billing;
use App\Billing\GatewayRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Tests\TestCase;

/**
 * Stripe Checkout: сесія створюється без payment_method_types, зарахування — лише з підписаного вебхука.
 */
class StripeGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    /** Запити до Stripe API в тесті: що надіслано і що відповісти */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.secret' => 'rk_test_123', 'services.stripe.webhook_secret' => self::WEBHOOK_SECRET]);

        $test = $this;
        ApiRequestor::setHttpClient(new class($test) implements ClientInterface
        {
            public function __construct(private StripeGatewayTest $test) {}

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                $this->test->record($method, $absUrl, $params);

                return [json_encode(['id' => 'cs_test_abc', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_abc']), 200, []];
            }
        });
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(CurlClient::instance());
        parent::tearDown();
    }

    public function record(string $method, string $url, array $params): void
    {
        $this->sent[] = compact('method', 'url', 'params');
    }

    private function webhook(string $type, array $session): array
    {
        $payload = json_encode(['id' => 'evt_'.uniqid(), 'object' => 'event', 'type' => $type, 'data' => ['object' => $session + ['object' => 'checkout.session']]]);
        $time = time();

        return [$payload, 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$payload, self::WEBHOOK_SECRET)];
    }

    private function postWebhook(array $hook)
    {
        return $this->call('POST', '/api/billing/callback/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $hook[1], 'CONTENT_TYPE' => 'application/json'], $hook[0]);
    }

    public function test_checkout_session_uses_dynamic_payment_methods_and_redirects(): void
    {
        $user = User::factory()->create();
        $this->assertArrayHasKey('stripe', app(GatewayRegistry::class)->available());

        $this->actingAs($user)->post(route('cabinet.balance.deposit'), ['amount' => 300, 'gateway' => 'stripe'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_abc');

        $params = $this->sent[0]['params'];
        $this->assertStringEndsWith('/v1/checkout/sessions', $this->sent[0]['url']);
        $this->assertArrayNotHasKey('payment_method_types', $params);
        $this->assertSame(['payment', 'uah', 30000], [$params['mode'], $params['line_items'][0]['price_data']['currency'], $params['line_items'][0]['price_data']['unit_amount']]);
        $this->assertMatchesRegularExpression('/_[a-z]{8}$/', $params['integration_identifier']);
        $this->assertSame('cs_test_abc', $user->transactions()->first()->external_id);
    }

    public function test_paid_session_webhook_credits_once_and_unpaid_waits(): void
    {
        $user = User::factory()->create();
        $deposit = app(Billing::class)->startDeposit($user, 30000, 'stripe');
        $session = ['id' => 'cs_test_abc', 'metadata' => ['transaction_id' => (string) $deposit->id], 'amount_total' => 30000];

        // Відкладений спосіб оплати: completed прийшов, але ще не оплачено
        $this->postWebhook($this->webhook('checkout.session.completed', $session + ['payment_status' => 'unpaid']))->assertOk();
        $this->assertSame(['pending', '0.00'], [$deposit->fresh()->status, $user->fresh()->balance]);

        $paid = $this->webhook('checkout.session.async_payment_succeeded', $session + ['payment_status' => 'paid']);
        $this->postWebhook($paid)->assertOk();
        $this->postWebhook($paid)->assertOk();

        $this->assertSame(['succeeded', '300.00'], [$deposit->fresh()->status, $user->fresh()->balance]);
    }

    public function test_failed_payment_and_forged_or_foreign_events(): void
    {
        $user = User::factory()->create();
        $deposit = app(Billing::class)->startDeposit($user, 30000, 'stripe');
        $session = ['id' => 'cs_test_abc', 'metadata' => ['transaction_id' => (string) $deposit->id], 'amount_total' => 30000, 'payment_status' => 'paid'];

        $forged = $this->webhook('checkout.session.completed', $session);
        $forged[1] = 't='.time().',v1=forged';
        $this->postWebhook($forged)->assertStatus(400);

        $this->postWebhook($this->webhook('customer.created', ['id' => 'cus_1']))->assertOk();
        $this->postWebhook($this->webhook('checkout.session.async_payment_failed', $session))->assertOk();

        $this->assertSame(['failed', '0.00'], [$deposit->fresh()->status, $user->fresh()->balance]);
    }

    public function test_stripe_is_hidden_without_webhook_secret(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $this->assertArrayNotHasKey('stripe', app(GatewayRegistry::class)->available());
    }
}
