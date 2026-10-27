<?php

namespace App\Rules;

use App\Support\PublicHost;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PublicAddress implements ValidationRule
{
    public function __construct(private bool $url = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $ok = $this->url ? PublicHost::isPublicUrl($value) : PublicHost::isPublic((string) $value);

        if (! $ok) {
            $fail(__('validation.public_address'));
        }
    }
}
