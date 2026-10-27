<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    private const THEMES = ['lineage2' => 'l2', 'gta' => 'gta', 'minecraft' => 'mc', 'dayz' => 'dayz', 'rust' => 'rust', 'counter-strike' => 'cs'];

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
                'query_driver' => 'samp',
                'default_port' => 7777,
                'fields' => [
                    $this->select('platform', ['uk' => 'Платформа', 'en' => 'Platform'], ['SA-MP', 'FiveM', 'RAGE:MP', 'alt:V'], inUrl: true,
                        drivers: ['SA-MP' => 'samp', 'FiveM' => 'fivem', 'RAGE:MP' => 'tcp', 'alt:V' => 'tcp']),
                    $this->select('roleplay', ['uk' => 'Режим', 'en' => 'Mode'], ['RP', 'Freeroam', 'DM']),
                ],
            ],
            [
                'slug' => 'minecraft',
                'name' => ['uk' => 'Minecraft', 'en' => 'Minecraft'],
                'query_driver' => 'minecraft',
                'default_port' => 25565,
                'fields' => [
                    $this->select('edition', ['uk' => 'Видання', 'en' => 'Edition'], ['Java', 'Bedrock'], inUrl: true,
                        drivers: ['Java' => 'minecraft', 'Bedrock' => 'bedrock']),
                    $this->select('version', ['uk' => 'Версія', 'en' => 'Version'], ['1.8', '1.12', '1.16', '1.20', '1.21']),
                    $this->select('mode', ['uk' => 'Режим', 'en' => 'Mode'], ['Виживання', 'SkyBlock', 'Анархія', 'Міні-ігри', 'Творчий']),
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
                'query_driver' => 'source',
                'default_port' => 27015,
                'fields' => [
                    $this->select('version', ['uk' => 'Версія', 'en' => 'Version'], ['CS 1.6', 'CS2'], inUrl: true),
                    $this->select('mode', ['uk' => 'Режим', 'en' => 'Mode'], ['Public', 'Deathmatch', 'Surf', 'Zombie', 'Retake', 'Jailbreak']),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, string>  $drivers  варіант => протокол моніторингу (перекриває драйвер гри)
     */
    private function select(string $key, array $label, array $values, bool $inUrl = false, array $drivers = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'select',
            'options' => array_map(fn ($v) => array_filter([
                'value' => str($v)->slug()->value() ?: $v,
                'label' => $v,
                'driver' => $drivers[$v] ?? null,
            ]), $values),
            'is_required' => true,
            'is_filterable' => true,
            'in_url' => $inUrl,
        ];
    }
}
