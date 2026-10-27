<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Тексти банера сторінки гри: заголовок («Lineage II») і підпис над ним; опис гри — підзаголовок
            $table->json('hero_title')->nullable()->after('name');
            $table->json('eyebrow')->nullable()->after('hero_title');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['hero_title', 'eyebrow']);
        });
    }
};
