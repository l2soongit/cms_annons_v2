@php
    use App\Analytics\Report;

    $t = $report->totals();
    $p = $prev->totals();
    $ev = $report->sums('event');
    $evPrev = $prev->sums('event');
    $funnel = $report->sums('funnel', 'visitors');
    $base = max(1, $funnel['server_view'] ?? 0);
    $num = fn ($v) => number_format((float) $v, 0, ',', ' ');
    // Частка відвідувачів сторінки, що проголосували
    $conv = fn (array $f) => ($f['server_view'] ?? 0) ? round(($f['vote'] ?? 0) / $f['server_view'] * 100, 1) : null;
    $convNow = $conv($funnel);
    $convPrev = $conv($prev->sums('funnel', 'visitors'));
    $rows = function (string $dim, int $limit = 8) use ($report, $labels) {
        $top = $report->top($dim, $limit);

        return $top->map(fn ($r) => ['label' => $labels->label($dim, $r['key']), 'values' => [$r['visitors'], $r['share'].'%'], 'share' => $r['share']]);
    };
    $kpis = [
        ['views', $t['views'], $p['views']],
        ['visitors', $t['visitors'], $p['visitors']],
        ['votes', $ev['vote'] ?? 0, $evPrev['vote'] ?? 0],
        ['website', ($ev['website'] ?? 0) + ($ev['discord'] ?? 0), ($evPrev['website'] ?? 0) + ($evPrev['discord'] ?? 0)],
        ['copy_ip', $ev['copy_ip'] ?? 0, $evPrev['copy_ip'] ?? 0],
    ];
    $cols = [__('analytics.cabinet.col_visitors'), __('analytics.cabinet.col_share')];
