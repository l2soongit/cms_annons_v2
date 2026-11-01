{{--
    Лінійний графік на SVG без бібліотек: однаково виглядає в адмінці й кабінеті (кольори — currentColor і задані серії).
    points: список масивів з 'label' і числовими ключами; series: [['key' => 'visitors', 'label' => 'Відвідувачі', 'color' => '#6366f1'], …]
--}}
@props(['points', 'series', 'height' => 200, 'money' => false])
@php
    $points = collect($points)->values();
    $n = max(1, $points->count());
    $max = max(1, (float) $points->map(fn ($p) => max(array_map(fn ($s) => (float) ($p[$s['key']] ?? 0), $series)))->max());
    // Кругла верхня межа шкали: 1, 2, 5 × 10^k
    $pow = 10 ** max(0, floor(log10($max)));
    $top = collect([1, 2, 2.5, 5, 10])->map(fn ($m) => $m * $pow)->first(fn ($v) => $v >= $max) ?? $max;
    $w = 1000;
    $h = 100;
    $x = fn ($i) => $n === 1 ? $w / 2 : round($i / ($n - 1) * $w, 2);
    $y = fn ($v) => round($h - ((float) $v / $top) * $h, 2);
    $fmt = fn ($v) => $money ? number_format((float) $v, 0, ',', ' ').' ₴' : number_format((float) $v, 0, ',', ' ');
    // Підписи шкали коротко: 12 500 → 12,5k
    $axis = fn ($v) => $v >= 1000 ? rtrim(rtrim(number_format($v / 1000, 1, ',', ''), '0'), ',').'k' : number_format((float) $v, 0, ',', ' ');
    $uid = 'c'.substr(md5(json_encode($series).$n.mt_rand()), 0, 6);
@endphp
<div {{ $attributes->merge(['style' => 'position:relative']) }}>
    <div style="display:flex;gap:14px;flex-wrap:wrap;font-size:12px;opacity:.85;margin-bottom:8px">
        @foreach ($series as $s)
            <span style="display:inline-flex;align-items:center;gap:6px"><i style="width:10px;height:10px;border-radius:3px;background:{{ $s['color'] }};display:inline-block"></i>{{ $s['label'] }} <b style="font-variant-numeric:tabular-nums">{{ $fmt($points->sum($s['key'])) }}</b></span>
        @endforeach
    </div>
    <div style="display:flex;gap:8px">
        <div style="display:flex;flex-direction:column;justify-content:space-between;font-size:11px;opacity:.55;text-align:right;min-width:3em;white-space:nowrap;height:{{ $height }}px;font-variant-numeric:tabular-nums" aria-hidden="true">
            <span>{{ $axis($top) }}</span><span>{{ $axis($top / 2) }}</span><span>0</span>
        </div>
        <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" role="img" aria-label="{{ collect($series)->pluck('label')->join(', ') }}"
             style="flex:1 1 0;min-width:0;width:100%;height:{{ $height }}px;overflow:visible;display:block">
            <defs>
                @foreach ($series as $i => $s)
                    <linearGradient id="{{ $uid }}g{{ $i }}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{{ $s['color'] }}" stop-opacity=".28"/><stop offset="1" stop-color="{{ $s['color'] }}" stop-opacity="0"/></linearGradient>
                @endforeach
            </defs>
            @foreach ([0, 50, 100] as $line)
                <line x1="0" x2="{{ $w }}" y1="{{ $line }}" y2="{{ $line }}" stroke="currentColor" stroke-opacity=".12" vector-effect="non-scaling-stroke" stroke-dasharray="{{ $line === 100 ? '' : '3 4' }}"/>
            @endforeach
            @foreach ($series as $i => $s)
                @php $line = $points->map(fn ($p, $j) => $x($j).','.$y($p[$s['key']] ?? 0))->join(' '); @endphp
                @if ($i === 0)
                    <polygon points="0,{{ $h }} {{ $line }} {{ $w }},{{ $h }}" fill="url(#{{ $uid }}g{{ $i }})"/>
                @endif
                <polyline points="{{ $line }}" fill="none" stroke="{{ $s['color'] }}" stroke-width="2.2" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" @if ($i > 0) stroke-dasharray="5 4" @endif/>
            @endforeach
            {{-- Підказки при наведенні: прозора смуга на кожну точку --}}
            @foreach ($points as $j => $p)
                <rect x="{{ max(0, $x($j) - $w / $n / 2) }}" y="0" width="{{ $w / $n }}" height="{{ $h }}" fill="transparent">
                    <title>{{ $p['label'] }}: {{ collect($series)->map(fn ($s) => $s['label'].' '.$fmt($p[$s['key']] ?? 0))->join(' · ') }}</title>
                </rect>
            @endforeach
        </svg>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:11px;opacity:.55;margin-left:calc(3em + 8px);margin-top:4px;font-variant-numeric:tabular-nums" aria-hidden="true">
        <span>{{ $points->first()['label'] ?? '' }}</span>
        @if ($n > 2)<span>{{ $points[intdiv($n, 2)]['label'] }}</span>@endif
        <span>{{ $points->last()['label'] ?? '' }}</span>
    </div>
</div>
