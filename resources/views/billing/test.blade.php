<x-layouts.app :title="__('billing.test.title').' | graj.club'">
    <div class="mx-auto max-w-md py-12">
        <h1 class="font-display text-2xl font-bold">{{ __('billing.test.title') }}</h1>
        <p class="mt-3 text-moss">{{ __('billing.test.lead') }}</p>
        <div class="mt-8 flex gap-3">
            @foreach ([$succeeded => __('billing.test.pay', ['amount' => \App\Billing\Money::format($transaction->amount)]), $failed => __('billing.test.fail')] as $status => $label)
                <form method="POST" action="{{ route('billing.test.pay') }}">
                    @csrf
                    <input type="hidden" name="transaction" value="{{ $transaction->id }}">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input type="hidden" name="signature" value="{{ $sign($status) }}">
                    <x-button :variant="$status === $succeeded ? 'primary' : 'secondary'">{{ $label }}</x-button>
                </form>
            @endforeach
        </div>
    </div>
</x-layouts.app>
