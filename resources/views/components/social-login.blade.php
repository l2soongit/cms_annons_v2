@php
    $providers = collect(['google' => 'Google', 'discord' => 'Discord'])
        ->filter(fn ($label, $provider) => \App\Http\Controllers\SocialAuthController::enabled($provider));
@endphp

@if ($providers->isNotEmpty())
    <div class="mt-6">
        <p class="text-center text-sm text-moss">{{ __('auth.ui.or_social') }}</p>
        <div @class(['mt-3 grid gap-2', 'sm:grid-cols-2' => $providers->count() === 2])>
            @foreach ($providers as $provider => $label)
                <x-button variant="secondary" :href="route('social.redirect', $provider)">{{ $label }}</x-button>
            @endforeach
        </div>
    </div>
@endif
