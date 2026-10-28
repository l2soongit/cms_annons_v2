@php use App\Billing\Money; @endphp
<x-layouts.app :title="__('billing.receipt.title', ['id' => $order->id]).' | graj.club'">
    <article class="mx-auto max-w-xl rounded-lg border border-seam bg-slab p-8 print:border-0 print:bg-white print:text-black">
        <header class="flex items-start justify-between gap-4">
            <div>
                <p class="font-display text-lg font-bold">graj.club</p>
                <h1 class="mt-4 font-display text-2xl font-bold">{{ __('billing.receipt.title', ['id' => $order->id]) }}</h1>
                <p class="mt-1 text-sm text-moss">{{ __('billing.receipt.issued', ['date' => $order->created_at->format('d.m.Y H:i')]) }}</p>
            </div>
            <button type="button" onclick="window.print()" class="text-sm text-moss hover:text-bone print:hidden">{{ __('billing.receipt.print') }}</button>
        </header>
        <dl class="mt-8 grid grid-cols-[10rem_1fr] gap-y-3 text-sm">
            <dt class="text-moss">{{ __('billing.receipt.payer') }}</dt><dd>{{ $order->user?->name }} ({{ $order->user?->email }})</dd>
            <dt class="text-moss">{{ __('billing.receipt.service') }}</dt><dd>{{ $order->tariff->name }}</dd>
            <dt class="text-moss">{{ __('billing.receipt.server') }}</dt><dd>{{ $order->server?->name ?? '—' }}</dd>
            <dt class="text-moss">{{ __('billing.receipt.period') }}</dt><dd class="tabular-nums">{{ $order->starts_at->format('d.m.Y H:i') }} – {{ $order->ends_at?->format('d.m.Y H:i') ?? '∞' }}</dd>
            @if ($order->promoCode)
                <dt class="text-moss">{{ __('billing.receipt.promo') }}</dt><dd>{{ $order->promoCode->code }} (−{{ $order->promoCode->discount_percent }}%)</dd>
            @endif
            <dt class="border-t border-seam pt-3 font-medium">{{ __('billing.receipt.total') }}</dt><dd class="border-t border-seam pt-3 font-display text-lg font-bold tabular-nums">{{ Money::format($order->amount) }}</dd>
        </dl>
        <p class="mt-8 text-xs text-moss">{{ __('billing.receipt.note') }}</p>
    </article>
</x-layouts.app>
