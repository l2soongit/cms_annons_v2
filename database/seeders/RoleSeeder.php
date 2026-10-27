<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['player', 'owner', 'moderator', 'admin'] as $name) {
            Role::findOrCreate($name, 'web');
        }

        if (app()->environment('local')) {
            $admin = User::firstOrCreate(
                ['email' => 'admin@graj.club'],
                ['name' => 'Admin', 'password' => bcrypt('password'), 'email_verified_at' => now()],
            );
            $admin->assignRole('admin');
        }
    }
}
