<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            // Який сервер рекламується (банер купує його власник)
            $table->foreignId('server_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            // Модератор може зняти банер з показу (неприйнятне зображення); оплачені дні не повертаються автоматично
            $table->boolean('is_active')->default(true)->after('url');
            $table->string('alt', 120)->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('server_id');
            $table->dropColumn(['is_active', 'alt']);
        });
    }
};
