<?php

namespace App\Billing;

/**
 * Перехід на сторінку оплати: GET-посилання або форма, яку браузер одразу надсилає (LiqPay приймає POST).
 */
final class Checkout
{
    /**
     * @param  array<string, string>  $fields
     */
    public function __construct(
        public readonly string $url,
        public readonly string $method = 'GET',
        public readonly array $fields = [],
    ) {}
}
