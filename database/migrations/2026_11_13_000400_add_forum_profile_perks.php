<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Вигляд профілю на форумі: підпис під повідомленнями (текст безкоштовно, картинка й посилання — за бали)
     * і стартові товари магазину балів для нових «плюшок» (ціни — заглушки, адміністратор змінює їх у «Магазині балів»).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('signature', 500)->nullable()->after('avatar');
            $table->string('signature_image')->nullable()->after('signature');
        });

        if (! Schema::hasTable('bonus_rewards')) {
            return;
        }
        $now = now();
        $rows = [
            ['title', 'Ветеран', ['uk' => 'Звання «Ветеран»', 'en' => 'Title “Veteran”'], 300, null],
            ['title', 'Мисливець на боса', ['uk' => 'Звання «Мисливець на боса»', 'en' => 'Title “Boss Hunter”'], 300, null],
            ['name_effect', 'rainbow', ['uk' => 'Веселковий нік', 'en' => 'Rainbow nickname'], 400, 30],
            ['name_effect', 'fire', ['uk' => 'Вогняний нік', 'en' => 'Fire nickname'], 400, 30],
            ['name_effect', 'ice', ['uk' => 'Крижаний нік', 'en' => 'Ice nickname'], 400, 30],
            ['card_bg', '1', ['uk' => 'Фон картки автора', 'en' => 'Author card background'], 250, 30],
            ['signature_plus', '1', ['uk' => 'Підпис з картинкою й посиланнями', 'en' => 'Signature with image and links'], 200, 30],
            ['gif_avatar', '1', ['uk' => 'Анімований аватар (GIF)', 'en' => 'Animated avatar (GIF)'], 500, 30],
        ];
        $sort = (int) DB::table('bonus_rewards')->max('sort');
        foreach ($rows as [$kind, $value, $name, $cost, $days]) {
            if (DB::table('bonus_rewards')->where('kind', $kind)->where('value', $value)->exists()) {
                continue;
            }
            DB::table('bonus_rewards')->insert([
                'kind' => $kind, 'value' => $value, 'name' => json_encode($name, JSON_UNESCAPED_UNICODE),
                'cost' => $cost, 'duration_days' => $days, 'audience' => 'all', 'sort' => $sort += 10,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['signature', 'signature_image']);
        });
    }
};
