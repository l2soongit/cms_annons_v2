<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Нагадування за кілька днів до кінця послуги надсилається один раз
            $table->timestamp('reminded_at')->nullable()->after('ends_at');
            // Продовжене замовлення посилається на попереднє, щоб не продовжити двічі
            $table->foreignId('renewed_from_id')->nullable()->after('promo_code_id')->constrained('orders')->nullOnDelete();
        });

        Schema::table('servers', function (Blueprint $table) {
            // Платне виділення в списках діє до цієї дати
            $table->timestamp('highlighted_until')->nullable()->after('bonus_points');
        });

        Schema::table('promo_codes', function (Blueprint $table) {
            // Промокод може діяти лише на одну послугу (vip, highlight…); null — на всі
            $table->string('service')->nullable()->after('discount_percent');
            $table->boolean('is_active')->default(true)->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('renewed_from_id');
            $table->dropColumn('reminded_at');
        });
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('highlighted_until');
        });
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropColumn(['service', 'is_active']);
        });
    }
};
