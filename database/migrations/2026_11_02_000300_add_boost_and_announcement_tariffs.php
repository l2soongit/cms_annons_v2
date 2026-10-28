<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Стартові тарифи «Підняття» й «Скоро відкриття» для вже встановленого порталу (ціни — заглушки, як у TariffSeeder).
     * Створюються лише для ігор, де таких тарифів ще немає; VIP і виділення не чіпаються.
     */
    public function up(): void
    {
        foreach (DB::table('games')->pluck('id') as $gameId) {
            $has = fn (string $service) => DB::table('tariffs')->where('game_id', $gameId)->where('service', $service)->exists();
            $rows = [];

            if (! $has('boost')) {
                foreach ([50 => 200, 150 => 500] as $points => $price) {
                    $rows[] = ['service' => 'boost', 'points' => $points, 'price' => $price,
                        'name' => ['uk' => "Підняття +{$points} балів", 'en' => "Boost +{$points} points"]];
                }
            }
            if (! $has('coming_soon')) {
                $rows[] = ['service' => 'coming_soon', 'points' => null, 'price' => 300,
                    'name' => ['uk' => 'Анонс «Скоро відкриття»', 'en' => '“Opening soon” announcement']];
            }

            foreach ($rows as $row) {
                DB::table('tariffs')->insert([
                    'game_id' => $gameId,
                    'service' => $row['service'],
                    'name' => json_encode($row['name'], JSON_UNESCAPED_UNICODE),
                    'points' => $row['points'],
                    'price' => $row['price'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
