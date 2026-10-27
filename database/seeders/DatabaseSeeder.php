<?php

namespace Database\Seeders;

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

        if (app()->environment('local')) {
            $this->call(DemoServerSeeder::class);
        }
    }
}
