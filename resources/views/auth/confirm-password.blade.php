<x-auth-card :title="__('auth.ui.confirm_title')" :lead="__('auth.ui.confirm_lead')">
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf
        <x-field name="password" type="password" :label="__('auth.ui.password')" autocomplete="current-password" required autofocus />
        <x-button class="w-full">{{ __('auth.ui.confirm') }}</x-button>
    </form>
</x-auth-card>
