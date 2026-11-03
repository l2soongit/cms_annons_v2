<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Власна картинка фону картки автора на форумі (діє, поки активна «плюшка» «Фон картки автора»).
     * У магазині лишається один товар «Фон картки автора» замість фонів окремих ігор.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('card_bg_image')->nullable()->after('signature_image');
        });

        if (! Schema::hasTable('bonus_rewards')) {
            return;
        }
        $ids = DB::table('bonus_rewards')->where('kind', 'card_bg')->orderBy('id')->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }
        DB::table('bonus_rewards')->where('id', $ids->first())->update([
            'value' => '1',
            'name' => json_encode(['uk' => 'Фон картки автора', 'en' => 'Author card background'], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
        DB::table('bonus_rewards')->whereIn('id', $ids->slice(1)->all())->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('card_bg_image');
        });
    }
};
