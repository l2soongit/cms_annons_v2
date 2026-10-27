<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            // Зміни ключових полів чекають модератора, сервер тим часом показується зі старими даними
            $table->json('pending_changes')->nullable()->after('status');
            $table->string('rejection_reason')->nullable()->after('pending_changes');
            $table->string('youtube')->nullable()->after('discord');
            // Перекриває драйвер гри, напр. GTA: SA-MP і FiveM мають різні протоколи
            $table->string('query_driver')->nullable()->after('port');
            $table->timestamp('offline_since')->nullable()->after('checked_at');
            $table->boolean('suspicious_online')->default(false)->after('offline_since');
        });

        Schema::table('votes', function (Blueprint $table) {
            // Хеш підмережі /24 для ліміту голосів з однієї мережі
            $table->string('subnet_hash', 64)->nullable()->after('ip_hash');
            $table->index(['server_id', 'subnet_hash', 'created_at']);
        });

        Schema::create('monthly_winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('server_name');
            $table->date('month');
            $table->unsignedTinyInteger('place');
            $table->unsignedInteger('votes');

            $table->unique(['game_id', 'month', 'place']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_winners');
        Schema::table('votes', function (Blueprint $table) {
            $table->dropIndex(['server_id', 'subnet_hash', 'created_at']);
            $table->dropColumn('subnet_hash');
        });
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['pending_changes', 'rejection_reason', 'youtube', 'query_driver', 'offline_since', 'suspicious_online']);
        });
    }
};
