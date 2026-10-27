<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('host');
            $table->unsignedInteger('port')->nullable();
            $table->string('website')->nullable();
            $table->string('discord')->nullable();
            $table->json('description')->nullable();
            $table->json('languages')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('status')->default('draft');  // draft, pending, published, rejected, hidden, blocked, archived
            $table->timestamp('verified_at')->nullable();
            $table->string('verify_token', 32)->nullable();
            $table->date('opens_at')->nullable();
            $table->string('callback_url')->nullable();
            $table->string('callback_secret', 64)->nullable();

            // Денормалізовані поля для швидкого рейтингу
            $table->boolean('is_online')->default(false);
            $table->unsignedInteger('players')->default(0);
            $table->unsignedInteger('max_players')->default(0);
            $table->decimal('uptime', 5, 2)->default(0);
            $table->unsignedInteger('votes_month')->default(0);
            $table->unsignedInteger('bonus_points')->default(0);
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->timestamp('checked_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['game_id', 'status', 'score']);
            $table->index(['status', 'opens_at']);
        });

        Schema::create('server_user', function (Blueprint $table) {
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['server_id', 'user_id']);
        });

        Schema::create('server_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_field_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->index(['game_field_id', 'value']);
        });

        Schema::create('server_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('type');                     // logo, banner, screenshot, video
            $table->string('path');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('server_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_online');
            $table->unsignedInteger('players')->default(0);
            $table->unsignedInteger('max_players')->default(0);
            $table->unsignedSmallInteger('ping_ms')->nullable();
            $table->timestamp('checked_at')->index();
            $table->index(['server_id', 'checked_at']);
        });

        Schema::create('server_stats_hourly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->dateTime('hour');
            $table->unsignedInteger('players_avg')->default(0);
            $table->unsignedInteger('players_max')->default(0);
            $table->decimal('uptime', 5, 2)->default(0);
            $table->unsignedInteger('votes')->default(0);
            $table->unique(['server_id', 'hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_stats_hourly');
        Schema::dropIfExists('server_checks');
        Schema::dropIfExists('server_media');
        Schema::dropIfExists('server_field_values');
        Schema::dropIfExists('server_user');
        Schema::dropIfExists('servers');
    }
};
