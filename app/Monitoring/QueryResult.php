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
        // Minecraft: опис сервера з кодами кольорів §
        public readonly ?string $motd = null,
        // Steam A2S: ключові слова сервера (DayZ передає в них чергу, ігровий час і його прискорення)
        public readonly ?string $keywords = null,
    ) {}

    public static function offline(): self
    {
        return new self(false);
    }
}
