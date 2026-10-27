<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Стиль гри на сайті: колір, шрифт і анімована сцена (див. Game::THEMES)
            $table->string('theme', 16)->nullable()->after('cover');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
