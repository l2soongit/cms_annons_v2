<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * alt:V (липень 2026) і RAGE:MP (серпень 2026) закрилися на вимогу Take-Two: прибираємо їх зі списку платформ GTA.
     * Значення в уже доданих серверах не чіпаємо — модератор вирішує їхню долю сам.
     */
    private const CLOSED = ['ragemp', 'altv'];

    public function up(): void
    {
        $gameId = DB::table('games')->where('slug', 'gta')->value('id');
        $field = $gameId ? DB::table('game_fields')->where('game_id', $gameId)->where('key', 'platform')->first() : null;
        if (! $field) {
            return;
        }

        $options = array_values(array_filter(json_decode($field->options ?? '[]', true) ?: [], fn ($o) => ! in_array($o['value'] ?? null, self::CLOSED, true)));
        DB::table('game_fields')->where('id', $field->id)->update(['options' => json_encode($options, JSON_UNESCAPED_UNICODE)]);
    }

    public function down(): void
    {
        //
    }
};
