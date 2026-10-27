<?php

namespace App\Monitoring;

final class QueryResult
{
    public function __construct(
        public readonly bool $online,
        public readonly int $players = 0,
        public readonly int $maxPlayers = 0,
        public readonly ?string $name = null,
        public readonly ?int $pingMs = null,
        // Лише протоколи, що їх повідомляють (Steam A2S): поточна карта й захист VAC
        public readonly ?string $map = null,
        public readonly ?bool $vac = null,
    ) {}

    public static function offline(): self
    {
        return new self(false);
    }
}
