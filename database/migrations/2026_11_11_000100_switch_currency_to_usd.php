<?php

use App\Billing\CurrencySwitch;
use App\Billing\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Основна валюта сайту — долар. Баланси, ціни тарифів, замовлення й історія операцій
     * перераховуються з гривень за курсом НБУ на день оновлення (CurrencySwitch). Далі валюту
     * можна змінити в адмінці: «Налаштування порталу» → «Гроші».
     */
    public function up(): void
    {
        // Валюту вже вибрали в адмінці — нічого не чіпаємо
        if (DB::table('settings')->where('key', 'billing.currency')->exists()) {
            return;
        }

        config(['graj.billing.currency' => 'UAH']);
        app(CurrencySwitch::class)->run('USD');
    }

    public function down(): void
    {
        if (Money::base() !== 'UAH') {
            app(CurrencySwitch::class)->run('UAH');
        }
    }
};
