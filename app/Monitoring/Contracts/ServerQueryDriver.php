<?php

namespace App\Monitoring\Contracts;

use App\Monitoring\QueryResult;

interface ServerQueryDriver
{
    /**
     * Опитати сервер. Не кидає винятків: будь-яка мережева помилка = офлайн.
     */
    public function query(string $host, int $port, float $timeout): QueryResult;
}
