<x-auth-card :title="__('auth.ui.register_title')" :lead="__('auth.ui.register_lead')">
    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf
        <x-field name="name" :label="__('auth.ui.name')" :hint="__('auth.ui.name_hint')" autocomplete="nickname" required autofocus />
        <x-field name="email" type="email" :label="__('auth.ui.email')" autocomplete="email" required />
        <x-field name="password" type="password" :label="__('auth.ui.password')" :hint="__('auth.ui.password_hint')" autocomplete="new-password" required />
        <x-field name="password_confirmation" type="password" :label="__('auth.ui.password_confirm')" autocomplete="new-password" required />

        <div>
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="rules" value="1" required class="mt-0.5 size-4 accent-amber" @checked(old('rules'))>
                <span>{{ __('auth.ui.accept_rules') }}</span>
            </label>
            @error('rules') <p class="mt-1.5 text-sm text-fade">{{ $message }}</p> @enderror
        </div>

        <x-turnstile />

        <x-button class="w-full">{{ __('auth.ui.register') }}</x-button>
    </form>

    <x-slot:footer>
        {{ __('auth.ui.have_account') }} <a href="{{ route('login') }}" class="text-amber hover:underline">{{ __('auth.ui.login') }}</a>
    </x-slot:footer>
</x-auth-card>
