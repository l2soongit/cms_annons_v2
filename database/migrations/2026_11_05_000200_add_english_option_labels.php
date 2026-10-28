<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Англійські підписи варіантів полів, що на старті були лише українською (англійська версія сайту, ТЗ 4).
     * Додаються лише там, де перекладу ще немає. Те саме — у GameSeeder для нових установок.
     */
    public const LABELS = [
        'lineage2' => ['type' => ['Гайдард' => 'Hardcore', 'Мультипрофа' => 'Multiclass']],
        'minecraft' => [
            'mode' => ['Виживання' => 'Survival', 'Анархія' => 'Anarchy', 'Міні-ігри' => 'Minigames', 'Творчий' => 'Creative'],
            'license' => ['Ліцензія' => 'Licensed', 'Піратка' => 'Cracked'],
        ],
        'rust' => [
            'wipe' => ['Щотижня' => 'Weekly', 'Раз на 2 тижні' => 'Every 2 weeks', 'Щомісяця' => 'Monthly'],
            'group' => ['Без ліміту' => 'No limit'],
        ],
    ];

    public function up(): void
    {
        foreach (self::LABELS as $slug => $fields) {
            $gameId = DB::table('games')->where('slug', $slug)->value('id');
            foreach ($gameId ? $fields : [] as $key => $labels) {
                $field = DB::table('game_fields')->where('game_id', $gameId)->where('key', $key)->first();
                if (! $field) {
                    continue;
                }
                $options = array_map(function ($o) use ($labels) {
                    if (isset($labels[$o['label'] ?? '']) && empty($o['labels']['en'])) {
                        $o['labels'] = ($o['labels'] ?? []) + ['en' => $labels[$o['label']]];
                    }

                    return $o;
                }, json_decode($field->options ?? '[]', true) ?: []);
                DB::table('game_fields')->where('id', $field->id)->update(['options' => json_encode($options, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
