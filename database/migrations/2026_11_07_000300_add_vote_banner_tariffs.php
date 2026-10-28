<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Банер на сторінці голосування — окреме платне місце для всього сайту (шапки там немає).
     * Ціни — заглушки, як у TariffSeeder.
     */
    public function up(): void
    {
        if (DB::table('tariffs')->where('service', 'banner')->where('placement', 'vote')->exists()) {
            return;
        }

        foreach ([1 => 90, 7 => 450, 30 => 1600] as $days => $price) {
            DB::table('tariffs')->insert([
                'game_id' => null,
                'service' => 'banner',
                'placement' => 'vote',
                'days' => $days,
                'price' => $price,
                'name' => json_encode(['uk' => "Банер на сторінці голосування, {$days} дн.", 'en' => "Vote page banner, {$days} days"], JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tariffs')->where('service', 'banner')->where('placement', 'vote')->whereNotExists(
            fn ($q) => $q->select(DB::raw(1))->from('orders')->whereColumn('orders.tariff_id', 'tariffs.id')
        )->delete();
    }
};
