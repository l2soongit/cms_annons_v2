<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Стартові тарифи банерів (ціни — заглушки, як у TariffSeeder): шапка й сайдбар — спільні,
     * сторінка гри — для кожної гри. Додаються лише там, де банерних тарифів на це місце ще немає.
     */
    public function up(): void
    {
        $rows = [];
        foreach (['header' => [1 => 150, 7 => 700, 30 => 2500], 'sidebar' => [1 => 80, 7 => 400, 30 => 1400]] as $placement => $prices) {
            if (DB::table('tariffs')->where('service', 'banner')->where('placement', $placement)->exists()) {
                continue;
            }
            $label = $placement === 'header' ? ['Банер у шапці', 'Header banner'] : ['Банер у сайдбарі', 'Sidebar banner'];
            foreach ($prices as $days => $price) {
                $rows[] = [null, $placement, $days, $price, ['uk' => "{$label[0]}, {$days} дн.", 'en' => "{$label[1]}, {$days} days"]];
            }
        }

        foreach (DB::table('games')->pluck('id') as $gameId) {
            if (DB::table('tariffs')->where('game_id', $gameId)->where('service', 'banner')->exists()) {
                continue;
            }
            foreach ([1 => 100, 7 => 500, 30 => 1800] as $days => $price) {
                $rows[] = [$gameId, 'game', $days, $price, ['uk' => "Банер на сторінці гри, {$days} дн.", 'en' => "Game page banner, {$days} days"]];
            }
        }

        foreach ($rows as [$gameId, $placement, $days, $price, $name]) {
            DB::table('tariffs')->insert([
                'game_id' => $gameId,
                'service' => 'banner',
                'placement' => $placement,
                'days' => $days,
                'price' => $price,
                'name' => json_encode($name, JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};
