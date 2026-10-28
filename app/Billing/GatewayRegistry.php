<?php

namespace App\Billing;

use App\Billing\Contracts\PaymentGateway;
use App\Billing\Gateways\LiqPayGateway;
use App\Billing\Gateways\StripeGateway;
use App\Billing\Gateways\TestGateway;

/**
 * Платіжні системи з config/graj.php (billing.gateways), у яких налаштовано ключі.
 */
class GatewayRegistry
{
    /** @var array<string, class-string<PaymentGateway>> */
    private const GATEWAYS = [
        'liqpay' => LiqPayGateway::class,
        'stripe' => StripeGateway::class,
        'test' => TestGateway::class,
    ];

    /** @return array<string, PaymentGateway> доступні покупцю, у порядку з конфігу; тестова — лише локально */
    public function available(): array
    {
        return collect([...config('graj.billing.gateways'), 'test'])
            ->unique()
            ->map(fn (string $key) => $this->get($key))
            ->filter(fn (?PaymentGateway $g) => $g?->isAvailable())
            ->keyBy(fn (PaymentGateway $g) => $g->key())
            ->all();
    }

    public function get(string $key): ?PaymentGateway
    {
        return isset(self::GATEWAYS[$key]) ? app(self::GATEWAYS[$key]) : null;
    }
}
