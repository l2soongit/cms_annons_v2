<x-auth-card :title="__('auth.ui.2fa_title')" :lead="__('auth.ui.2fa_lead')">
    <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-5">
        @csrf
        <x-field name="code" :label="__('auth.ui.2fa_code')" inputmode="numeric" autocomplete="one-time-code" autofocus />
        <details class="text-sm">
            <summary class="cursor-pointer text-moss hover:text-bone">{{ __('auth.ui.2fa_use_recovery') }}</summary>
            <x-field name="recovery_code" class="mt-3" :label="__('auth.ui.2fa_recovery')" autocomplete="one-time-code" />
        </details>
        <x-button class="w-full">{{ __('auth.ui.login') }}</x-button>
    </form>
</x-auth-card>
