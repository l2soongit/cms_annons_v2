<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            // Останні дані опитування для списків: поточна карта (Source/A2S), пінг від моніторингу, захист VAC
            $table->string('current_map', 64)->nullable()->after('max_players');
            $table->unsignedSmallInteger('ping_ms')->nullable()->after('current_map');
            $table->boolean('vac')->nullable()->after('ping_ms');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['current_map', 'ping_ms', 'vac']);
        });
    }
};
