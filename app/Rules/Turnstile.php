<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

/**
 * Перевірка Cloudflare Turnstile. Без TURNSTILE_SECRET (локально, у тестах) пропускає все.
 */
class Turnstile implements ValidationRule
{
    public static function enabled(): bool
    {
        return filled(config('services.turnstile.secret'));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::enabled()) {
            return;
        }

        if (blank($value)) {
            $fail(__('validation.turnstile'));

            return;
        }

        $response = Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => config('services.turnstile.secret'),
            'response' => $value,
            'remoteip' => request()->ip(),
        ]);

        if (! $response->json('success')) {
            $fail(__('validation.turnstile'));
        }
    }
}
