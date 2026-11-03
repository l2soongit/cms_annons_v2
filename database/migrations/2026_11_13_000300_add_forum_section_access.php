<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Права розділів форуму: хто бачить, хто відповідає, власна модерація тем і відповідей, модератори розділу.
     * Порожні значення — як було: бачать і відповідають усі, кожна тема на перевірці, відповіді — за загальним налаштуванням.
     */
    public function up(): void
    {
        Schema::table('forum_categories', function (Blueprint $table) {
            $table->json('view_roles')->nullable()->after('post_roles'); // хто бачить; null — усі, зокрема гості
            $table->json('reply_roles')->nullable()->after('view_roles'); // хто відповідає; null — усі зареєстровані
            $table->boolean('moderate_topics')->default(true)->after('topic_types'); // нові теми спершу на перевірку
            $table->string('moderate_replies', 16)->nullable()->after('moderate_topics'); // all | newbies | none; null — загальне налаштування
        });

        Schema::create('forum_category_moderators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('forum_categories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['category_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_category_moderators');
        Schema::table('forum_categories', function (Blueprint $table) {
            $table->dropColumn(['view_roles', 'reply_roles', 'moderate_topics', 'moderate_replies']);
        });
    }
};
