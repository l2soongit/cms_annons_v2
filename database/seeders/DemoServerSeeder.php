<?php

namespace Database\Seeders;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Вигадані сервери лише для локальної розробки та прототипу.
 */
class DemoServerSeeder extends Seeder
{
    private const NAMES = [
        'lineage2' => ['Aden Legacy', 'Giran Nights', 'Elmore Classic', 'Talking Island x5', 'Oren PvP', 'Dion Craft', 'Heine Essence', 'Goddard RvR', 'Rune Interlude', 'Schuttgart x100'],
        'gta' => ['Kyiv RP', 'Odesa Life', 'Lviv Streets', 'Dnipro City RP', 'Coastline Freeroam', 'Steppe Roleplay', 'Harbor DM'],
        'minecraft' => ['Карпати SMP', 'SkyIsland', 'Bedrock Village', 'Анархія Степ', 'Blockforge', 'Minigames Hub', 'Pixel Realm'],
        'dayz' => ['Chernarus Survival', 'Livonia PvE', 'Sakhal Frost', 'Deer Isle Hardcore', 'Polissia Zone'],
        'rust' => ['Rust Solo Weekly', 'Duo Monthly Vanilla', 'Wild Coast x2', 'Sunday Wipe', 'Northern Max 5'],
        'counter-strike' => ['Public 24/7 Dust2', 'Retake Pro', 'Surf Paradise', 'Zombie Escape UA', 'Jailbreak Night'],
    ];

    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'owner@graj.club'],
            ['name' => 'Demo Owner', 'password' => bcrypt('password'), 'email_verified_at' => now()],
        );

        foreach (Game::with('fields')->get() as $game) {
            foreach (self::NAMES[$game->slug] ?? [] as $i => $name) {
                $max = fake()->randomElement([100, 200, 500, 1000, 2000]);
                $online = fake()->boolean(85);
                $votes = fake()->numberBetween(20, 4000);

                $server = Server::updateOrCreate(['slug' => Str::slug($name)], [
                    'game_id' => $game->id,
                    'user_id' => $owner->id,
                    'name' => $name,
                    'host' => Str::slug($name).'.example.net',
                    'port' => $game->default_port,
                    'website' => 'https://'.Str::slug($name).'.example.net',
                    'description' => ['uk' => fake()->realText(240), 'en' => fake()->realText(240)],
                    'status' => ServerStatus::Published,
                    'verified_at' => fake()->boolean(70) ? now() : null,
                    'opens_at' => $i === 0 ? now()->addDays(fake()->numberBetween(2, 20)) : now()->subDays(fake()->numberBetween(5, 900)),
                    'is_online' => $online,
                    'players' => $online ? fake()->numberBetween(5, $max) : 0,
                    'max_players' => $max,
                    'uptime' => fake()->randomFloat(2, 82, 100),
                    'votes_month' => $votes,
                    'score' => $votes,
                    'checked_at' => now()->subMinutes(fake()->numberBetween(1, 5)),
                ]);

                $server->fieldValues()->delete();
                foreach ($game->fields as $field) {
                    $server->fieldValues()->create([
                        'game_field_id' => $field->id,
                        'value' => fake()->randomElement($field->options)['value'],
                    ]);
                }
            }

            $rank = 1;
            foreach ($game->servers()->ranked()->get() as $server) {
                $server->updateQuietly(['rank' => $rank++]);
            }

            // Два VIP-місця в кожній грі для перегляду блоку VIP
            $vipTariff = $game->tariffs()->where('service', 'vip')->where('days', 30)->first();
            foreach ($game->servers()->inRandomOrder()->limit(2)->get() as $server) {
                $server->orders()->firstOrCreate(['tariff_id' => $vipTariff->id], [
                    'user_id' => $owner->id,
                    'amount' => $vipTariff->price,
                    'starts_at' => now()->subDays(3),
                    'ends_at' => now()->addDays(27),
                ]);
            }
        }
    }
}
