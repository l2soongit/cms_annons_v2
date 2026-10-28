<?php

namespace App\Messaging;

use RuntimeException;

/**
 * Повідомлення не можна надіслати: текст уже перекладений і показується користувачу.
 */
class MessagingException extends RuntimeException
{
    public static function because(string $key, array $replace = []): self
    {
        return new self(__("messages.errors.{$key}", $replace));
    }
}
