<?php

namespace Database\Seeders;

use App\Enums\ServerStatus;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use App\Rating\RatingCalculator;
use App\Voting\VoteService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
                    'ping_ms' => $online ? fake()->numberBetween(8, 120) : null,
                    'current_map' => $game->slug === 'counter-strike' ? fake()->randomElement(['de_dust2', 'de_mirage', 'de_inferno', 'de_nuke', 'de_ancient', 'cs_office', 'surf_utopia']) : null,
                    'vac' => $game->slug === 'counter-strike' ? fake()->boolean(70) : null,
                    'checked_at' => now()->subMinutes(fake()->numberBetween(1, 5)),
                    'callback_secret' => Str::random(40),
                    'verify_token' => Str::lower(Str::random(16)),
                ]);

                $this->seedVotes($server, $i === 0 ? fake()->numberBetween(0, 15) : fake()->numberBetween(10, 600));
                $this->seedHourlyStats($server);

                $server->fieldValues()->delete();
                foreach ($game->fields as $field) {
                    $server->fieldValues()->create([
                        'game_field_id' => $field->id,
                        'value' => fake()->randomElement($field->options)['value'],
                    ]);
                }
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

        app(RatingCalculator::class)->recalculate();
    }

    private function seedVotes(Server $server, int $count): void
    {
        $server->votes()->delete();
        $since = now()->startOfMonth();
        $span = max(1, (int) $since->diffInSeconds(now()));

        $rows = [];
        for ($n = 0; $n < $count; $n++) {
            $ip = fake()->ipv4();
            $rows[] = [
                'server_id' => $server->id,
                'ip_hash' => VoteService::hashIp($ip),
                'subnet_hash' => VoteService::hashSubnet($ip),
                'nickname' => fake()->boolean(70) ? fake()->userName() : null,
                'status' => 'counted',
                'callback_status' => 'none',
                'created_at' => $since->copy()->addSeconds(fake()->numberBetween(0, $span)),
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('votes')->insert($chunk);
        }
    }

    private function seedHourlyStats(Server $server): void
    {
        DB::table('server_stats_hourly')->where('server_id', $server->id)->delete();

        $votesPerHour = $server->votes()->pluck('created_at')
            ->countBy(fn ($at) => Carbon::parse($at)->format('Y-m-d H:00:00'));

        $rows = [];
        $base = max(1, $server->players ?: (int) ($server->max_players * 0.3));
        for ($h = 24 * 30; $h >= 1; $h--) {
            $hour = now()->subHours($h)->startOfHour();
            // Добовий цикл: пік увечері, мінімум під ранок
            $wave = 0.55 + 0.45 * sin(($hour->hour - 9) / 24 * 2 * M_PI);
            $players = (int) round($base * $wave * fake()->randomFloat(2, 0.85, 1.15));
            $rows[] = [
                'server_id' => $server->id,
                'hour' => $hour->toDateTimeString(),
                'players_avg' => $players,
                'players_max' => (int) round($players * 1.2),
                'uptime' => fake()->boolean(95) ? 100 : fake()->randomFloat(2, 50, 99),
                'votes' => $votesPerHour[$hour->toDateTimeString()] ?? 0,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('server_stats_hourly')->insert($chunk);
        }
    }
}
