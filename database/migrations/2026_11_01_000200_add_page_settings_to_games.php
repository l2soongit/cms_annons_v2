<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Оформлення варіантів полів, що раніше було зашите в шаблонах за підписом варіанта:
     * колір позначки, коротка назва, підпис під варіантом, команда підключення ({address}).
     */
    private const OPTIONS = [
        'gta' => ['platform' => [
            'SA-MP' => ['color' => '#eab308'],
            'FiveM' => ['color' => '#f97316', 'connect' => 'connect {address}'],
            'RAGE:MP' => ['color' => '#38bdf8'],
            'alt:V' => ['color' => '#22c55e'],
        ]],
        'counter-strike' => ['version' => [
            'CS 1.6' => ['short' => '1.6', 'note' => 'GoldSrc', 'color' => '#f2a33a', 'connect' => 'connect {address}'],
            'CS2' => ['short' => '2', 'note' => 'Source 2', 'connect' => 'connect {address}'],
        ]],
        'minecraft' => ['edition' => [
            'Java' => ['color' => '#ffaa00'],
            'Bedrock' => ['color' => '#55ffff'],
            'Java+Bedrock' => ['short' => 'Java+Bed', 'color' => '#55ff55'],
        ]],
    ];

    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Налаштування сторінки гри з адмінки: блоки, кількість анонсів, поле популярності, тексти стилю
            $table->json('page')->nullable()->after('eyebrow');
        });

        foreach (self::OPTIONS as $slug => $fields) {
            $gameId = DB::table('games')->where('slug', $slug)->value('id');
            foreach ($gameId ? $fields : [] as $key => $extras) {
                $field = DB::table('game_fields')->where('game_id', $gameId)->where('key', $key)->first();
                if (! $field) {
                    continue;
                }
                // Додаємо лише відсутнє: те, що вже змінили в адмінці, не чіпаємо
                $options = array_map(fn ($o) => isset($extras[$o['label'] ?? '']) ? $o + $extras[$o['label']] : $o, json_decode($field->options ?? '[]', true) ?: []);
                DB::table('game_fields')->where('id', $field->id)->update(['options' => json_encode($options, JSON_UNESCAPED_UNICODE)]);
            }
        }

        // Minecraft показує популярність версій, а не видань
        DB::table('games')->where('slug', 'minecraft')->whereNull('page')->update(['page' => json_encode(['popular_field' => 'version'])]);
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('page');
        });
    }
};
