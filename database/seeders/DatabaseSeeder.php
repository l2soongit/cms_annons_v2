<?php

namespace Database\Seeders;

use App\Support\ContentTranslations;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            GameSeeder::class,
            TariffSeeder::class,
        ]);

        // Російські й польські переклади ігор, сторінок, тарифів і полів (лише там, де порожньо)
        ContentTranslations::fill();

        if (app()->environment('local')) {
            $this->call(DemoServerSeeder::class);
        }
    }
}
