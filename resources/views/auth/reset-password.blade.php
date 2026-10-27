<x-auth-card :title="__('auth.ui.reset_title')">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-field name="email" type="email" :label="__('auth.ui.email')" :value="$request->email" autocomplete="email" required />
        <x-field name="password" type="password" :label="__('auth.ui.new_password')" :hint="__('auth.ui.password_hint')" autocomplete="new-password" required autofocus />
        <x-field name="password_confirmation" type="password" :label="__('auth.ui.password_confirm')" autocomplete="new-password" required />
        <x-button class="w-full">{{ __('auth.ui.save_password') }}</x-button>
    </form>
</x-auth-card>
