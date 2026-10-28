@php
    $user = auth()->user();
    $locale = app()->getLocale();
    $hour = (int) now()->format('G');
    $greet = __('cabinet.overview.greet_'.match (true) { $hour < 5 => 'night', $hour < 12 => 'morning', $hour < 18 => 'day', default => 'evening' }, ['name' => \Illuminate\Support\Str::before($user->name, ' ')]);
    $monthLeft = now()->endOfMonth()->locale($locale)->diffForHumans(now(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2]);
@endphp
<x-layouts.cabinet :server="$current" :heading="$current ? $greet : __('cabinet.my_servers')" :subtitle="$current ? __('cabinet.overview.season', ['time' => $monthLeft]) : null">
    <x-flash class="mb-6" />

    @if ($servers->isEmpty())
        <div class="c-card" style="max-width:560px">
            <h2>{{ __('cabinet.empty_title') }}</h2>
            <p class="c-muted" style="margin-top:8px">{{ __('cabinet.empty_lead') }}</p>
            <a class="c-btn is-go" style="margin-top:16px" href="{{ route('cabinet.servers.create') }}">{{ __('ui.nav.add_server') }}</a>
        </div>
    @else
        @php
            ['race' => $race, 'kpi' => $kpi, 'days' => $days, 'todo' => $todo, 'votes' => $votes, 'services' => $services] = $overview;
            $tints = ['#a16207,#713f12', '#7c3aed,#4c1d95', '#4d7c0f,#1a2e05', '#0e7490,#164e63', '#be123c,#881337', '#4338ca,#312e81'];
            $num = fn ($n) => number_format((int) $n, 0, ',', ' ');
        @endphp

        {{-- Мої сервери --}}
        <nav class="c-stabs" aria-label="{{ __('cabinet.overview.switch') }}">
            @foreach ($servers as $i => $s)
                @php $logo = $s->media->firstWhere('type', 'logo'); @endphp
                <a class="c-stab" href="{{ route('cabinet', ['server' => $s->id]) }}" @if ($s->is($current)) aria-current="true" @endif>
                    <span class="c-lg" style="background:linear-gradient(135deg,{{ $tints[$s->id % count($tints)] }})">@if ($logo)<img src="{{ \App\Support\HomePageData::url($logo->path) }}" alt="">@else{{ mb_strtoupper(mb_substr($s->name, 0, 1)) }}@endif</span>
                    {{ $s->name }} <small>{{ $s->game->shortName() }} · {{ $s->rank ? '#'.$s->rank : mb_strtolower($s->status->label()) }}</small>
                </a>
            @endforeach
            <a class="c-stab is-add" href="{{ route('cabinet.servers.create') }}">+ {{ __('ui.nav.add_server') }}</a>
        </nav>

        {{-- Перегони в рейтингу --}}
        @if ($race)
            <section class="c-card c-race" aria-label="{{ __('cabinet.overview.ladder') }}">
                <div class="c-place"><b>#{{ $race['rank'] }}</b><span>{{ __('cabinet.overview.place', ['game' => $current->game->shortName()]) }} <em>· {{ __('cabinet.overview.of', ['n' => $race['total']]) }}</em></span></div>
                <div class="c-ladder">
                    @foreach ($race['ladder'] as $row)
                        <div @class(['c-rung', 'is-me' => $row['me']])>
                            <span class="c-pos">{{ $row['place'] }}</span>
                            <div class="c-bar"><i style="width:{{ $row['width'] }}%"></i><span>{{ $row['name'] }}@if ($row['me']) <em>— {{ __('cabinet.overview.you') }}</em>@endif</span></div>
                            <span class="c-v c-num">{{ $num($row['score']) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="c-gap">
                    @if ($race['gap_up'])
                        <p>{{ __('cabinet.overview.gap_up', ['place' => $race['rank'] - 1]) }}</p>
                        <strong><em class="c-num">{{ trans_choice('cabinet.overview.points', $race['gap_up'], ['n' => $num($race['gap_up'])]) }}</em></strong>
                    @elseif ($race['lead'] !== null)
                        <p>{{ __('cabinet.overview.leader') }}</p>
                        <strong><em class="c-num">{{ trans_choice('cabinet.overview.points', $race['lead'], ['n' => $num($race['lead'])]) }}</em></strong>
                    @else
                        <p>{{ __('cabinet.overview.alone') }}</p>
                    @endif
                    <p style="margin-bottom:12px">{{ __('cabinet.overview.gap_hint', ['time' => $monthLeft]) }}</p>
                    <a class="c-btn is-go" href="{{ route('cabinet.servers.show', $current) }}#s-promo">{{ $race['gap_up'] ? __('cabinet.overview.raise') : __('cabinet.overview.keep') }}</a>
                </div>
            </section>
        @else
            <p class="c-card" style="margin-bottom:16px">
                <a href="{{ route('cabinet.servers.show', $current) }}" style="font-weight:700">{{ $current->name }}</a> —
                {{ __('cabinet.overview.not_ranked', ['status' => mb_strtolower($current->status->label())]) }}
            </p>
        @endif

        {{-- Показники --}}
        @php
            // Спарклайн: лінія й заливка під нею, значення по днях
            $spark = function (array $values, string $color, string $id) {
                $w = 200; $h = 34; $min = min($values); $max = max($values); $n = max(1, count($values) - 1);
                $pts = collect($values)->map(fn ($v, $i) => [round($i * $w / $n, 1), round($h - 3 - ($max > $min ? ($v - $min) / ($max - $min) : .5) * ($h - 8), 1)]);
                $line = $pts->map(fn ($p, $i) => ($i ? 'L' : 'M').$p[0].' '.$p[1])->implode(' ');
                return '<svg class="c-spark" viewBox="0 0 '.$w.' '.$h.'" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="'.$id.'" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="'.$color.'" stop-opacity=".35"/><stop offset="1" stop-color="'.$color.'" stop-opacity="0"/></linearGradient></defs>'
                    .'<path d="'.$line.' L'.$w.' '.$h.' L0 '.$h.' Z" fill="url(#'.$id.')"/><path d="'.$line.'" fill="none" stroke="'.$color.'" stroke-width="2" vector-effect="non-scaling-stroke"/></svg>';
            };
            $last14 = $days->slice(-14);
        @endphp
        <div class="c-kpis">
            <div class="c-card c-kpi">
                <div class="c-lbl">{{ __('cabinet.overview.k_votes') }}</div>
                <div class="c-val c-num">{{ $num($kpi['votes_month']) }}</div>
                @if ($kpi['votes_trend'] === null)
                    <div class="c-delta c-faint">{{ __('cabinet.overview.k_trend_none') }}</div>
                @else
                    <div @class(['c-delta', 'c-up' => $kpi['votes_trend'] >= 0, 'c-down' => $kpi['votes_trend'] < 0])>{{ __($kpi['votes_trend'] >= 0 ? 'cabinet.overview.k_trend_up' : 'cabinet.overview.k_trend_down', ['n' => abs($kpi['votes_trend'])]) }}</div>
                @endif
                {!! $spark($last14->pluck('votes')->all(), '#fbbf24', 'sp-votes') !!}
            </div>
            <div class="c-card c-kpi">
                <div class="c-lbl"><span @class(['c-live', 'is-off' => $kpi['online'] === null])></span>{{ __('cabinet.overview.k_online') }}</div>
                @if ($kpi['online'] === null)
                    <div class="c-val c-down">{{ __('cabinet.overview.k_offline') }}</div>
                @else
                    <div class="c-val c-num">{{ $num($kpi['online']) }}@if ($kpi['max_players']) <small>/ {{ $num($kpi['max_players']) }}</small>@endif</div>
                @endif
                <div class="c-delta c-faint">{{ __('cabinet.overview.k_peak', ['n' => $num($kpi['peak'])]) }}</div>
                {!! $spark($last14->pluck('online')->all(), '#60a5fa', 'sp-online') !!}
            </div>
            <div class="c-card c-kpi">
                <div class="c-lbl">{{ __('cabinet.overview.k_uptime') }}</div>
                @if ($kpi['uptime'] === null)
                    <div class="c-val c-faint">—</div>
                    <div class="c-delta c-faint">{{ __('cabinet.overview.k_no_data') }}</div>
                @else
                    <div class="c-val c-num">{{ number_format($kpi['uptime'], 1, ',', ' ') }}%</div>
                    {{-- Кожна смужка — доба: зелена ≥ 99%, бурштинова ≥ 90%, червона — менше --}}
                    <div class="c-uptime" aria-hidden="true">
                        @foreach ($days as $day)
                            <i @class(['is-none' => $day['uptime'] === null, 'is-warn' => $day['uptime'] !== null && $day['uptime'] < 99 && $day['uptime'] >= 90, 'is-bad' => $day['uptime'] !== null && $day['uptime'] < 90])
                               title="{{ $day['date']->format('d.m') }}: {{ $day['uptime'] ?? '—' }}%"></i>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="c-card c-kpi">
                <div class="c-lbl">{{ __('cabinet.overview.k_day') }}</div>
                <div class="c-val c-num">{{ $num($kpi['votes_day']) }}</div>
                <div class="c-delta c-faint">{{ __('cabinet.overview.k_day_hint') }}</div>
                {!! $spark($last14->pluck('votes')->map(fn ($v, $i) => $v)->all(), '#34d399', 'sp-day') !!}
            </div>
        </div>

        <div class="c-cols" style="margin-top:16px">
            <div class="c-stack">
                {{-- Голоси й онлайн --}}
                @php
                    $maxV = max(1, $days->max('votes')) * 1.15;
                    $maxO = max(1, $days->max('online')) * 1.15;
                    $bw = 600 / $days->count();
                    $line = $days->values()->map(fn ($d, $i) => ($i ? 'L' : 'M').round($i * $bw + $bw / 2, 1).' '.round(200 - $d['online'] / $maxO * 182, 1))->implode(' ');
                @endphp
                <section class="c-card" aria-labelledby="ov-chart">
                    <h2 id="ov-chart">{{ __('cabinet.overview.chart') }}</h2>
                    <div class="c-chart">
                        <svg viewBox="0 0 600 200" preserveAspectRatio="none" role="img" aria-label="{{ __('cabinet.overview.chart') }}">
                            @foreach ([.25, .5, .75] as $f)
                                <line x1="0" x2="600" y1="{{ 200 * $f }}" y2="{{ 200 * $f }}" stroke="#27272a" stroke-dasharray="3 5" vector-effect="non-scaling-stroke"/>
                            @endforeach
                            @foreach ($days->values() as $i => $day)
                                @php $h = round($day['votes'] / $maxV * 182, 1); @endphp
                                <rect x="{{ round($i * $bw + $bw * .18, 1) }}" y="{{ 200 - $h }}" width="{{ round($bw * .64, 1) }}" height="{{ $h }}" rx="3" fill="#fbbf24" opacity=".9">
                                    <title>{{ __('cabinet.overview.chart_tip', ['date' => $day['date']->format('d.m'), 'votes' => $day['votes'], 'online' => $day['online']]) }}</title>
                                </rect>
                            @endforeach
                            <path d="{{ $line }}" fill="none" stroke="#60a5fa" stroke-width="2.2" vector-effect="non-scaling-stroke" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="c-axis c-num"><span>{{ $days->first()['date']->format('d.m') }}</span><span>{{ $days->last()['date']->format('d.m') }}</span></div>
                    <div class="c-legend"><span><i style="background:#fbbf24"></i>{{ __('cabinet.overview.l_votes') }}</span><span><i style="background:#60a5fa"></i>{{ __('cabinet.overview.l_online') }}</span></div>
                </section>

                {{-- Останні голоси --}}
                <section class="c-card" aria-labelledby="ov-votes">
                    <h2 id="ov-votes">{{ __('cabinet.votes.title') }}
                        @if ($current->callback_url)<span class="c-more">{{ __('cabinet.overview.callback_on') }}</span>@else<a class="c-more" href="{{ route('cabinet.servers.show', $current) }}#s-callback">{{ __('cabinet.overview.callback_off') }}</a>@endif
                    </h2>
                    @if ($votes->isEmpty())
                        <p class="c-muted" style="margin-top:12px;font-size:14px">{{ __('cabinet.votes.empty') }}</p>
                    @else
                        <ul class="c-feed">
                            @foreach ($votes as $vote)
                                <li>
                                    <span class="c-nick">{{ $vote->nickname ?: __('cabinet.votes.anonymous') }}</span>
                                    @if ($vote->callback_status && $vote->callback_status !== 'none')
                                        <span @class(['c-cb', 'is-ok' => $vote->callback_status === 'delivered', 'is-wait' => $vote->callback_status === 'pending', 'is-bad' => $vote->callback_status === 'failed'])>{{ __('cabinet.votes.callback_'.$vote->callback_status) }}</span>
                                    @else
                                        <span style="margin-left:auto"></span>
                                    @endif
                                    <time datetime="{{ $vote->created_at->toIso8601String() }}">{{ $vote->created_at->locale($locale)->diffForHumans(short: true) }}</time>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            <div class="c-stack">
                {{-- Що зробити --}}
                @php $doneCount = collect($todo)->where('done', true)->count(); @endphp
                <section class="c-card" aria-labelledby="ov-todo">
                    <h2 id="ov-todo">{{ __('cabinet.overview.todo') }} <span class="c-more c-num">{{ $doneCount }} / {{ count($todo) }}</span></h2>
                    <div class="c-progress"><i style="width:{{ round($doneCount / count($todo) * 100) }}%"></i></div>
                    @if ($doneCount === count($todo))
                        <p class="c-up" style="margin-top:12px;font-size:14px">{{ __('cabinet.overview.todo_done') }}</p>
                    @endif
                    <ul class="c-todo">
                        @foreach ($todo as $item)
                            <li @class(['is-done' => $item['done']])>
                                <span class="c-ck" aria-hidden="true"></span>
                                <span>{{ __('cabinet.overview.t_'.$item['key']) }}@unless ($item['done'])<small>{{ __('cabinet.overview.w_'.$item['key']) }}</small>@endunless</span>
                                @unless ($item['done'])<a class="c-btn is-sm" href="{{ $item['url'] }}">{{ __('cabinet.overview.go') }}</a>@endunless
                            </li>
                        @endforeach
                    </ul>
                </section>

                {{-- Активні послуги --}}
                <section class="c-card" aria-labelledby="ov-services">
                    <h2 id="ov-services">{{ __('cabinet.overview.services') }} <a class="c-more" href="{{ route('cabinet.servers.show', $current) }}#s-promo">{{ __('cabinet.overview.manage') }}</a></h2>
                    @if ($services->isEmpty())
                        <p class="c-muted" style="margin-top:12px;font-size:14px">{{ __('cabinet.overview.services_empty') }}</p>
                        <a class="c-btn is-go is-sm" style="margin-top:12px" href="{{ route('cabinet.servers.show', $current) }}#s-promo">{{ __('cabinet.overview.raise') }}</a>
                    @else
                        <div class="c-svc">
                            @foreach ($services as $order)
                                @php
                                    $total = $order->starts_at && $order->ends_at ? max(1, $order->starts_at->diffInSeconds($order->ends_at)) : null;
                                    $left = $total ? max(0, min(100, round(now()->diffInSeconds($order->ends_at, false) / $total * 100))) : 100;
                                    $ending = $order->ends_at && $order->ends_at->lt(now()->addDays(3));
                                    $upcoming = $order->starts_at && $order->starts_at->isFuture();
                                    $glyph = ['vip' => '★', 'highlight' => '◆', 'boost' => '▲', 'coming_soon' => '⏱'][$order->tariff->service] ?? '▣';
                                @endphp
                                <div @class(['c-svc-row', 'is-warn' => $ending])>
                                    <span class="c-ic" aria-hidden="true">{{ $glyph }}</span>
                                    <div>
                                        <b>{{ $order->tariff->name }}</b>
                                        @if ($order->ends_at)
                                            <small>{{ $ending ? __('cabinet.overview.ending', ['time' => $order->ends_at->locale($locale)->diffForHumans()]) : __('cabinet.overview.until', ['date' => $order->ends_at->copy()->subSecond()->format('d.m')]) }}@if ($order->auto_renew) · {{ __('cabinet.overview.auto') }}@endif</small>
                                            <div class="c-left"><i style="width:{{ $upcoming ? 100 : $left }}%"></i></div>
                                        @endif
                                    </div>
                                    @if ($upcoming)
                                        <span class="c-status is-wait">{{ __('cabinet.overview.from', ['date' => $order->starts_at->format('d.m')]) }}</span>
                                    @elseif ($order->auto_renew)
                                        <span class="c-status is-ok">↻</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>
        </div>
    @endif
</x-layouts.cabinet>
