<x-auth-card :title="__('auth.ui.forgot_title')" :lead="__('auth.ui.forgot_lead')">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <x-field name="email" type="email" :label="__('auth.ui.email')" autocomplete="email" required autofocus />
        <x-button class="w-full">{{ __('auth.ui.send_link') }}</x-button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}" class="text-amber hover:underline">{{ __('auth.ui.back_to_login') }}</a>
    </x-slot:footer>
</x-auth-card>