@endphp
<x-layouts.cabinet :server="$server" :heading="__('analytics.cabinet.title')" :subtitle="$server->name" :title="__('analytics.cabinet.title').' · '.$server->name">
    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px">
        <a class="c-more" href="{{ route('cabinet.servers.show', $server) }}">{{ __('analytics.cabinet.back') }}</a>
        <nav class="c-stabs" style="margin:0" aria-label="{{ __('analytics.cabinet.title') }}">
            @foreach (__('analytics.cabinet.periods') as $key => $label)
                <a class="c-stab" style="padding:8px 14px" href="{{ route('cabinet.servers.analytics', [$server, 'period' => $key]) }}" @if ((string) $key === $period) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </div>
    <p class="c-muted" style="margin:0 0 16px;font-size:14px">{{ __('analytics.cabinet.lead') }}</p>

    <div class="c-kpis" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
        @foreach ($kpis as [$key, $now, $before])
            @php $d = Report::delta($now, $before); @endphp
            <div class="c-card c-kpi">
                <div class="c-lbl">{{ __("analytics.cabinet.kpi.{$key}") }}</div>
                <div class="c-val c-num">{{ $num($now) }}</div>
                <div @class(['c-delta', 'c-faint' => $d === null, 'c-up' => $d !== null && $d >= 0, 'c-down' => $d !== null && $d < 0])>@if ($d === null)—@else{{ ($d > 0 ? '+' : '').$d.'% ' }}<span class="c-faint" style="font-weight:600">{{ __('analytics.cabinet.vs', ['n' => $report->days()]) }}</span>@endif</div>
            </div>
        @endforeach
        <div class="c-card c-kpi">
            <div class="c-lbl">{{ __('analytics.cabinet.kpi.conversion') }}</div>
            <div class="c-val c-num">{{ $convNow !== null ? $convNow.'%' : '—' }}</div>
            @php $d = $convNow !== null && $convPrev ? round($convNow - $convPrev, 1) : null; @endphp
            <div @class(['c-delta', 'c-faint' => $d === null, 'c-up' => $d !== null && $d >= 0, 'c-down' => $d !== null && $d < 0])>{{ $d === null ? '—' : ($d > 0 ? '+' : '').$d.' п.п.' }}</div>
        </div>
    </div>

    @if ($t['views'] === 0 && ! $ev)
        <p class="c-card c-muted" style="margin-top:16px">{{ __('analytics.cabinet.empty') }}</p>
    @endif

    <section class="c-card" style="margin-top:16px">
        <h2 style="font-size:16px;margin:0 0 14px">{{ __('analytics.cabinet.chart') }}</h2>
        @php $__points1 = $report->series(); @endphp
        @php $__series2 = [['key' => 'visitors', 'label' => __('analytics.cabinet.col_visitors'), 'color' => '#fbbf24'], ['key' => 'views', 'label' => __('analytics.cabinet.col_views'), 'color' => '#60a5fa']]; @endphp
        <x-stats.chart :points="$__points1" :series="$__series2" style="color:#a1a1aa" />
    </section>

    <div style="display:grid;gap:16px;margin-top:16px;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr))">
        <section class="c-card">
            <h2 style="font-size:16px;margin:0 0 4px">{{ __('analytics.cabinet.funnel') }}</h2>
            <p class="c-faint" style="font-size:13px;margin:0 0 14px">{{ __('analytics.cabinet.funnel_hint') }}</p>
            @foreach (['server_view', 'vote_click', 'vote', 'website'] as $step)
                @php $n = $funnel[$step] ?? 0; @endphp
                <div style="display:grid;grid-template-columns:1fr auto;gap:4px 10px;font-size:14px;margin-bottom:10px">
                    <span>{{ __("analytics.funnel.{$step}") }}</span><b class="c-num">{{ $num($n) }} <small class="c-faint">{{ round($n / $base * 100, 1) }}%</small></b>
                    <i style="grid-column:1/-1;display:block;height:8px;border-radius:4px;background:var(--c-grad);width:{{ max(1, round($n / $base * 100, 1)) }}%"></i>
                </div>
            @endforeach
        </section>

        <section class="c-card">
            @php $actions = collect(['vote_click', 'vote', 'copy_ip', 'website', 'discord', 'youtube'])->map(fn ($k) => ['label' => __("analytics.event.{$k}"), 'values' => [$ev[$k] ?? 0], 'share' => ($sum = max(1, array_sum($ev))) ? round(($ev[$k] ?? 0) / $sum * 100, 1) : 0]); @endphp
            <x-stats.table :title="__('analytics.cabinet.actions')" :rows="$actions" :columns="[__('analytics.cabinet.col_count')]" color="#fbbf24" />
        </section>

        @php $__rows3 = $rows('source'); @endphp
        <x-stats.table class="c-card" :title="__('analytics.cabinet.sources')" :rows="$__rows3" :columns="$cols" :empty="__('analytics.cabinet.none')" color="#fbbf24" />
        @php $__rows4 = $rows('referrer', 15); @endphp
        <x-stats.table class="c-card" :title="__('analytics.cabinet.referrers')" :rows="$__rows4" :columns="$cols" :empty="__('analytics.cabinet.none')" color="#fbbf24" />
        @php $__rows5 = $rows('country', 12); @endphp
        <x-stats.table class="c-card" :title="__('analytics.cabinet.countries')" :rows="$__rows5" :columns="$cols" :empty="__('analytics.cabinet.none')" color="#fbbf24" />
        @php $__rows6 = $rows('lang', 10); @endphp
        <x-stats.table class="c-card" :title="__('analytics.cabinet.langs')" :rows="$__rows6" :columns="$cols" :empty="__('analytics.cabinet.none')" color="#fbbf24" />
        @php $__rows7 = $rows('device', 4); @endphp
        <x-stats.table class="c-card" :title="__('analytics.cabinet.devices')" :rows="$__rows7" :columns="$cols" :empty="__('analytics.cabinet.none')" color="#fbbf24" />
    </div>

    <p class="c-faint" style="font-size:13px;margin-top:16px">{{ __('analytics.cabinet.tip_utm') }}</p>
    <p class="c-faint" style="font-size:13px">{{ __('analytics.cabinet.privacy') }}</p>
</x-layouts.cabinet>
