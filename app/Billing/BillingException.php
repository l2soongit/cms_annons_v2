<?php

namespace App\Billing;

use RuntimeException;

/**
 * Купівлю не можна провести: повідомлення вже перекладене й показується покупцю.
 */
class BillingException extends RuntimeException
{
    public static function because(string $key, array $replace = []): self
    {
        return new self(__("billing.errors.{$key}", $replace));
    }
}
