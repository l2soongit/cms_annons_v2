<?php

namespace App\Http\Controllers;

use App\Billing\Billing;
use App\Billing\BillingException;
use App\Billing\GatewayRegistry;
use App\Billing\Gateways\TestGateway;
use App\Billing\Money;
use App\Billing\PaymentResult;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

/**
 * Баланс у кабінеті: поповнення через платіжну систему, історія операцій, послуги й квитанції.
 */
class BillingController extends Controller
{
    public function index(Request $request, GatewayRegistry $gateways): View
    {
        $user = $request->user();

        return view('cabinet.balance', [
            'user' => $user,
            'gateways' => $gateways->available(),
            'transactions' => $user->transactions()->with('order.tariff')->latest('id')->paginate(20),
            'orders' => $user->orders()->with(['tariff', 'server'])->latest('id')->limit(50)->get(),
        ]);
    }

    public function deposit(Request $request, Billing $billing, GatewayRegistry $gateways): View|RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'gateway' => ['required', 'string'],
        ]);

        try {
            $deposit = $billing->startDeposit($request->user(), Money::cents($data['amount']), $data['gateway']);
        } catch (BillingException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        try {
            $checkout = $gateways->get($deposit->provider)->checkout($deposit->load('user'));
        } catch (Throwable $e) {
            // Платіжна система недоступна або відхилила запит: поповнення не відбулося
            report($e);
            $deposit->update(['status' => 'failed']);

            return back()->withInput()->withErrors(['amount' => __('billing.errors.checkout')]);
        }
        if ($checkout->method === 'GET') {
            return redirect()->away($checkout->url);
        }

        // LiqPay приймає лише POST: форма надсилається одразу після завантаження
        return view('billing.redirect', ['checkout' => $checkout]);
    }

    /** Куди платіжна система повертає покупця: зарахування приходить окремим зворотним викликом */
    public function returned(): RedirectResponse
    {
        return redirect()->route('cabinet.balance')->with('status', __('billing.returned'));
    }

    /** Зворотний виклик платіжної системи (без сесії й CSRF; довіра — лише підпису) */
    public function callback(Request $request, string $gateway, GatewayRegistry $gateways, Billing $billing): Response
    {
        $driver = $gateways->get($gateway);
        $result = $driver?->parseCallback($request);
        abort_unless($result !== null, 400);

        $billing->completeDeposit($driver->key(), $result);

        return response('OK');
    }

    public function receipt(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id || $request->user()->can('billing.manage'), 404);

        return view('billing.receipt', ['order' => $order->load(['tariff', 'server', 'user', 'promoCode'])]);
    }

    /** Тестова сторінка оплати: лише локально, посилання підписане */
    public function testPage(Transaction $transaction, TestGateway $gateway): View
    {
        abort_unless($gateway->isAvailable() && $transaction->provider === 'test', 404);

        return view('billing.test', [
            'transaction' => $transaction,
            'sign' => fn (string $status) => $gateway->sign($transaction->id, $status),
            'succeeded' => PaymentResult::SUCCEEDED,
            'failed' => PaymentResult::FAILED,
        ]);
    }

    public function testPay(Request $request, TestGateway $gateway, Billing $billing): RedirectResponse
    {
        abort_unless($gateway->isAvailable(), 404);
        $result = $gateway->parseCallback($request);
        abort_unless($result !== null, 400);

        $billing->completeDeposit($gateway->key(), $result);

        return redirect()->route('cabinet.balance')->with('status', __('billing.test.done'));
    }
}
