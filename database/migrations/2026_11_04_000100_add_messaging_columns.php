<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Хто почав діалог: для ліміту нових діалогів на добу (антиспам)
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            // Останнє повідомлення: непрочитане рахується за номером, а не за часом (секунди збігаються)
            $table->unsignedBigInteger('last_message_id')->nullable()->after('last_message_at');
        });

        Schema::table('conversation_user', function (Blueprint $table) {
            $table->unsignedBigInteger('last_read_id')->nullable()->after('read_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            // Відповідь від імені підтримки (з адмінки) — навіть якщо співробітник сам автор тікета
            $table->boolean('is_support')->default(false)->after('attachment');
        });

        // Чорний список: користувач не отримує повідомлень від заблокованих
        Schema::create('user_blocks', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['user_id', 'blocked_id']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('status');
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['status', 'updated_at']);
            $table->dropColumn('closed_at');
        });
        Schema::dropIfExists('user_blocks');
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('is_support');
        });
        Schema::table('conversation_user', function (Blueprint $table) {
            $table->dropColumn('last_read_id');
        });
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('last_message_id');
        });
    }
};
