<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Game;
use App\Models\Tariff;
use Illuminate\Database\Seeder;

/**
 * Стартові тарифи. Ціни — заглушки в доларах (основна валюта нового сайту), остаточні задає адміністратор.
 * Нові тарифи в міграціях теж задаються в основній валюті: Money::base().
 */
class TariffSeeder extends Seeder
{
    public function run(): void
    {
        // Банери в шапці, сайдбарі й на сторінці голосування — спільні для всього сайту
        $labels = [
            'header' => ['uk' => 'Банер у шапці', 'en' => 'Header banner'],
            'sidebar' => ['uk' => 'Банер у сайдбарі', 'en' => 'Sidebar banner'],
            'vote' => ['uk' => 'Банер на сторінці голосування', 'en' => 'Vote page banner'],
        ];
        foreach (['header' => [1 => 4, 7 => 17, 30 => 60], 'sidebar' => [1 => 2, 7 => 10, 30 => 34], 'vote' => [1 => 2, 7 => 11, 30 => 39]] as $placement => $prices) {
            foreach ($prices as $days => $price) {
                $label = $labels[$placement];
                Tariff::updateOrCreate(
                    ['game_id' => null, 'service' => 'banner', 'placement' => $placement, 'days' => $days],
                    ['name' => ['uk' => "{$label['uk']}, {$days} дн.", 'en' => "{$label['en']}, {$days} days"], 'price' => $price],
                );
            }
        }

        // Рекламні місця форуму — спільні для всього форуму
        $forum = [
            'forum_skin' => [[1 => 6, 7 => 28, 30 => 95], 'Фон форуму', 'Forum background'],
            'forum_top' => [[1 => 3, 7 => 14, 30 => 48], 'Банер форуму під заголовком', 'Forum top banner'],
            'forum_list' => [[1 => 1.5, 7 => 7, 30 => 24], 'Банер у списку тем форуму', 'Forum list banner'],
            'forum_side' => [[1 => 2, 7 => 9, 30 => 30], 'Банер у бічній колонці форуму', 'Forum side column banner'],
            'forum_post' => [[1 => 2, 7 => 9, 30 => 30], 'Банер у темі форуму', 'Forum topic banner'],
        ];
        foreach ($forum as $placement => [$prices, $uk, $en]) {
            foreach ($prices as $days => $price) {
                Tariff::updateOrCreate(
                    ['game_id' => null, 'service' => 'banner', 'placement' => $placement, 'days' => $days],
                    ['name' => ['uk' => "{$uk}, {$days} дн.", 'en' => "{$en}, {$days} days"], 'price' => $price],
                );
            }
        }

        foreach (Game::all() as $game) {
            // Банер у розділі цієї гри на форумі
            foreach ([1 => 1.5, 7 => 7, 30 => 24] as $days => $price) {
                Tariff::updateOrCreate(
                    ['game_id' => $game->id, 'service' => 'banner', 'placement' => 'forum_game', 'days' => $days],
                    ['name' => ['uk' => "Банер у розділі гри на форумі, {$days} дн.", 'en' => "Forum game section banner, {$days} days"], 'price' => $price],
                );
            }

            foreach ([7 => 7, 30 => 24] as $days => $price) {
                Tariff::updateOrCreate(
                    ['game_id' => $game->id, 'service' => 'vip', 'days' => $days],
                    ['name' => ['uk' => "VIP на {$days} днів", 'en' => "VIP for {$days} days"], 'price' => $price],
                );
                Tariff::updateOrCreate(
                    ['game_id' => $game->id, 'service' => 'highlight', 'days' => $days],
                    ['name' => ['uk' => "Виділення на {$days} днів", 'en' => "Highlight for {$days} days"], 'price' => $price / 2],
                );
            }

            // Підняття: бонусні бали до кінця місяця
            foreach ([50 => 5, 150 => 12] as $points => $price) {
                Tariff::updateOrCreate(
                    ['game_id' => $game->id, 'service' => 'boost', 'points' => $points],
                    ['name' => ['uk' => "Підняття +{$points} балів", 'en' => "Boost +{$points} points"], 'price' => $price],
                );
            }

            // Банер на сторінці цієї гри
            foreach ([1 => 2.5, 7 => 12, 30 => 44] as $days => $price) {
                Tariff::updateOrCreate(
                    ['game_id' => $game->id, 'service' => 'banner', 'placement' => 'game', 'days' => $days],
                    ['name' => ['uk' => "Банер на сторінці гри, {$days} дн.", 'en' => "Game page banner, {$days} days"], 'price' => $price],
                );
            }

            // Бічна колонка сторінки гри — лише там, де стиль її має
            if (in_array($game->themeKey(), Banner::SIDE_COLUMN_THEMES, true)) {
                foreach ([1 => 1.7, 7 => 8.5, 30 => 29] as $days => $price) {
                    Tariff::updateOrCreate(
                        ['game_id' => $game->id, 'service' => 'banner', 'placement' => 'game_side', 'days' => $days],
                        ['name' => ['uk' => "Банер у бічній колонці гри, {$days} дн.", 'en' => "Game side column banner, {$days} days"], 'price' => $price],
                    );
                }
            }

            // Анонс на головній до дати відкриття
            Tariff::updateOrCreate(
                ['game_id' => $game->id, 'service' => 'coming_soon'],
                ['name' => ['uk' => 'Анонс «Скоро відкриття»', 'en' => '“Opening soon” announcement'], 'price' => 7],
            );
        }
    }
}
