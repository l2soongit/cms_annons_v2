<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Permissions::sync();

        if (app()->environment('local')) {
            $admin = User::firstOrCreate(
                ['email' => 'admin@graj.club'],
                ['name' => 'Admin', 'password' => bcrypt('password'), 'email_verified_at' => now()],
            );
            $admin->assignRole('admin');
        }
    }
}
