<?php

namespace App\Billing\Contracts;

use App\Billing\Checkout;
use App\Billing\PaymentResult;
use App\Models\Transaction;
use Illuminate\Http\Request;

/**
 * Платіжна система для поповнення балансу. Новий провайдер — новий клас і рядок у config/graj.php (billing.gateways);
 * логіка послуг (Billing) про провайдерів не знає.
 */
interface PaymentGateway
{
    /** Ключ у конфігу, у transactions.provider і в URL зворотного виклику */
    public function key(): string;

    /** Назва для покупця */
    public function label(): string;

    /** Ключі налаштовано — можна показувати покупцю */
    public function isAvailable(): bool;

    /** Куди і з якими полями відправити покупця для оплати поповнення */
    public function checkout(Transaction $deposit): Checkout;

    /** Розібрати зворотний виклик провайдера; null — підпис не збігся */
    public function parseCallback(Request $request): ?PaymentResult;
}
