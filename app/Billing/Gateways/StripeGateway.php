<?php

namespace App\Billing\Gateways;

use App\Billing\Checkout;
use App\Billing\Contracts\PaymentGateway;
use App\Billing\Money;
use App\Billing\PaymentResult;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Stripe Checkout (сторінка оплати на боці Stripe). Способи оплати Stripe підбирає сам (dynamic payment methods),
 * зарахування — лише з вебхука з перевіреним підписом: checkout.session.completed і async_payment_succeeded
 * зараховують, якщо сесію оплачено; async_payment_failed і expired — відмова.
 */
class StripeGateway implements PaymentGateway
{
    /** Мітка сесій у Dashboard для порівняння потоків оплати (суфікс — 8 випадкових літер) */
    private const INTEGRATION_IDENTIFIER = 'graj_balance_topup_qkvmzrtw';

    public function key(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Stripe';
    }

    public function isAvailable(): bool
    {
        // Без секрету вебхука зарахування неможливе, тож спосіб оплати не показуємо
        return filled(config('services.stripe.secret')) && filled(config('services.stripe.webhook_secret'));
    }

    public function checkout(Transaction $deposit): Checkout
    {
        $session = $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($deposit->currency),
                    'unit_amount' => Money::cents($deposit->amount),
                    'product_data' => ['name' => __('billing.deposit_description', ['id' => $deposit->id, 'email' => $deposit->user->email])],
                ],
            ]],
            'client_reference_id' => (string) $deposit->id,
            'metadata' => ['transaction_id' => (string) $deposit->id],
            'customer_email' => $deposit->user->email,
            'locale' => app()->getLocale() === 'en' ? 'en' : 'uk',
            'success_url' => route('billing.return'),
            'cancel_url' => route('cabinet.balance'),
            'integration_identifier' => self::INTEGRATION_IDENTIFIER,
        ], ['idempotency_key' => 'graj-deposit-'.$deposit->id]);

        $deposit->update(['external_id' => $session->id]);

        return new Checkout($session->url);
    }

    public function parseCallback(Request $request): ?PaymentResult
    {
        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), (string) config('services.stripe.webhook_secret'));
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return null;
        }

        $session = $event->data->object;
        $transactionId = (int) ($session->metadata->transaction_id ?? $session->client_reference_id ?? 0);

        $status = match ($event->type) {
            // Відкладені способи оплати: completed приходить ще з unpaid — тоді чекаємо async_payment_succeeded
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => ($session->payment_status ?? 'unpaid') !== 'unpaid' ? PaymentResult::SUCCEEDED : PaymentResult::PENDING,
            'checkout.session.async_payment_failed', 'checkout.session.expired' => PaymentResult::FAILED,
            // Інші події підписані, але нас не стосуються — відповідаємо 200, нічого не змінюючи
            default => PaymentResult::PENDING,
        };

        return new PaymentResult($transactionId, $status, (int) ($session->amount_total ?? 0), $session->id ?? null, ['event' => $event->id, 'type' => $event->type]);
    }

    private function client(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }
}
