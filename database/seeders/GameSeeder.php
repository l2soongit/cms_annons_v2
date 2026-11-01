<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    private const THEMES = ['lineage2' => 'l2', 'gta' => 'gta', 'minecraft' => 'mc', 'dayz' => 'dayz', 'rust' => 'rust', 'counter-strike' => 'cs',
        'world-of-warcraft' => 'wow', 'perfect-world' => 'pw', 'aion' => 'aion', 'mu-online' => 'mu'];

    /** Англійські підписи українських варіантів (та сама мапа — у міграції add_english_option_labels) */
    private const EN_LABELS = [
        'Гайдард' => 'Hardcore', 'Мультипрофа' => 'Multiclass',
        'Трохи кастому' => 'Some custom', 'Багато кастому' => 'Heavy custom', 'Без донату' => 'No donations', 'Косметика' => 'Cosmetic only', 'Впливає на гру' => 'Pay-to-win', 'Без ресетів' => 'Non-Reset', 'Ресети' => 'Reset', 'Гранд-ресети' => 'Grand Reset',
        'Виживання' => 'Survival', 'Анархія' => 'Anarchy', 'Міні-ігри' => 'Minigames', 'Творчий' => 'Creative',
        'Ліцензія' => 'Licensed', 'Піратка' => 'Cracked',
        'Щотижня' => 'Weekly', 'Раз на 2 тижні' => 'Every 2 weeks', 'Щомісяця' => 'Monthly', 'Без ліміту' => 'No limit', 'Ванілла' => 'Vanilla',
    ];

    public function run(): void
    {
        $this->seedGames($this->games());
    }

    /** Лише ігри, яких ще немає в базі: для міграцій на робочих базах, змінене в адмінці не чіпаємо */
    public function seedMissing(): void
    {
        $existing = Game::pluck('slug')->all();
        $this->seedGames(array_filter($this->games(), fn ($g) => ! in_array($g['slug'], $existing, true)));
    }

    /** @param  array<int, array<string, mixed>>  $games  ключ масиву — порядок гри */
    private function seedGames(array $games): void
    {
        foreach ($games as $sort => $data) {
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
                    // days — скільки днів між вайпами (сторінка Rust рахує наступний вайп)
                    $this->select('wipe', ['uk' => 'Вайп', 'en' => 'Wipe'], ['Щотижня', 'Раз на 2 тижні', 'Щомісяця'],
                        extras: ['Щотижня' => ['days' => 7], 'Раз на 2 тижні' => ['days' => 14], 'Щомісяця' => ['days' => 30]]),
                    $this->select('group', ['uk' => 'Група', 'en' => 'Group'], ['Solo', 'Duo', 'Trio', 'Max 5', 'Без ліміту']),
                    ['is_required' => false] + $this->select('rate', ['uk' => 'Рейти', 'en' => 'Rates'], ['Ванілла', 'x2', 'x3', 'x5', 'x10']),
                    ['is_required' => false] + $this->select('map_size', ['uk' => 'Розмір мапи', 'en' => 'Map size'], ['3000', '3500', '4000', '4500', '5000+']),
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
            // Приватні сервери MMORPG не відповідають на A2S чи інші запити онлайну:
            // моніторинг перевіряє, чи відкритий порт сервера авторизації (онлайн/офлайн)
            [
                'slug' => 'world-of-warcraft',
                'name' => ['uk' => 'World of Warcraft', 'en' => 'World of Warcraft'],
                'eyebrow' => ['uk' => 'Фентезі MMORPG · приватні сервери', 'en' => 'Fantasy MMORPG · private servers'],
                'description' => [
                    'uk' => 'Від класики й Burning Crusade до Wrath of the Lich King і новіших доповнень. Обери доповнення й рейти — покажемо, де збираються рейди.',
                    'en' => 'From Classic and The Burning Crusade to Wrath of the Lich King and newer expansions. Pick an expansion and rates — we will show where raids gather.',
                ],
                'query_driver' => 'tcp',
                'default_port' => 3724,
                'fields' => [
                    $this->select('expansion', ['uk' => 'Доповнення', 'en' => 'Expansion'], ['Classic 1.12', 'TBC 2.4.3', 'WotLK 3.3.5a', 'Cataclysm 4.3.4', 'MoP 5.4.8', 'WoD 6.2.4', 'Legion 7.3.5', 'BfA 8.3.7', 'Retail'], inUrl: true),
                    $this->select('rates', ['uk' => 'Рейти', 'en' => 'Rates'], ['x1', 'x3', 'x5', 'x10', 'x50', 'x100', 'x1000']),
                    $this->select('type', ['uk' => 'Тип', 'en' => 'Type'], ['PvP', 'PvE', 'RP', 'RP-PvP', 'FFA-PvP']),
                    // Головне, на що зважають гравці приватних серверів: наскільки сервер схожий на оригінал і чи можна купити перевагу
                    ['is_required' => false] + $this->select('content', ['uk' => 'Контент', 'en' => 'Content'], ['Blizzlike', 'Трохи кастому', 'Багато кастому']),
                    ['is_required' => false] + $this->select('donate', ['uk' => 'Донат', 'en' => 'Donations'], ['Без донату', 'Косметика', 'Впливає на гру']),
                ],
            ],
            [
                'slug' => 'perfect-world',
                'name' => ['uk' => 'Perfect World', 'en' => 'Perfect World'],
                'eyebrow' => ['uk' => 'Східне фентезі MMORPG · з 2005 року', 'en' => 'Eastern fantasy MMORPG · since 2005'],
                'description' => [
                    'uk' => 'Польоти, гільдійні війни за території й три раси. Обери версію й рейти — покажемо сервери, де вже воюють за землі.',
                    'en' => 'Flying, guild territory wars and three races. Pick a version and rates — we will show servers where the land wars are already on.',
                ],
                'query_driver' => 'tcp',
                'default_port' => 29000,
                'fields' => [
                    $this->select('version', ['uk' => 'Версія', 'en' => 'Version'], ['1.3.6', '1.4.4', '1.4.6', '1.5.1', '1.5.3', '1.5.5', '1.7.x'], inUrl: true),
                    $this->select('rates', ['uk' => 'Рейти', 'en' => 'Rates'], ['x1', 'x5', 'x10', 'x50', 'x100', 'x1000']),
                    $this->select('type', ['uk' => 'Тип', 'en' => 'Type'], ['PvP', 'PvE', 'Classic', 'Fun']),
                ],
            ],
            [
                'slug' => 'aion',
                'name' => ['uk' => 'Aion', 'en' => 'Aion'],
                'eyebrow' => ['uk' => 'MMORPG з крилами · Елійці проти Асмодіан', 'en' => 'MMORPG with wings · Elyos vs Asmodians'],
                'description' => [
                    'uk' => 'Повітряні бої, Безодня й облоги фортець. Обери версію й рейти — покажемо, де вирує війна рас.',
                    'en' => 'Aerial combat, the Abyss and fortress sieges. Pick a version and rates — we will show where the race war rages.',
                ],
                'query_driver' => 'tcp',
                'default_port' => 2106,
                'fields' => [
                    $this->select('version', ['uk' => 'Версія', 'en' => 'Version'], ['1.9', '2.7', '3.9', '4.6', '4.8', '5.8', '7.x', 'Classic'], inUrl: true),
                    $this->select('rates', ['uk' => 'Рейти', 'en' => 'Rates'], ['x1', 'x3', 'x5', 'x10', 'x50', 'x100']),
                    $this->select('type', ['uk' => 'Тип', 'en' => 'Type'], ['PvP', 'PvE', 'Classic', 'Fun']),
                ],
            ],
            [
                'slug' => 'mu-online',
                'name' => ['uk' => 'MU Online', 'en' => 'MU Online'],
                'eyebrow' => ['uk' => 'Хардкорна MMORPG · ресети й сезони', 'en' => 'Hardcore MMORPG · resets and seasons'],
                'description' => [
                    'uk' => 'Ресети, Blood Castle і Castle Siege. Обери сезон і рейти досвіду — покажемо, де зараз найжвавіше.',
                    'en' => 'Resets, Blood Castle and Castle Siege. Pick a season and experience rates — we will show where it is busiest right now.',
                ],
                'query_driver' => 'tcp',
                'default_port' => 44405,
                'fields' => [
                    $this->select('season', ['uk' => 'Сезон', 'en' => 'Season'], ['Season 2', 'Season 4', 'Season 6', 'Season 9', 'Season 12', 'Season 15', 'Season 17', 'Season 19'], inUrl: true),
                    $this->select('rates', ['uk' => 'Рейти', 'en' => 'Rates'], ['x10', 'x50', 'x100', 'x500', 'x1000', 'x9999']),
                    $this->select('type', ['uk' => 'Тип', 'en' => 'Type'], ['Без ресетів', 'Ресети', 'Гранд-ресети', 'PvP', 'Hard']),
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
