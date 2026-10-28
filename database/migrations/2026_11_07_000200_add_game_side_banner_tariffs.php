<?php

use App\Models\Banner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Бічна колонка сторінки гри — окреме платне місце (раніше туди безкоштовно потрапляв сайдбар серверів).
     * Тарифи — для ігор, у стилі яких є бічна колонка; ціни — заглушки, як у TariffSeeder.
     */
    public function up(): void
    {
        $games = DB::table('games')->whereIn('theme', Banner::SIDE_COLUMN_THEMES)->pluck('id');

        foreach ($games as $gameId) {
            if (DB::table('tariffs')->where('game_id', $gameId)->where('service', 'banner')->where('placement', 'game_side')->exists()) {
                continue;
            }
            foreach ([1 => 70, 7 => 350, 30 => 1200] as $days => $price) {
                DB::table('tariffs')->insert([
                    'game_id' => $gameId,
                    'service' => 'banner',
                    'placement' => 'game_side',
                    'days' => $days,
                    'price' => $price,
                    'name' => json_encode(['uk' => "Банер у бічній колонці гри, {$days} дн.", 'en' => "Game side column banner, {$days} days"], JSON_UNESCAPED_UNICODE),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('tariffs')->where('service', 'banner')->where('placement', 'game_side')->whereNotExists(
            fn ($q) => $q->select(DB::raw(1))->from('orders')->whereColumn('orders.tariff_id', 'tariffs.id')
        )->delete();
    }
};
