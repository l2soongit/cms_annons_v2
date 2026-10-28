@php use App\Billing\Money; @endphp
<x-layouts.app :title="__('billing.title').' | graj.club'">
    <x-cabinet-nav />
    <x-flash class="mb-6" />

    <div class="grid gap-x-12 gap-y-12 lg:grid-cols-[1fr_20rem]">
        <div class="min-w-0">
            <h1 class="font-display text-3xl font-bold tracking-tight">{{ __('billing.title') }}</h1>
            <p class="mt-3 max-w-2xl text-moss">{{ __('billing.balance_lead') }}</p>

            {{-- Послуги --}}
            <section aria-labelledby="b-orders" class="mt-10">
                <h2 id="b-orders" class="font-display text-lg font-medium">{{ __('billing.orders') }}</h2>
                @if ($orders->isEmpty())
                    <p class="mt-3 text-sm text-moss">{{ __('billing.orders_empty') }}</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[36rem] text-sm">
                            <thead>
                                <tr class="border-b border-bone/80 text-left text-moss">
                                    <th class="py-2 font-normal">{{ __('billing.col.service') }}</th>
                                    <th class="py-2 font-normal">{{ __('billing.col.server') }}</th>
                                    <th class="py-2 font-normal">{{ __('billing.col.period') }}</th>
                                    <th class="py-2 text-right font-normal">{{ __('billing.col.amount') }}</th>
                                    <th class="py-2 text-right font-normal">{{ __('billing.col.receipt') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($orders as $order)
                                    <tr class="border-b border-seam">
                                        <td class="py-3">{{ $order->tariff->name }}<span class="block text-xs text-moss">{{ __("billing.order_statuses.{$order->status}") }}@if ($order->auto_renew && $order->status === 'active') · {{ __('billing.promo.auto_renew') }}@endif</span></td>
                                        <td class="py-3">@if ($order->server)<a href="{{ route('cabinet.servers.show', $order->server) }}" class="hover:text-amber">{{ $order->server->name }}</a>@else — @endif</td>
                                        <td class="py-3 tabular-nums">{{ $order->starts_at->format('d.m.Y') }} – {{ $order->ends_at?->format('d.m.Y') ?? '∞' }}</td>
                                        <td class="py-3 text-right tabular-nums">{{ Money::format($order->amount) }}</td>
                                        <td class="py-3 text-right"><a href="{{ route('cabinet.orders.receipt', $order) }}" class="text-moss hover:text-amber">№{{ $order->id }}</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Історія операцій --}}
            <section aria-labelledby="b-history" class="mt-12">
                <h2 id="b-history" class="font-display text-lg font-medium">{{ __('billing.history') }}</h2>
                @if ($transactions->isEmpty())
                    <p class="mt-3 text-sm text-moss">{{ __('billing.history_empty') }}</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[32rem] text-sm">
                            <thead>
                                <tr class="border-b border-bone/80 text-left text-moss">
                                    <th class="py-2 font-normal">{{ __('billing.col.date') }}</th>
                                    <th class="py-2 font-normal">{{ __('billing.col.type') }}</th>
                                    <th class="py-2 font-normal">{{ __('billing.col.status') }}</th>
                                    <th class="py-2 text-right font-normal">{{ __('billing.col.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($transactions as $t)
                                    <tr class="border-b border-seam">
                                        <td class="py-3 tabular-nums text-moss">{{ $t->created_at->format('d.m.Y H:i') }}</td>
                                        <td class="py-3">{{ __("billing.types.{$t->type}") }}@if ($t->order?->tariff)<span class="block text-xs text-moss">{{ $t->order->tariff->name }}</span>@endif</td>
                                        <td @class(['py-3', 'text-fade' => $t->status === 'failed', 'text-moss' => $t->status === 'pending'])>{{ __("billing.statuses.{$t->status}") }}</td>
                                        <td @class(['py-3 text-right tabular-nums', 'text-signal' => $t->amount > 0 && $t->status === 'succeeded'])>{{ $t->amount > 0 ? '+' : '' }}{{ Money::format($t->amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">{{ $transactions->links() }}</div>
                @endif
            </section>
        </div>

        {{-- Баланс і поповнення --}}
        <aside aria-labelledby="b-deposit" class="lg:sticky lg:top-24 lg:self-start">
            <div class="rounded-lg border border-seam bg-slab p-5">
                <p class="text-sm text-moss">{{ __('billing.balance') }}</p>
                <p class="mt-1 font-display text-3xl font-bold tabular-nums">{{ Money::format($user->balance) }}</p>

                <h2 id="b-deposit" class="mt-6 font-display text-lg font-medium">{{ __('billing.deposit.title') }}</h2>
                @if ($gateways)
                    <form method="POST" action="{{ route('cabinet.balance.deposit') }}" class="mt-4 space-y-4">
                        @csrf
                        <x-field name="amount" :label="__('billing.deposit.amount')" type="number" inputmode="decimal"
                                 :min="config('graj.billing.min_deposit')" :max="config('graj.billing.max_deposit')" step="1" :value="500" required />
                        <fieldset>
                            <legend class="mb-1.5 text-sm font-medium">{{ __('billing.deposit.gateway') }}</legend>
                            <div class="space-y-2">
                                @foreach ($gateways as $key => $gateway)
                                    <label class="flex items-center gap-2 rounded-md border border-seam px-3 py-2 text-sm has-checked:border-amber">
                                        <input type="radio" name="gateway" value="{{ $key }}" @checked(old('gateway', array_key_first($gateways)) === $key) class="accent-amber">{{ $gateway->label() }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <x-button class="w-full">{{ __('billing.deposit.submit') }}</x-button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-moss">{{ __('billing.deposit.unavailable') }}</p>
                @endif
            </div>
        </aside>
    </div>
</x-layouts.app>
