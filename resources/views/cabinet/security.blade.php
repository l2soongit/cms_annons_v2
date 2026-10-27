<x-layouts.app :title="__('profile.nav.security').' | graj.club'">
    <x-cabinet-nav />
    <x-flash class="mb-6" />

    <section class="max-w-xl" aria-labelledby="s-2fa">
        <h2 id="s-2fa" class="font-display text-lg font-medium">{{ __('profile.security.title') }}</h2>
        <p class="mt-3 text-moss">{{ __('profile.security.lead') }}</p>

        @if ($user->two_factor_confirmed_at)
            <p class="mt-6 text-signal">{{ __('profile.security.enabled', ['date' => $user->two_factor_confirmed_at->format('d.m.Y')]) }}</p>

            <details class="mt-6">
                <summary class="cursor-pointer text-sm text-moss hover:text-bone">{{ __('profile.security.show_codes') }}</summary>
                <p class="mt-3 text-sm text-moss">{{ __('profile.security.codes_hint') }}</p>
                <ul class="mt-3 grid grid-cols-2 gap-2 rounded-md border border-seam bg-slab p-4 text-sm tabular-nums">
                    @foreach ($user->recoveryCodes() as $code)
                        <li class="select-all">{{ $code }}</li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}" class="mt-3">
                    @csrf
                    <x-button variant="ghost" class="px-0">{{ __('profile.security.regenerate_codes') }}</x-button>
                </form>
            </details>

            @unless ($user->isStaff() && config('graj.security.require_staff_2fa'))
                <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-6" onsubmit="return confirm(@js(__('profile.security.disable_confirm')))">
                    @csrf @method('DELETE')
                    <x-button variant="danger">{{ __('profile.security.disable') }}</x-button>
                </form>
            @endunless
        @elseif ($user->two_factor_secret)
            {{-- Секрет створено, чекаємо підтвердження кодом --}}
            <ol class="mt-6 list-decimal space-y-2 pl-5 text-sm">
                <li>{{ __('profile.security.step_scan') }}</li>
                <li>{{ __('profile.security.step_code') }}</li>
            </ol>
            <div class="mt-5 inline-block rounded-md bg-white p-3">{!! $user->twoFactorQrCodeSvg() !!}</div>
            <details class="mt-3 text-sm">
                <summary class="cursor-pointer text-moss hover:text-bone">{{ __('profile.security.manual_key') }}</summary>
                <p class="mt-2 select-all break-all rounded-md border border-seam bg-slab px-3 py-2">{{ decrypt($user->two_factor_secret) }}</p>
            </details>
            <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-5 flex items-end gap-2">
                @csrf
                <x-field name="code" class="flex-1" :label="__('auth.ui.2fa_code')" inputmode="numeric" autocomplete="one-time-code" required autofocus />
                <x-button>{{ __('profile.security.confirm') }}</x-button>
            </form>
            @foreach ($errors->confirmTwoFactorAuthentication->all() as $message)
                <p class="mt-2 text-sm text-fade">{{ $message }}</p>
            @endforeach
        @else
            <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-6">
                @csrf
                <x-button>{{ __('profile.security.enable') }}</x-button>
            </form>
        @endif
    </section>
</x-layouts.app>
