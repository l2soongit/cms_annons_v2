<x-layouts.app :title="__('billing.deposit.title').' | graj.club'">
    {{-- Платіжна система приймає лише POST: форма надсилається автоматично, кнопка — якщо скрипти вимкнено --}}
    <div class="mx-auto max-w-md py-16 text-center">
        <p class="text-moss">{{ __('billing.deposit.redirect') }}</p>
        <form method="{{ $checkout->method }}" action="{{ $checkout->url }}" id="checkout" class="mt-6">
            @foreach ($checkout->fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <x-button>{{ __('billing.deposit.redirect_button') }}</x-button>
        </form>
    </div>
    <script>document.getElementById('checkout').submit();</script>
</x-layouts.app>
