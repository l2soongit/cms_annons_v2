@if (\App\Rules\Turnstile::enabled())
    @once
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce
    <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="auto"></div>
    @error('cf-turnstile-response')
        <p class="mt-1.5 text-sm text-fade">{{ $message }}</p>
    @enderror
@endif
