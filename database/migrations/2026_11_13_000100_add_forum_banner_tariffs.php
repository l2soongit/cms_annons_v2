<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Рекламні місця форуму (Спільнота): фон сторінок, банери під заголовком, між розділами, у темі, бічна колонка
     * і банер розділу гри (окремо для кожної гри). Ціни — заглушки в основній валюті (USD), як у TariffSeeder.
     */
    public function up(): void
    {
        $global = [
            'forum_skin' => [[1 => 6, 7 => 28, 30 => 95], 'Фон форуму', 'Forum background'],
            'forum_top' => [[1 => 3, 7 => 14, 30 => 48], 'Банер форуму під заголовком', 'Forum top banner'],
            'forum_list' => [[1 => 1.5, 7 => 7, 30 => 24], 'Банер у списку тем форуму', 'Forum list banner'],
            'forum_side' => [[1 => 2, 7 => 9, 30 => 30], 'Банер у бічній колонці форуму', 'Forum side column banner'],
            'forum_post' => [[1 => 2, 7 => 9, 30 => 30], 'Банер у темі форуму', 'Forum topic banner'],
        ];
        foreach ($global as $placement => [$prices, $uk, $en]) {
            $this->add(null, $placement, $prices, $uk, $en);
        }
        foreach (DB::table('games')->pluck('id') as $gameId) {
            $this->add($gameId, 'forum_game', [1 => 1.5, 7 => 7, 30 => 24], 'Банер у розділі гри на форумі', 'Forum game section banner');
        }
    }

    private function add(?int $gameId, string $placement, array $prices, string $uk, string $en): void
    {
        if (DB::table('tariffs')->where('game_id', $gameId)->where('service', 'banner')->where('placement', $placement)->exists()) {
            return;
        }
        foreach ($prices as $days => $price) {
            DB::table('tariffs')->insert([
                'game_id' => $gameId,
                'service' => 'banner',
                'placement' => $placement,
                'days' => $days,
                'price' => $price,
                'name' => json_encode(['uk' => "{$uk}, {$days} дн.", 'en' => "{$en}, {$days} days"], JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tariffs')->where('service', 'banner')->where('placement', 'like', 'forum_%')->whereNotExists(
            fn ($q) => $q->select(DB::raw(1))->from('orders')->whereColumn('orders.tariff_id', 'tariffs.id')
        )->delete();
    }
};
