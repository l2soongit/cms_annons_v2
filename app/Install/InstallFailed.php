<?php

namespace App\Install;

use RuntimeException;

/** Обов'язковий крок встановлення не вдався; журнал виконаних кроків — для сторінки інсталятора */
final class InstallFailed extends RuntimeException
{
    /** @param  list<array{string, bool, string}>  $log */
    public function __construct(public readonly array $log)
    {
        parent::__construct(end($log)[2] ?? 'Встановлення не вдалося');
    }
}
