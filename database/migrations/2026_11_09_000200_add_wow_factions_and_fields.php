<?php

use App\Support\ContentTranslations;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Сторінка WoW («Альянс проти Орди»):
     * - онлайн за фракціями: приватні сервери WoW не відповідають на query-запити, тому баланс надсилає сам сервер через API;
     * - типи реалмів як на WoW-топах (PvP, PvE, RP, RP-PvP, FFA-PvP);
     * - нові фільтри «Контент» (Blizzlike / кастом) і «Донат» (головне, на що зважають гравці приватних серверів).
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedInteger('alliance')->nullable()->after('players');
            $table->unsignedInteger('horde')->nullable()->after('alliance');
        });

        $wow = DB::table('games')->where('slug', 'world-of-warcraft')->value('id');
        if (! $wow) {
            return;
        }

        $opt = fn (string $label, ?string $en = null) => array_filter(['value' => str($label)->slug()->value(), 'label' => $label, 'labels' => $en ? ['en' => $en] : null]);

        // Типи реалмів: старі варіанти без серверів замінюємо; вибрані власниками — лишаються
        $type = DB::table('game_fields')->where('game_id', $wow)->where('key', 'type')->first();
        if ($type) {
            $used = DB::table('server_field_values')->where('game_field_id', $type->id)->distinct()->pluck('value')->all();
            $old = collect(json_decode($type->options, true) ?: [])->filter(fn ($o) => in_array($o['value'], $used, true));
            $options = collect([$opt('PvP'), $opt('PvE'), $opt('RP'), $opt('RP-PvP'), $opt('FFA-PvP')]);
            $options = $options->concat($old->reject(fn ($o) => $options->contains('value', $o['value'])))->values()->all();
            DB::table('game_fields')->where('id', $type->id)->update(['options' => json_encode($options, JSON_UNESCAPED_UNICODE)]);
        }

        $sort = (int) DB::table('game_fields')->where('game_id', $wow)->max('sort');
        $fields = [
            'content' => [['uk' => 'Контент', 'en' => 'Content'], [$opt('Blizzlike'), $opt('Трохи кастому', 'Some custom'), $opt('Багато кастому', 'Heavy custom')]],
            'donate' => [['uk' => 'Донат', 'en' => 'Donations'], [$opt('Без донату', 'No donations'), $opt('Косметика', 'Cosmetic only'), $opt('Впливає на гру', 'Pay-to-win')]],
        ];
        foreach ($fields as $key => [$label, $options]) {
            if (DB::table('game_fields')->where('game_id', $wow)->where('key', $key)->exists()) {
                continue;
            }
            DB::table('game_fields')->insert([
                'game_id' => $wow, 'key' => $key, 'label' => json_encode($label, JSON_UNESCAPED_UNICODE), 'type' => 'select',
                'options' => json_encode($options, JSON_UNESCAPED_UNICODE),
                // Додані пізніше — власники вже доданих серверів не мусять їх заповнювати
                'is_required' => false, 'is_filterable' => true, 'in_url' => false, 'sort' => ++$sort,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        ContentTranslations::fill();
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['alliance', 'horde']);
        });

        $wow = DB::table('games')->where('slug', 'world-of-warcraft')->value('id');
        if ($wow) {
            DB::table('game_fields')->where('game_id', $wow)->whereIn('key', ['content', 'donate'])->delete();
        }
    }
};
