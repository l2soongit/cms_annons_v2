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

            // Анонс на головній до дати відкриття
            Tariff::updateOrCreate(
                ['game_id' => $game->id, 'service' => 'coming_soon'],
                ['name' => ['uk' => 'Анонс «Скоро відкриття»', 'en' => '“Opening soon” announcement'], 'price' => 300],
            );
        }
    }
}
