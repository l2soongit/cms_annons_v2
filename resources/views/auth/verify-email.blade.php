<x-auth-card :title="__('auth.ui.verify_title')" :lead="__('auth.ui.verify_lead', ['email' => auth()->user()->email])">
    @if (session('status') === 'verification-link-sent')
        <p class="mb-6 text-sm text-signal" role="status">{{ __('auth.ui.verify_sent') }}</p>
    @endif

    <div class="flex flex-wrap gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button>{{ __('auth.ui.verify_resend') }}</x-button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button variant="ghost">{{ __('auth.ui.logout') }}</x-button>
        </form>
    </div>
</x-auth-card>
