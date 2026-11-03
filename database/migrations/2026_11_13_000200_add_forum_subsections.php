<?php

use App\Community\ForumSections;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Структура форуму як у демо D: підрозділи в кожній грі (новини серверів, обговорення, гайди, клани),
     * у «Власникам серверів» (підтримка, пропозиції) і розділ «Скриншоти й відео». Наявні теми лишаються на місці.
     */
    public function up(): void
    {
        if (Schema::hasTable('forum_categories')) {
            ForumSections::ensureAll();
        }
    }

    public function down(): void
    {
        // Підрозділи з темами не видаляємо; порожні — прибираємо
        DB::table('forum_categories')->whereNotNull('parent_id')->where('topics_count', 0)->delete();
        DB::table('forum_categories')->where('slug', 'media')->where('topics_count', 0)->delete();
    }
};
