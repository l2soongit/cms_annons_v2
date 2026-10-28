<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Поповнення в іноземній валюті: баланс і amount лишаються в гривнях,
 * а що саме списала платіжна система — у paid_amount / paid_currency за курсом rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->string('currency', 3)->primary();
            $table->decimal('rate', 14, 6);             // гривень за одиницю валюти
            $table->date('rate_date');                  // дата, на яку НБУ встановив курс
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('paid_amount', 12, 2)->nullable()->after('currency');
            $table->string('paid_currency', 3)->nullable()->after('paid_amount');
            $table->decimal('rate', 14, 6)->nullable()->after('paid_currency');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'paid_currency', 'rate']);
        });

        Schema::dropIfExists('exchange_rates');
    }
};
