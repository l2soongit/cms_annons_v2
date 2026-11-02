@php
    use App\Community\Bonus;
    $span = $level['next'] ? max(1, $level['next'] - $level['min']) : 1;
    $pct = $level['next'] ? min(100, (int) round(($user->bonus_earned - $level['min']) / $span * 100)) : 100;
@endphp
<x-layouts.cabinet :title="__('community.bonus.title').' | graj.club'" :heading="__('community.bonus.title')" :subtitle="__('community.bonus.lead')">
    <x-flash class="mb-6" />
    @error('reward')<div class="c-warn" role="alert" style="margin-bottom:16px">{{ $message }}</div>@enderror

    <div class="c-grid-3" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
        <section class="c-card"><h2>{{ __('community.bonus.balance') }}</h2><p class="c-num" style="font:800 32px var(--c-display, inherit);color:var(--c-amber, #fbbf24)">{{ number_format($user->bonus_balance, 0, ',', ' ') }}</p><p class="c-muted">{{ __('community.bonus.points') }}</p></section>
        <section class="c-card"><h2>{{ __('community.bonus.level') }}</h2><p style="font-size:22px;font-weight:800">{{ __('community.levels.'.$level['key']) }}</p>
            <div style="height:8px;border-radius:999px;background:#ffffff10;margin:10px 0 6px;overflow:hidden"><i style="display:block;height:100%;width:{{ $pct }}%;background:linear-gradient(90deg,#fbbf24,#f97316)"></i></div>
            <p class="c-muted" style="font-size:13px">@if ($level['next']){{ __('community.bonus.to_next', ['n' => $level['next'] - $user->bonus_earned]) }}@else{{ __('community.bonus.max_level') }}@endif</p></section>
        <section class="c-card"><h2>{{ __('community.bonus.streak') }}</h2><p style="font-size:22px;font-weight:800">🔥 {{ trans_choice('community.bonus.days', $user->streak_days, ['n' => $user->streak_days]) }}</p><p class="c-muted" style="font-size:13px">{{ __('community.bonus.streak_hint') }}</p></section>
    </div>

    <section class="c-card" style="margin-top:16px" aria-labelledby="b-earn">
        <h2 id="b-earn">{{ __('community.bonus.how') }}</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;margin-top:12px">
            @foreach ($earn as $key => $points)
                <div style="display:flex;justify-content:space-between;gap:8px;padding:12px;border-radius:12px;border:1px solid var(--c-line)"><span>{{ __('community.bonus.reasons.'.$key) }}</span><b style="color:#fbbf24;white-space:nowrap">+{{ $points }}</b></div>
            @endforeach
        </div>
        <p class="c-muted" style="margin-top:10px;font-size:13px">{{ __('community.bonus.rules', ['cap' => config('graj.community.bonus.daily_cap'), 'share' => config('graj.community.bonus.max_pay_share')]) }}</p>
    </section>

    <section class="c-card" style="margin-top:16px" aria-labelledby="b-shop">
        <h2 id="b-shop">{{ __('community.bonus.shop') }}</h2>
        @if ($rewards->isEmpty())
            <p class="c-muted">{{ __('community.bonus.shop_empty') }}</p>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;margin-top:12px">
                @foreach ($rewards as $reward)
                    <form method="post" action="{{ route('cabinet.bonus.buy', $reward) }}" style="display:grid;gap:8px;align-content:start;padding:16px;border-radius:14px;border:1px solid var(--c-line)">
                        @csrf
                        <b style="font-size:16px">@if ($reward->kind === 'name_color')<span style="color:{{ preg_match('/^#[0-9a-f]{3,8}$/i', (string) $reward->value) ? $reward->value : 'inherit' }}">●</span> @elseif ($reward->kind === 'badge'){{ $reward->value }} @endif{{ $reward->name }}</b>
                        @if ($reward->description)<span class="c-muted" style="font-size:13px">{{ $reward->description }}</span>@endif
                        <span class="c-muted" style="font-size:13px">{{ $reward->duration_days ? trans_choice('community.bonus.for_days', $reward->duration_days, ['n' => $reward->duration_days]) : __('community.bonus.forever') }}</span>
                        @if ($reward->kind === 'pin_clan')
                            @if ($clanTopics->isEmpty())<span class="c-muted" style="font-size:13px">{{ __('community.bonus.no_clan_topics') }}</span>
                            @else<select name="topic_id" class="c-inp" aria-label="{{ __('community.types.clan') }}">@foreach ($clanTopics as $t)<option value="{{ $t->id }}">{{ $t->title }}</option>@endforeach</select>@endif
                        @endif
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                            <b class="c-num" style="color:#fbbf24;font-size:18px">{{ $reward->cost }} {{ __('community.bonus.points_short') }}</b>
                            <button class="c-btn is-go" @disabled($user->bonus_balance < $reward->cost || ($reward->kind === 'pin_clan' && $clanTopics->isEmpty()))>{{ __('community.bonus.buy') }}</button>
                        </div>
                    </form>
                @endforeach
            </div>
        @endif
    </section>

    <section class="c-card" style="margin-top:16px" aria-labelledby="b-history">
        <h2 id="b-history">{{ __('community.bonus.history') }}</h2>
        @if ($history->isEmpty())
            <p class="c-muted">{{ __('community.bonus.history_empty') }}</p>
        @else
            <div style="overflow-x:auto"><table class="c-table">
                <thead><tr><th>{{ __('community.bonus.when') }}</th><th>{{ __('community.bonus.what') }}</th><th style="text-align:right">{{ __('community.bonus.points') }}</th></tr></thead>
                <tbody>
                    @foreach ($history as $tx)
                        <tr><td>{{ $tx->created_at?->translatedFormat('j M, H:i') }}</td>
                            <td>{{ __('community.bonus.reasons.'.$tx->reason) }}@if ($tx->note && in_array($tx->reason, ['manual', 'reward', 'payment'], true)) · {{ $tx->note }}@endif</td>
                            <td style="text-align:right;font-weight:800;color:{{ $tx->amount > 0 ? '#34d399' : '#fb7185' }}">{{ $tx->amount > 0 ? '+' : '' }}{{ $tx->amount }}</td></tr>
                    @endforeach
                </tbody>
            </table></div>
            <div style="margin-top:12px">{{ $history->links() }}</div>
        @endif
    </section>
</x-layouts.cabinet>
