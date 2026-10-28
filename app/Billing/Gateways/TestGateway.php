<?php

namespace App\Billing\Gateways;

use App\Billing\Checkout;
use App\Billing\Contracts\PaymentGateway;
use App\Billing\Money;
use App\Billing\PaymentResult;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Тестова оплата для локальної розробки й автотестів: сторінка з кнопками «Оплатити» / «Відмовитись»
 * без справжньої платіжної системи. На робочому сайті недоступна.
 */
class TestGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'test';
    }

    public function label(): string
    {
        return __('billing.test_gateway');
    }

    public function isAvailable(): bool
    {
        return app()->environment('local', 'testing');
    }

    public function checkout(Transaction $deposit): Checkout
    {
        return new Checkout(URL::signedRoute('billing.test', $deposit));
    }

    public function parseCallback(Request $request): ?PaymentResult
    {
        $id = (int) $request->input('transaction');
        $status = $request->input('status') === PaymentResult::SUCCEEDED ? PaymentResult::SUCCEEDED : PaymentResult::FAILED;

        if (! hash_equals($this->sign($id, $status), (string) $request->input('signature'))) {
            return null;
        }

        return new PaymentResult($id, $status, Money::cents(Transaction::find($id)?->amount), 'test-'.$id);
    }

    public function sign(int $transactionId, string $status): string
    {
        return hash_hmac('sha256', "{$transactionId}|{$status}", (string) config('app.key'));
    }
}
