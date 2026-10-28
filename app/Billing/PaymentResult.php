<?php

namespace App\Billing;

/**
 * Відповідь платіжної системи про поповнення: наша транзакція, ідентифікатор у провайдера, статус і сума.
 */
final class PaymentResult
{
    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    public function __construct(
        public readonly int $transactionId,
        public readonly string $status,
        public readonly int $amountCents,
        public readonly ?string $externalId = null,
        public readonly array $payload = [],
    ) {}
}
