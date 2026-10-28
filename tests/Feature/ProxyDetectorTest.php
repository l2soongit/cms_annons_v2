<?php

namespace Tests\Feature;

use App\Voting\ProxyDetector;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Перевірка VPN / проксі перед голосом: без ключа вимкнена, результат кешується, збій сервісу не блокує голос.
 */
class ProxyDetectorTest extends TestCase
{
    public function test_disabled_without_key_and_for_private_addresses(): void
    {
        Http::fake();

        config(['services.ipqs.key' => null]);
        $this->assertFalse(app(ProxyDetector::class)->isProxy('93.184.216.34'));

        config(['services.ipqs.key' => 'k']);
        $this->assertFalse(app(ProxyDetector::class)->isProxy('192.168.1.10'));
        $this->assertFalse(app(ProxyDetector::class)->isProxy('not-an-ip'));

        Http::assertNothingSent();
    }

    public function test_vpn_proxy_or_tor_is_detected_and_cached(): void
    {
        config(['services.ipqs.key' => 'k']);
        Http::fake([
            'ipqualityscore.com/api/json/ip/k/93.184.216.34*' => Http::response(['vpn' => true, 'proxy' => false]),
            'ipqualityscore.com/api/json/ip/k/8.8.8.8*' => Http::response(['tor' => true]),
            'ipqualityscore.com/*' => Http::response(['vpn' => false, 'proxy' => false, 'tor' => false]),
        ]);
        $detector = app(ProxyDetector::class);

        $this->assertTrue($detector->isProxy('93.184.216.34'));
        $this->assertTrue($detector->isProxy('93.184.216.34'));
        $this->assertTrue($detector->isProxy('8.8.8.8'));
        $this->assertFalse($detector->isProxy('1.1.1.1'));

        // Повторна перевірка тієї ж адреси береться з кешу
        Http::assertSentCount(3);
    }

    public function test_service_failure_does_not_block_vote(): void
    {
        config(['services.ipqs.key' => 'k']);
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->assertFalse(app(ProxyDetector::class)->isProxy('93.184.216.34'));
    }
}
