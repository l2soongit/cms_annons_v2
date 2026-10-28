<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Перший адміністратор на робочому сервері (ТЗ 4, реліз): створює акаунт або підвищує наявний.
 * Пароль питається приховано й не потрапляє в історію shell.
 */
class MakeAdmin extends Command
{
    protected $signature = 'user:make-admin {email} {--name= : Нік для нового акаунта}';

    protected $description = 'Створити адміністратора або надати роль admin наявному акаунту';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        if (Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
            $this->error('Некоректний email');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $password = (string) $this->secret('Пароль (щонайменше 12 символів)');
            if (mb_strlen($password) < 12 || $password !== $this->secret('Повторіть пароль')) {
                $this->error('Пароль закороткий або не збігається');

                return self::FAILURE;
            }

            $user = User::forceCreate([
                'name' => $this->option('name') ?: strstr($email, '@', true),
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
            $this->info("Створено акаунт {$email}");
        }

        $user->assignRole('admin');
        $this->info("{$email} тепер адміністратор. Під час першого входу в /admin налаштуйте 2FA.");

        return self::SUCCESS;
    }
}
