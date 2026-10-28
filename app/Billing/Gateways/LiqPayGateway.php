<?php

namespace App\Billing\Gateways;

use App\Billing\Checkout;
use App\Billing\Contracts\PaymentGateway;
use App\Billing\Money;
use App\Billing\PaymentResult;
use App\Models\Transaction;
use Illuminate\Http\Request;

/**
 * LiqPay (ПриватБанк), API v3: покупця відправляємо формою на сторінку checkout,
 * результат приходить POST-запитом на server_url. Підпис: base64(sha1(private_key + data + private_key)).
 */
class LiqPayGateway implements PaymentGateway
{
    private const CHECKOUT_URL = 'https://www.liqpay.ua/api/3/checkout';

    public function key(): string
    {
        return 'liqpay';
    }

    public function label(): string
    {
        return 'LiqPay';
    }

    public function isAvailable(): bool
    {
        return filled(config('services.liqpay.public_key')) && filled(config('services.liqpay.private_key'));
    }

    public function checkout(Transaction $deposit): Checkout
    {
        $params = array_filter([
            'version' => 3,
            'public_key' => config('services.liqpay.public_key'),
            'action' => 'pay',
            'amount' => Money::decimal($deposit->chargeCents()),
            'currency' => $deposit->chargeCurrency(),
            'description' => __('billing.deposit_description', ['id' => $deposit->id, 'email' => $deposit->user->email]),
            // Номер замовлення унікальний для мерчанта: id + час, щоб тестова й робоча база не перетиналися
            'order_id' => $deposit->id.'-'.$deposit->created_at->timestamp,
            'result_url' => route('billing.return'),
            'server_url' => route('billing.callback', $this->key()),
            // LiqPay має лише українську й англійську сторінку оплати
            'language' => app()->getLocale() === 'uk' ? 'uk' : 'en',
            'sandbox' => config('services.liqpay.sandbox') ? '1' : null,
        ], fn ($v) => $v !== null);

        $data = base64_encode(json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return new Checkout(self::CHECKOUT_URL, 'POST', ['data' => $data, 'signature' => $this->sign($data)]);
    }

    public function parseCallback(Request $request): ?PaymentResult
    {
        $data = (string) $request->input('data');
        if ($data === '' || ! hash_equals($this->sign($data), (string) $request->input('signature'))) {
            return null;
        }

        $payload = json_decode(base64_decode($data), true) ?: [];
        $status = match ($payload['status'] ?? null) {
            'success', 'sandbox' => PaymentResult::SUCCEEDED,
            'failure', 'error', 'reversed' => PaymentResult::FAILED,
            default => PaymentResult::PENDING,
        };

        return new PaymentResult(
            (int) strtok((string) ($payload['order_id'] ?? ''), '-'),
            $status,
            Money::cents($payload['amount'] ?? 0),
            isset($payload['payment_id']) ? (string) $payload['payment_id'] : null,
            $payload,
            isset($payload['currency']) ? (string) $payload['currency'] : null,
        );
    }

    private function sign(string $data): string
    {
        $key = (string) config('services.liqpay.private_key');

        return base64_encode(sha1($key.$data.$key, true));
    }
}
