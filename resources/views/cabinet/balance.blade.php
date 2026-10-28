@php
    use App\Billing\Money;

    $base = config('graj.billing.currency');
    $symbols = ['UAH' => '₴', 'USD' => '$', 'EUR' => '€', 'RUB' => '₽'];
    $presets = ['UAH' => [200, 500, 1000, 2000], 'USD' => [5, 10, 25, 50], 'EUR' => [5, 10, 25, 50]];
    $currency = old('currency', array_key_first($currencies));
    $texts = [
        'credit' => __('billing.deposit.credit'),
        'rate' => __('billing.deposit.rate_line'),
        'range' => __('billing.deposit.range_line', ['min' => Money::format((int) config('graj.billing.min_deposit') * 100, true), 'max' => Money::format((int) config('graj.billing.max_deposit') * 100, true)]),
        'pay' => __('billing.deposit.pay'),
    ];
@endphp
<x-layouts.cabinet :heading="__('billing.title')" :subtitle="__('billing.balance_lead')">
    <x-flash class="mb-6" />

    <div class="c-cols-side">
        <div class="c-stack">
            <div class="c-card c-big-bal">
                <p class="c-muted">{{ __('billing.balance') }}</p>
                <div class="c-v c-num">{{ Money::format($user->balance) }}</div>
                <p class="c-muted" style="font-size:13px">{{ __('billing.wallet_hint') }}</p>
            </div>

            {{-- Історія операцій --}}
            <section class="c-card" aria-labelledby="b-history">
                <h2 id="b-history">{{ __('billing.history') }}</h2>
                @if ($transactions->isEmpty())
                    <p class="c-muted" style="margin-top:12px;font-size:14px">{{ __('billing.history_empty') }}</p>
                @else
                    <div style="overflow-x:auto">
                        <table class="c-table" style="min-width:32rem">
                            <thead><tr><th>{{ __('billing.col.date') }}</th><th>{{ __('billing.col.type') }}</th><th>{{ __('billing.col.status') }}</th><th>{{ __('billing.col.amount') }}</th></tr></thead>
                            <tbody>
                                @foreach ($transactions as $t)
                                    <tr>
                                        <td class="c-num c-muted">{{ $t->created_at->format('d.m.Y') }}<small>{{ $t->created_at->format('H:i') }}</small></td>
                                        <td>{{ __("billing.types.{$t->type}") }}@if ($t->provider && $t->type === 'deposit') · {{ ucfirst($t->provider) }}@endif
                                            @if ($t->order?->tariff)<small>{{ $t->order->tariff->name }}</small>@endif
                                            @if ($t->paid_currency)<small>{{ Money::formatIn(Money::cents($t->paid_amount), $t->paid_currency) }} · {{ __('billing.rate_short', ['rate' => rtrim(rtrim(number_format((float) $t->rate, 4, ',', ''), '0'), ',')]) }}</small>@endif
                                        </td>
                                        <td @class(['c-down' => $t->status === 'failed', 'c-faint' => $t->status === 'pending'])>{{ __("billing.statuses.{$t->status}") }}</td>
                                        <td @class(['c-num', 'c-up' => $t->amount > 0 && $t->status === 'succeeded'])>{{ $t->amount > 0 ? '+' : '' }}{{ Money::format($t->amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:16px">{{ $transactions->links() }}</div>
                @endif
            </section>

            {{-- Послуги й квитанції --}}
            <section class="c-card" aria-labelledby="b-orders">
                <h2 id="b-orders">{{ __('billing.orders') }}</h2>
                @if ($orders->isEmpty())
                    <p class="c-muted" style="margin-top:12px;font-size:14px">{{ __('billing.orders_empty') }}</p>
                @else
                    <div style="overflow-x:auto">
                        <table class="c-table" style="min-width:36rem">
                            <thead><tr><th>{{ __('billing.col.service') }}</th><th>{{ __('billing.col.period') }}</th><th>{{ __('billing.col.amount') }}</th><th>{{ __('billing.col.receipt') }}</th></tr></thead>
                            <tbody>
                                @foreach ($orders as $order)
                                    <tr>
                                        <td>{{ $order->tariff->name }}
                                            <small>@if ($order->server)<a href="{{ route('cabinet.servers.show', $order->server) }}">{{ $order->server->name }}</a> · @endif{{ __("billing.order_statuses.{$order->status}") }}@if ($order->auto_renew && $order->status === 'active') · {{ __('billing.promo.auto_renew') }}@endif</small></td>
                                        <td class="c-num">{{ $order->starts_at->format('d.m') }} – {{ $order->ends_at?->copy()->subSecond()->format('d.m.Y') ?? '∞' }}</td>
                                        <td class="c-num">{{ Money::format($order->amount) }}</td>
                                        <td><a class="c-muted" href="{{ route('cabinet.orders.receipt', $order) }}">№{{ $order->id }}</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        {{-- Поповнення --}}
        <section class="c-card" aria-labelledby="b-deposit">
            <h2 id="b-deposit">{{ __('billing.deposit.title') }}</h2>
            @if ($gateways)
                <form method="POST" action="{{ route('cabinet.balance.deposit') }}" data-deposit>
                    @csrf
                    @if (count($currencies) > 1)
                        <div class="c-seg" role="radiogroup" aria-label="{{ __('billing.deposit.currency') }}">
                            @foreach ($currencies as $code => $rate)
                                <label><input type="radio" name="currency" value="{{ $code }}" @checked($currency === $code) data-rate="{{ $rate }}" data-symbol="{{ $symbols[$code] ?? $code }}"><span>{{ $symbols[$code] ?? '' }} {{ $code }}</span></label>
                            @endforeach
                        </div>
                    @else
                        <input type="hidden" name="currency" value="{{ $base }}" data-rate="1" data-symbol="₴">
                    @endif
                    <div class="c-field">
                        <label for="amount">{{ __('billing.deposit.amount_plain') }}</label>
                        <div class="c-inp"><input id="amount" name="amount" type="number" inputmode="decimal" min="1" step="0.01" value="{{ old('amount', 500) }}" required><b data-symbol-out>₴</b></div>
                        @error('amount')<p class="c-err">{{ $message }}</p>@enderror
                    </div>
                    <div class="c-quick" data-quick></div>
                    <div class="c-conv" data-conv aria-live="polite"></div>
                    <div class="c-pay">
                        @foreach ($gateways as $key => $gateway)
                            <label><input type="radio" name="gateway" value="{{ $key }}" @checked(old('gateway', array_key_first($gateways)) === $key)>{{ $gateway->label() }}</label>
                        @endforeach
                    </div>
                    <button class="c-btn is-go" style="width:100%;margin-top:14px" data-pay>{{ __('billing.deposit.submit') }}</button>
                    <p class="c-hint" style="margin-top:10px">{{ __('billing.deposit.rates_note') }}</p>
                </form>
            @else
                <p class="c-muted" style="margin-top:12px;font-size:14px">{{ __('billing.deposit.unavailable') }}</p>
            @endif
        </section>
    </div>

    <x-slot:scripts>
        @if ($gateways)
            <script>
                // Поповнення: валюта, швидкі суми й перерахунок у гривні за курсом НБУ
                (() => {
                    const form = document.querySelector('[data-deposit]');
                    const presets = @json($presets);
                    const t = @json($texts);
                    const min = {{ (int) config('graj.billing.min_deposit') }}, max = {{ (int) config('graj.billing.max_deposit') }};
                    const locale = @json(app()->getLocale());
                    const amount = form.querySelector('#amount');
                    const fmt = (n, d = 2) => n.toLocaleString(locale, { minimumFractionDigits: d, maximumFractionDigits: d });
                    const cur = () => form.querySelector('input[name="currency"]:checked') || form.querySelector('input[name="currency"]');
                    const update = () => {
                        const c = cur(), rate = +c.dataset.rate, sym = c.dataset.symbol, a = parseFloat(amount.value) || 0, uah = a * rate;
                        form.querySelector('[data-symbol-out]').textContent = sym;
                        const ok = uah >= min && uah <= max;
                        form.querySelector('[data-conv]').innerHTML = (rate !== 1 ? t.rate.replace(':symbol', sym).replace(':rate', fmt(rate, 4)) + '<br>' : '')
                            + t.credit.replace(':sum', `<b>${fmt(uah)} ₴</b>`) + (ok ? '' : ` · <span class="c-down">${t.range}</span>`);
                        form.querySelector('[data-pay]').textContent = t.pay.replace(':sum', `${fmt(a)} ${sym}`);
                        form.querySelector('[data-pay]').disabled = !ok;
                    };
                    const setPresets = () => {
                        const c = cur(), list = presets[c.value] || presets.UAH;
                        form.querySelector('[data-quick]').innerHTML = list.map(v => `<button type="button" data-q="${v}">${v.toLocaleString(locale)} ${c.dataset.symbol}</button>`).join('');
                    };
                    form.addEventListener('click', (e) => { const q = e.target.closest('[data-q]'); if (q) { amount.value = q.dataset.q; update(); } });
                    form.querySelectorAll('input[name="currency"]').forEach(r => r.addEventListener('change', () => { setPresets(); amount.value = (presets[r.value] || presets.UAH)[1]; update(); }));
                    amount.addEventListener('input', update);
                    setPresets(); update();
                })();
            </script>
        @endif
    </x-slot:scripts>
</x-layouts.cabinet>
