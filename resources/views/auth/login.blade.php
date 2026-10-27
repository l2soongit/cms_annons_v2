<x-auth-card :title="__('auth.ui.login_title')">
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <x-field name="email" type="email" :label="__('auth.ui.email')" autocomplete="email" required autofocus />
        <x-field name="password" type="password" :label="__('auth.ui.password')" autocomplete="current-password" required />

        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="remember" class="size-4 accent-amber"> {{ __('auth.ui.remember') }}
            </label>
            <a href="{{ route('password.request') }}" class="text-moss hover:text-bone">{{ __('auth.ui.forgot') }}</a>
        </div>

        <x-button class="w-full">{{ __('auth.ui.login') }}</x-button>
    </form>

    <x-slot:footer>
        {{ __('auth.ui.no_account') }} <a href="{{ route('register') }}" class="text-amber hover:underline">{{ __('auth.ui.register') }}</a>
    </x-slot:footer>
</x-auth-card>
