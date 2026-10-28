<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Дані з ключових слів Steam A2S (DayZ: «lqs3,etm4.000000,…,14:20»): черга входу, ігровий час і його прискорення.
     * Для сторінки DayZ — фільтр «Перспектива» (1PP / 3PP), який задає власник сервера.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedSmallInteger('queue')->nullable()->after('max_players');
            $table->string('game_time', 5)->nullable()->after('queue');
            $table->decimal('time_accel', 5, 2)->nullable()->after('game_time');
        });

        $dayz = DB::table('games')->where('slug', 'dayz')->value('id');
        if ($dayz && ! DB::table('game_fields')->where('game_id', $dayz)->where('key', 'perspective')->exists()) {
            DB::table('game_fields')->insert([
                'game_id' => $dayz,
                'key' => 'perspective',
                'label' => json_encode(['uk' => 'Перспектива', 'en' => 'Perspective'], JSON_UNESCAPED_UNICODE),
                'type' => 'select',
                'options' => json_encode([['value' => '1pp', 'label' => '1PP'], ['value' => '3pp', 'label' => '3PP']]),
                'is_filterable' => true,
                'sort' => (int) DB::table('game_fields')->where('game_id', $dayz)->max('sort') + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['queue', 'game_time', 'time_accel']);
        });
    }
};
