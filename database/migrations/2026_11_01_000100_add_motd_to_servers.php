<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            // Опис сервера з відповіді Minecraft (Java і Bedrock) з кодами кольорів §, до двох рядків
            $table->string('motd', 300)->nullable()->after('vac');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('motd');
        });
    }
};
