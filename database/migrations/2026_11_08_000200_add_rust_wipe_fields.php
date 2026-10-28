<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rust повідомляє через ключові слова Steam A2S момент останнього вайпу («born1730…») і моди («oxide», «modded»).
     * Для сторінки Rust: дні між вайпами у варіантах поля «Вайп» і два нові фільтри — рейти й розмір мапи.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('last_wiped_at')->nullable()->after('time_accel');
            $table->boolean('modded')->nullable()->after('last_wiped_at');
        });

        $rust = DB::table('games')->where('slug', 'rust')->value('id');
        if (! $rust) {
            return;
        }

        // Частота вайпу: скільки днів між вайпами (щомісячний — це форс-вайп першого четверга)
        $wipe = DB::table('game_fields')->where('game_id', $rust)->where('key', 'wipe')->first();
        if ($wipe) {
            $days = ['shhotiznia' => 7, 'raz-na-2-tizni' => 14, 'shhomisiacia' => 30];
            $options = array_map(fn ($o) => isset($days[$o['value']]) && ! isset($o['days']) ? $o + ['days' => $days[$o['value']]] : $o, json_decode($wipe->options, true) ?: []);
            DB::table('game_fields')->where('id', $wipe->id)->update(['options' => json_encode($options, JSON_UNESCAPED_UNICODE)]);
        }

        $sort = (int) DB::table('game_fields')->where('game_id', $rust)->max('sort');
        $fields = [
            'rate' => [['uk' => 'Рейти', 'en' => 'Rates'], [['value' => 'vanilla', 'label' => 'Ванілла', 'labels' => ['en' => 'Vanilla']], ['value' => 'x2', 'label' => 'x2'], ['value' => 'x3', 'label' => 'x3'], ['value' => 'x5', 'label' => 'x5'], ['value' => 'x10', 'label' => 'x10']]],
            'map_size' => [['uk' => 'Розмір мапи', 'en' => 'Map size'], [['value' => '3000', 'label' => '3000'], ['value' => '3500', 'label' => '3500'], ['value' => '4000', 'label' => '4000'], ['value' => '4500', 'label' => '4500'], ['value' => '5000', 'label' => '5000+']]],
        ];
        foreach ($fields as $key => [$label, $options]) {
            if (DB::table('game_fields')->where('game_id', $rust)->where('key', $key)->exists()) {
                continue;
            }
            DB::table('game_fields')->insert([
                'game_id' => $rust,
                'key' => $key,
                'label' => json_encode($label, JSON_UNESCAPED_UNICODE),
                'type' => 'select',
                'options' => json_encode($options, JSON_UNESCAPED_UNICODE),
                // Додані пізніше — власники вже доданих серверів не мусять їх заповнювати
                'is_required' => false,
                'is_filterable' => true,
                'sort' => ++$sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['last_wiped_at', 'modded']);
        });
    }
};
