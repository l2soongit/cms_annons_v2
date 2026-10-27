<?php

namespace App\Monitoring;

use App\Models\Server;
use App\Monitoring\Contracts\ServerQueryDriver;
use App\Monitoring\Drivers\BedrockDriver;
use App\Monitoring\Drivers\FiveMDriver;
use App\Monitoring\Drivers\MinecraftDriver;
use App\Monitoring\Drivers\SampDriver;
use App\Monitoring\Drivers\SourceDriver;
use App\Monitoring\Drivers\TcpDriver;

/**
 * Реєстр протоколів. Нова гра = вибір існуючого ключа в адмінці або новий клас тут.
 */
class DriverRegistry
{
    public const DRIVERS = [
        'tcp' => TcpDriver::class,
        'minecraft' => MinecraftDriver::class,
        'bedrock' => BedrockDriver::class,
        'source' => SourceDriver::class,
        'samp' => SampDriver::class,
        'fivem' => FiveMDriver::class,
    ];

    public static function options(): array
    {
        return [
            'tcp' => 'TCP-порт (лише онлайн/офлайн)',
            'minecraft' => 'Minecraft Java (Server List Ping)',
            'bedrock' => 'Minecraft Bedrock (RakNet)',
            'source' => 'Steam A2S (CS, Rust, DayZ)',
            'samp' => 'SA-MP / open.mp',
            'fivem' => 'FiveM (HTTP)',
        ];
    }

    public function for(Server $server): ServerQueryDriver
    {
        $key = $server->query_driver ?: $server->game->query_driver;

        return app(self::DRIVERS[$key] ?? TcpDriver::class);
    }
}
