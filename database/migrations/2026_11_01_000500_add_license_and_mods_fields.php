<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Поля з ТЗ (3.1), яких не було на старті: ліцензія/піратка для Minecraft і моди для DayZ.
     * Додаються лише там, де гра є і поля з таким ключем ще немає.
     */
    private const FIELDS = [
        'minecraft' => ['key' => 'license', 'label' => ['uk' => 'Ліцензія', 'en' => 'License'], 'type' => 'select',
            'options' => [['value' => 'licenziia', 'label' => 'Ліцензія'], ['value' => 'piratka', 'label' => 'Піратка']]],
        'dayz' => ['key' => 'mods', 'label' => ['uk' => 'Моди', 'en' => 'Mods'], 'type' => 'boolean', 'options' => null],
    ];

    public function up(): void
    {
        foreach (self::FIELDS as $slug => $field) {
            $gameId = DB::table('games')->where('slug', $slug)->value('id');
            if (! $gameId || DB::table('game_fields')->where('game_id', $gameId)->where('key', $field['key'])->exists()) {
                continue;
            }

            DB::table('game_fields')->insert([
                'game_id' => $gameId,
                'key' => $field['key'],
                'label' => json_encode($field['label'], JSON_UNESCAPED_UNICODE),
                'type' => $field['type'],
                'options' => $field['options'] ? json_encode($field['options'], JSON_UNESCAPED_UNICODE) : null,
                'is_required' => false,
                'is_filterable' => true,
                'in_url' => false,
                'sort' => (int) DB::table('game_fields')->where('game_id', $gameId)->max('sort') + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::FIELDS as $slug => $field) {
            $gameId = DB::table('games')->where('slug', $slug)->value('id');
            DB::table('game_fields')->where('game_id', $gameId)->where('key', $field['key'])->delete();
        }
    }
};
