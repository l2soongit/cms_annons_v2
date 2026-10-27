<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique();
            $table->string('discord_id')->nullable()->unique();
            $table->string('telegram_chat_id')->nullable()->unique();
            $table->string('telegram_link_token', 32)->nullable()->unique();
            $table->json('notification_prefs')->nullable();
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('offline_notified_at')->nullable();
        });

        Schema::create('server_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['server_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_invitations');
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('offline_notified_at');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropUnique(['discord_id']);
            $table->dropUnique(['telegram_chat_id']);
            $table->dropUnique(['telegram_link_token']);
            $table->dropColumn(['google_id', 'discord_id', 'telegram_chat_id', 'telegram_link_token', 'notification_prefs']);
        });
    }
};
