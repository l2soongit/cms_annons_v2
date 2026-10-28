<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    private const THEMES = ['lineage2' => 'l2', 'gta' => 'gta', 'minecraft' => 'mc', 'dayz' => 'dayz', 'rust' => 'rust', 'counter-strike' => 'cs'];

    /** Англійські підписи українських варіантів (та сама мапа — у міграції add_english_option_labels) */
    private const EN_LABELS = [
        'Гайдард' => 'Hardcore', 'Мультипрофа' => 'Multiclass',
        'Виживання' => 'Survival', 'Анархія' => 'Anarchy', 'Міні-ігри' => 'Minigames', 'Творчий' => 'Creative',
        'Ліцензія' => 'Licensed', 'Піратка' => 'Cracked',
        'Щотижня' => 'Weekly', 'Раз на 2 тижні' => 'Every 2 weeks', 'Щомісяця' => 'Monthly', 'Без ліміту' => 'No limit',
    ];

    public function run(): void
    {
        foreach ($this->games() as $sort => $data) {
            $fields = $data['fields'];
            unset($data['fields']);

            $game = Game::updateOrCreate(['slug' => $data['slug']], $data + ['sort' => $sort, 'theme' => self::THEMES[$data['slug']] ?? null]);

            foreach ($fields as $fieldSort => $field) {
                $game->fields()->updateOrCreate(['key' => $field['key']], $field + ['sort' => $fieldSort]);
            }
        }
    }

    private function games(): array
    {
        return [
            [
                'slug' => 'lineage2',
                'name' => ['uk' => 'Lineage 2', 'en' => 'Lineage 2'],
                'hero_title' => ['uk' => 'Lineage II', 'en' => 'Lineage II'],
                'eyebrow' => ['uk' => 'Фентезі MMORPG · з 2003 року', 'en' => 'Fantasy MMORPG · since 2003'],
                'description' => [
                    'uk' => 'Облоги замків, клани й олімпіади. Обери хроніки та рейти — ми покажемо, де зараз найбільше гравців.',
                    'en' => 'Castle sieges, clans and the Olympiad. Pick a chronicle and rates — we will show where most players are right now.',
                ],
                'query_driver' => 'tcp',
                'default_port' => 7777,
                'fields' => [
                    $this->select('chronicle', ['uk' => 'Хроніки', 'en' => 'Chronicle'], ['C4', 'Interlude', 'Gracia Final', 'High Five', 'Classic', 'Essence'], inUrl: true),
                    $this->select('rates', ['uk' => 'Рейти', 'en' => 'Rates'], ['x1', 'x3', 'x5', 'x10', 'x50', 'x100', 'x1000', 'x10000'], inUrl: true),
                    $this->select('type', ['uk' => 'Тип', 'en' => 'Type'], ['PvP', 'Гайдард', 'Мультипрофа', 'Craft-PvP', 'RvR']),
                ],
            ],
            [
                'slug' => 'gta',
                'name' => ['uk' => 'Grand Theft Auto', 'en' => 'Grand Theft Auto'],
                'eyebrow' => ['uk' => 'Рольові й мультиплеєрні сервери', 'en' => 'Roleplay and multiplayer servers'],
                'description' => [
                    'uk' => 'Своє місто, своя історія: від суворого RP із фракціями до драйвового DM. Обери платформу й режим — покажемо, де вирує життя.',
                    'en' => 'Your city, your story: from serious faction RP to fast-paced DM. Pick a platform and mode — we will show where the action is.',
                ],
                'query_driver' => 'samp',
                'default_port' => 7777,
                'fields' => [
                    // alt:V і RAGE:MP закрилися у 2026 на вимогу Take-Two; FiveM — єдина дозволена платформа для GTA V
                    $this->select('platform', ['uk' => 'Платформа', 'en' => 'Platform'], ['SA-MP', 'FiveM'], inUrl: true,
                        drivers: ['SA-MP' => 'samp', 'FiveM' => 'fivem'],
                        // FiveM приймає команду в консолі F8, інші клієнти — адресу
                        extras: ['SA-MP' => ['color' => '#eab308'], 'FiveM' => ['color' => '#f97316', 'connect' => 'connect {address}']]),
                    $this->select('roleplay', ['uk' => 'Режим', 'en' => 'Mode'], ['RP', 'Freeroam', 'DM']),
                ],
            ],
            [
                'slug' => 'minecraft',
                'name' => ['uk' => 'Minecraft', 'en' => 'Minecraft'],
                'eyebrow' => ['uk' => 'Пісочниця · Java і Bedrock', 'en' => 'Sandbox · Java and Bedrock'],
                'description' => [
                    'uk' => 'Виживання з друзями, SkyBlock, анархія чи міні-ігри — обери версію й режим, а ми покажемо сервери, де вже копають.',
                    'en' => 'Survival with friends, SkyBlock, anarchy or minigames — pick a version and mode, and we will show servers where people are already digging.',
                ],
                'query_driver' => 'minecraft',
                'default_port' => 25565,
                'page' => ['popular_field' => 'version'],
                'fields' => [
                    // Java+Bedrock (Geyser) опитується як Java: гравці Bedrock заходять через той самий сервер
                    $this->select('edition', ['uk' => 'Видання', 'en' => 'Edition'], ['Java', 'Bedrock', 'Java+Bedrock'], inUrl: true,
                        drivers: ['Java' => 'minecraft', 'Bedrock' => 'bedrock', 'Java+Bedrock' => 'minecraft'],
                        extras: ['Java' => ['color' => '#ffaa00'], 'Bedrock' => ['color' => '#55ffff'], 'Java+Bedrock' => ['short' => 'Java+Bed', 'color' => '#55ff55']]),
                    $this->select('version', ['uk' => 'Версія', 'en' => 'Version'], ['1.8', '1.12', '1.16', '1.20', '1.21']),
                    $this->select('mode', ['uk' => 'Режим', 'en' => 'Mode'], ['Виживання', 'SkyBlock', 'Анархія', 'Міні-ігри', 'Творчий']),
                    ['is_required' => false] + $this->select('license', ['uk' => 'Ліцензія', 'en' => 'License'], ['Ліцензія', 'Піратка']),
                ],
            ],
            [
                'slug' => 'dayz',
                'name' => ['uk' => 'DayZ', 'en' => 'DayZ'],
                'query_driver' => 'source',
                'default_port' => 2302,
                'fields' => [
                    $this->select('map', ['uk' => 'Мапа', 'en' => 'Map'], ['Chernarus', 'Livonia', 'Sakhal', 'Deer Isle']),
                    $this->select('style', ['uk' => 'Стиль', 'en' => 'Style'], ['PvE', 'PvP', 'RP', 'Vanilla', 'Modded']),
                    // Необов’язкове: додане пізніше, власники вже доданих серверів не мусять його заповнювати
                    ['is_required' => false] + $this->select('perspective', ['uk' => 'Перспектива', 'en' => 'Perspective'], ['1PP', '3PP']),
                    $this->boolean('mods', ['uk' => 'Моди', 'en' => 'Mods']),
                ],
            ],
            [
                'slug' => 'rust',
                'name' => ['uk' => 'Rust', 'en' => 'Rust'],
                'query_driver' => 'source',
                'default_port' => 28015,
                'fields' => [
                    $this->select('wipe', ['uk' => 'Вайп', 'en' => 'Wipe'], ['Щотижня', 'Раз на 2 тижні', 'Щомісяця']),
                    $this->select('group', ['uk' => 'Група', 'en' => 'Group'], ['Solo', 'Duo', 'Trio', 'Max 5', 'Без ліміту']),
                ],
            ],
            [
                'slug' => 'counter-strike',
                'name' => ['uk' => 'Counter-Strike', 'en' => 'Counter-Strike'],
                'eyebrow' => ['uk' => 'Тактичний шутер · CS2 і CS 1.6', 'en' => 'Tactical shooter · CS2 and CS 1.6'],
                'description' => [
                    'uk' => 'Паблік, ретейк, DM чи серф — обери версію, режим і карту. Пінг, тикрейт і живий онлайн видно одразу.',
                    'en' => 'Public, retake, DM or surf — pick a version, mode and map. Ping, tickrate and live player counts at a glance.',
                ],
                'query_driver' => 'source',
                'default_port' => 27015,
                'fields' => [
                    $this->select('version', ['uk' => 'Версія', 'en' => 'Version'], ['CS 1.6', 'CS2'], inUrl: true,
                        extras: ['CS 1.6' => ['short' => '1.6', 'note' => 'GoldSrc', 'color' => '#f2a33a', 'connect' => 'connect {address}'], 'CS2' => ['short' => '2', 'note' => 'Source 2', 'connect' => 'connect {address}']]),
                    $this->select('mode', ['uk' => 'Режим', 'en' => 'Mode'], ['Public', 'Deathmatch', 'Surf', 'Zombie', 'Retake', 'Jailbreak']),
                    // Сервер не повідомляє тикрейт у відповіді, тому його вказує власник; фільтр — пункт «128 tick» у меню закупівлі
                    ['is_required' => false, 'is_filterable' => false] + $this->select('tickrate', ['uk' => 'Тикрейт', 'en' => 'Tickrate'], ['64', '100', '128']),
                ],
            ],
        ];
    }

    /** Поле «так / ні»: галочка у формі сервера, у фільтрі — «Так» / «Ні» */
    private function boolean(string $key, array $label): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'boolean', 'options' => null, 'is_required' => false, 'is_filterable' => true, 'in_url' => false];
    }

    /**
     * @param  array<string, string>  $drivers  варіант => протокол моніторингу (перекриває драйвер гри)
     * @param  array<string, array<string, string>>  $extras  варіант => оформлення (short, note, color, connect)
     */
    private function select(string $key, array $label, array $values, bool $inUrl = false, array $drivers = [], array $extras = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'select',
            'options' => array_map(fn ($v) => array_filter([
                'value' => str($v)->slug()->value() ?: $v,
                'label' => $v,
                'driver' => $drivers[$v] ?? null,
                'labels' => isset(self::EN_LABELS[$v]) ? ['en' => self::EN_LABELS[$v]] : null,
                ...$extras[$v] ?? [],
            ]), $values),
            'is_required' => true,
            'is_filterable' => true,
            'in_url' => $inUrl,
        ];
    }
}
