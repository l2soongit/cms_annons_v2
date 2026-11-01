<?php

use App\Support\ContentTranslations;
use Database\Seeders\GameSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUGS = ['world-of-warcraft', 'perfect-world', 'aion', 'mu-online'];

    /**
     * Нові ігри: World of Warcraft, Perfect World, Aion, MU Online — зі стилями, фільтрами й перекладами.
     * Додаються лише відсутні ігри; наявні (і змінене в адмінці) не чіпаємо.
     */
    public function up(): void
    {
        (new GameSeeder)->seedMissing();
        ContentTranslations::fill();
    }

    public function down(): void
    {
        $ids = DB::table('games')->whereIn('slug', self::SLUGS)->pluck('id');
        // Гру з уже доданими серверами не видаляємо
        $free = $ids->reject(fn ($id) => DB::table('servers')->where('game_id', $id)->exists());
        DB::table('game_fields')->whereIn('game_id', $free)->delete();
        DB::table('games')->whereIn('id', $free)->delete();
    }
};
