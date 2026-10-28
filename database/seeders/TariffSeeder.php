<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Tariff;
use Illuminate\Database\Seeder;

/**
 * Стартові тарифи. Ціни — заглушки, остаточні задає адміністратор (відкрите питання ТЗ).
 */
class TariffSeeder extends Seeder
{
    public function run(): void
    {
        // Банери в шапці й сайдбарі — спільні для всього сайту
        foreach (['header' => [1 => 150, 7 => 700, 30 => 2500], 'sidebar' => [1 => 80, 7 => 400, 30 => 1400]] as $placement => $prices) {
            foreach ($prices as $days => $price) {
                $label = $placement === 'header' ? ['uk' => 'Банер у шапці', 'en' => 'Header banner'] : ['uk' => 'Банер у сайдбарі', 'en' => 'Sidebar banner'];
                Tariff::updateOrCreate(
                    ['game_id' => null, 'service' => 'banner', 'placement' => $placement, 'days' => $days],
                    ['name' => ['uk' => "{$label['uk']}, {$days} дн.", 'en' => "{$label['en']}, {$days} days"], 'price' => $price],
                );
            }
        }

        foreach (Game::all() as $game) {
            foreach ([7 => 300, 30 => 1000] as $days => $price) {
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
            foreach ([50 => 200, 150 => 500] as $points => $price) {
                Tariff::updateOrCreate(
                    ['game_id' => $game->id, 'service' => 'boost', 'points' => $points],
                    ['name' => ['uk' => "Підняття +{$points} балів", 'en' => "Boost +{$points} points"], 'price' => $price],
                );
            }

            // Банер на сторінці цієї гри
            foreach ([1 => 100, 7 => 500, 30 => 1800] as $days => $price) {
                Tariff::updateOrCreate(
                    ['game_id' => $game->id, 'service' => 'banner', 'placement' => 'game', 'days' => $days],
                    ['name' => ['uk' => "Банер на сторінці гри, {$days} дн.", 'en' => "Game page banner, {$days} days"], 'price' => $price],
                );
            }

            // Анонс на головній до дати відкриття
            Tariff::updateOrCreate(
                ['game_id' => $game->id, 'service' => 'coming_soon'],
                ['name' => ['uk' => 'Анонс «Скоро відкриття»', 'en' => '“Opening soon” announcement'], 'price' => 300],
            );
        }
    }
}
