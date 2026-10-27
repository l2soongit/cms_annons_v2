<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class SocialAuthController extends Controller
{
    public const PROVIDERS = ['google', 'discord'];

    public static function enabled(string $provider): bool
    {
        return filled(config("services.{$provider}.client_id")) && filled(config("services.{$provider}.client_secret"));
    }

    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true) && self::enabled($provider), 404);

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider);

        return ($provider === 'discord' ? $driver->scopes(['identify', 'email']) : $driver)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true) && self::enabled($provider), 404);

        try {
            $social = Socialite::driver($provider)->user();
        } catch (\Throwable) {
            return to_route('login')->with('status', __('auth.ui.social_failed'));
        }

        $email = Str::lower((string) $social->getEmail());
        $verified = $provider === 'google' || ($social->user['verified'] ?? false);

        if ($email === '' || ! $verified) {
            return to_route('login')->with('status', __('auth.ui.social_no_email'));
        }

        $column = "{$provider}_id";
        $user = User::where($column, $social->getId())->first()
            ?? User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $this->uniqueName($social->getNickname() ?: $social->getName() ?: Str::before($email, '@')),
                'email' => $email,
                'password' => Str::password(40),
                'email_verified_at' => now(),
                'locale' => app()->getLocale(),
            ]);
            $user->assignRole('player');
        }

        $user->forceFill([$column => $social->getId(), 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        if ($user->isBanned()) {
            return to_route('login')->with('status', __('vote.rejected.banned'));
        }

        // Акаунти з 2FA проходять той самий екран коду, що й при вході паролем
        if ($user->two_factor_confirmed_at) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => true]);

            return to_route('two-factor.login');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }

    private function uniqueName(string $name): string
    {
        $base = Str::limit(preg_replace('/[^\pL\pN_.\- ]/u', '', $name) ?: 'player', 28, '');
        $candidate = $base;

        while (User::where('name', $candidate)->exists()) {
            $candidate = $base.random_int(10, 9999);
        }

        return $candidate;
    }
}
