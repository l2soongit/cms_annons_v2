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

        if ($ok) {
            return;
        }

        $host = $this->url ? (string) parse_url((string) $value, PHP_URL_HOST) : (string) $value;

        // Домен, який просто не існує, — окреме повідомлення, щоб власник шукав друкарську помилку
        $fail(! filter_var($host, FILTER_VALIDATE_IP) && @gethostbynamel($host) === false
            ? __('validation.host_unresolved', ['host' => $host])
            : __('validation.public_address'));
    }
}
