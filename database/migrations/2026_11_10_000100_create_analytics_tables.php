<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Власна аналітика без cookies: відвідувач — хеш IP + браузера з сіллю, що змінюється щодня (як у Plausible),
     * тому людину неможливо відстежити між днями, а IP не зберігається.
     * - analytics_views — сирі перегляди сторінок (зберігаються graj.analytics.raw_retention_days);
     * - analytics_events — дії: перехід на сайт сервера, Discord, копіювання IP, голос, реєстрація…;
     * - analytics_stats — зведення по днях за вимірами (джерела, сторінки, пристрої…), для звітів за будь-який період.
     */
    public function up(): void
    {
        Schema::create('analytics_views', function (Blueprint $table) {
            $table->id();
            $table->char('visitor', 16);
            $table->string('path', 255);
            $table->string('route', 48)->nullable();
            $table->unsignedBigInteger('game_id')->nullable();
            $table->unsignedBigInteger('server_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('locale', 5)->nullable();
            $table->boolean('entry')->default(false);   // перший перегляд візиту (зайшли ззовні)
            $table->string('source', 16);               // direct, search, social, messenger, referral, campaign, internal
            $table->string('referrer', 128)->nullable(); // лише домен
            $table->string('utm_source', 64)->nullable();
            $table->string('utm_medium', 64)->nullable();
            $table->string('utm_campaign', 64)->nullable();
            $table->string('device', 8);                // desktop, mobile, tablet, bot
            $table->string('browser', 24)->nullable();
            $table->string('os', 24)->nullable();
            $table->string('lang', 8)->nullable();      // мова браузера
            $table->char('country', 2)->nullable();     // з заголовка CDN/хостингу, якщо є
            $table->timestamp('created_at')->index();

            $table->index(['visitor', 'created_at']);
            $table->index(['server_id', 'created_at']);
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->char('visitor', 16);
            $table->string('name', 24);                 // website, discord, youtube, copy_ip, vote_click, vote, outbound, search, banner_click, register, server_add
            $table->string('label', 128)->nullable();   // домен посилання, пошуковий запит…
            $table->unsignedBigInteger('game_id')->nullable();
            $table->unsignedBigInteger('server_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('source', 16)->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['name', 'created_at']);
            $table->index(['server_id', 'created_at']);
        });

        Schema::create('analytics_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('server_id')->default(0); // 0 — увесь сайт
            $table->string('dim', 16);
            $table->string('key', 191)->default('');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedInteger('entries')->default(0);
            // Лише для dim = total: візити, візити з одним переглядом, сумарна тривалість візитів у секундах
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('bounces')->default(0);
            $table->unsignedInteger('duration')->default(0);

            $table->unique(['date', 'server_id', 'dim', 'key']);
            $table->index(['server_id', 'dim', 'date']);
        });

        // Дозвіл «Аналітика» (адміністратор має його завжди)
        Permissions::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_stats');
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('analytics_views');
    }
};
