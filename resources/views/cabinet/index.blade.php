<x-layouts.app :title="__('cabinet.title').' | graj.club'">
    <x-cabinet-nav />
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="font-display text-3xl font-bold tracking-tight">{{ $current ? __('cabinet.overview.title') : __('cabinet.my_servers') }}</h1>
        <x-button :href="route('cabinet.servers.create')">{{ __('ui.nav.add_server') }}</x-button>
    </div>

    <x-flash class="mt-6" />

    @if ($servers->isEmpty())
        <div class="mt-10 max-w-lg">
            <p class="text-lg">{{ __('cabinet.empty_title') }}</p>
            <p class="mt-2 text-moss">{{ __('cabinet.empty_lead') }}</p>
            <x-button class="mt-6" :href="route('cabinet.servers.create')">{{ __('ui.nav.add_server') }}</x-button>
        </div>
    @else
        @php
            /** @var \App\Models\Server $current */
            ['race' => $race, 'kpi' => $kpi, 'days' => $days, 'todo' => $todo, 'votes' => $votes, 'services' => $services] = $overview;
            $locale = app()->getLocale();
        @endphp

        {{-- Перемикач серверів --}}
        @if ($servers->count() > 1)
            <nav class="mt-6 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none]" aria-label="{{ __('cabinet.overview.switch') }}">
                @foreach ($servers as $s)
                    <a href="{{ route('cabinet', ['server' => $s->id]) }}" @if ($s->is($current)) aria-current="true" @endif
                       @class(['flex shrink-0 items-center gap-2 rounded-lg border px-3 py-2 text-sm', 'border-amber bg-amber/10 text-bone' => $s->is($current), 'border-seam text-moss hover:text-bone' => ! $s->is($current)])>
                        <span class="font-medium">{{ $s->name }}</span>
                        <span class="text-xs text-moss">{{ $s->game->shortName() }} · {{ $s->rank ? '#'.$s->rank : $s->status->label() }}</span>
                    </a>
                @endforeach
            </nav>
        @endif

        {{-- Місце в рейтингу й відрив до сусідів --}}
        @if ($race)
            <section class="mt-6 grid gap-6 rounded-xl border border-amber/30 bg-gradient-to-br from-amber/10 to-transparent p-5 sm:p-6 lg:grid-cols-[auto_1fr_16rem] lg:items-center" aria-labelledby="ov-place">
                <div class="flex items-baseline gap-3 lg:block lg:text-center">
                    <p id="ov-place" class="font-display text-6xl font-bold leading-none text-amber tabular-nums">#{{ $race['rank'] }}<span class="ml-1 font-sans text-base font-medium text-moss">{{ __('cabinet.overview.of', ['n' => $race['total']]) }}</span></p>
                    <p class="text-xs font-medium uppercase tracking-widest text-moss lg:mt-2">{{ __('cabinet.overview.place', ['game' => $current->game->shortName()]) }}</p>
                </div>
                <ol class="space-y-2" aria-label="{{ __('cabinet.overview.ladder') }}">
                    @foreach ($race['ladder'] as $row)
                        <li class="grid grid-cols-[1.75rem_1fr_3.5rem] items-center gap-3 text-sm">
                            <span class="text-right font-medium text-moss tabular-nums">{{ $row['place'] }}</span>
                            <span @class(['relative h-8 overflow-hidden rounded-md border', 'border-amber/60' => $row['me'], 'border-seam bg-slab' => ! $row['me']])>
                                {{-- Смужка може бути короткою, тож текст лишається світлим, а «ви» — бурштиновим --}}
                                <span @class(['absolute inset-y-0 left-0 rounded-[5px]', 'bg-amber/45' => $row['me'], 'bg-seam' => ! $row['me']]) style="width: {{ $row['width'] }}%"></span>
                                <span class="relative block truncate px-3 leading-8 font-medium">{{ $row['name'] }}@if ($row['me']) <span class="text-amber">— {{ __('cabinet.overview.you') }}</span>@endif</span>
                            </span>
                            <span class="text-right font-semibold tabular-nums">{{ number_format($row['score'], 0, ',', ' ') }}</span>
                        </li>
                    @endforeach
                </ol>
                <div>
                    @if ($race['gap_up'])
                        <p class="text-sm text-moss">{{ __('cabinet.overview.gap_up', ['place' => $race['rank'] - 1]) }}</p>
                        <p class="mt-1 font-display text-2xl font-bold"><span class="text-amber tabular-nums">{{ trans_choice('cabinet.overview.points', $race['gap_up'], ['n' => number_format($race['gap_up'], 0, ',', ' ')]) }}</span></p>
                    @elseif ($race['lead'] !== null)
                        <p class="text-sm text-moss">{{ __('cabinet.overview.leader') }}</p>
                        <p class="mt-1 font-display text-2xl font-bold text-amber tabular-nums">{{ trans_choice('cabinet.overview.points', $race['lead'], ['n' => number_format($race['lead'], 0, ',', ' ')]) }}</p>
                    @else
                        <p class="text-sm text-moss">{{ __('cabinet.overview.alone') }}</p>
                    @endif
                    <p class="mt-2 text-xs text-moss">{{ __('cabinet.overview.gap_hint', ['time' => now()->endOfMonth()->locale($locale)->diffForHumans(now(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2])]) }}</p>
                    <x-button class="mt-4" :href="route('cabinet.servers.show', $current).'#s-promo'">{{ $race['gap_up'] ? __('cabinet.overview.raise') : __('cabinet.overview.keep') }}</x-button>
                </div>
            </section>
        @else
            <p class="mt-6 rounded-lg border border-seam bg-slab px-4 py-3 text-sm">
                <a href="{{ route('cabinet.servers.show', $current) }}" class="font-medium hover:text-amber">{{ $current->name }}</a> —
                {{ __('cabinet.overview.not_ranked', ['status' => mb_strtolower($current->status->label())]) }}
            </p>
        @endif

        {{-- Показники --}}
        @php
            $spark = function (array $values) {
                $max = max(1, max($values));
                $n = max(1, count($values) - 1);
                return collect($values)->map(fn ($v, $i) => round($i / $n * 100, 1).','.round(30 - $v / $max * 26, 1))->implode(' ');
            };
            $votesSpark = $spark($days->slice(-14)->pluck('votes')->all());
            $onlineSpark = $spark($days->slice(-14)->pluck('online')->all());
        @endphp
        <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-xl border border-seam bg-slab p-4">
                <p class="text-sm text-moss">{{ __('cabinet.overview.k_votes') }}</p>
                <p class="mt-1 font-display text-2xl font-bold tabular-nums">{{ number_format($kpi['votes_month'], 0, ',', ' ') }}</p>
                @if ($kpi['votes_trend'] === null)
                    <p class="mt-1 text-xs text-moss">{{ __('cabinet.overview.k_trend_none') }}</p>
                @else
                    <p @class(['mt-1 text-xs font-medium', 'text-signal' => $kpi['votes_trend'] >= 0, 'text-fade' => $kpi['votes_trend'] < 0])>{{ __($kpi['votes_trend'] >= 0 ? 'cabinet.overview.k_trend_up' : 'cabinet.overview.k_trend_down', ['n' => abs($kpi['votes_trend'])]) }}</p>
                @endif
                <svg viewBox="0 0 100 32" preserveAspectRatio="none" class="mt-2 h-8 w-full" aria-hidden="true"><polyline points="{{ $votesSpark }}" fill="none" stroke="var(--color-amber)" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round"/></svg>
            </div>
            <div class="rounded-xl border border-seam bg-slab p-4">
                <p class="text-sm text-moss">{{ __('cabinet.overview.k_day') }}</p>
                <p class="mt-1 font-display text-2xl font-bold tabular-nums">{{ number_format($kpi['votes_day'], 0, ',', ' ') }}</p>
                <p class="mt-1 text-xs text-moss">{{ __('cabinet.overview.k_day_hint') }}</p>
            </div>
            <div class="rounded-xl border border-seam bg-slab p-4">
                <p class="flex items-center gap-2 text-sm text-moss">
                    <span @class(['size-2 rounded-full', 'bg-signal' => $kpi['online'] !== null, 'bg-fade' => $kpi['online'] === null])></span>{{ __('cabinet.overview.k_online') }}
                </p>
                @if ($kpi['online'] === null)
                    <p class="mt-1 font-display text-2xl font-bold text-fade">{{ __('cabinet.overview.k_offline') }}</p>
                @else
                    <p class="mt-1 font-display text-2xl font-bold tabular-nums">{{ number_format($kpi['online'], 0, ',', ' ') }}@if ($kpi['max_players'])<span class="text-sm font-normal text-moss"> / {{ number_format($kpi['max_players'], 0, ',', ' ') }}</span>@endif</p>
                @endif
                <p class="mt-1 text-xs text-moss">{{ __('cabinet.overview.k_peak', ['n' => number_format($kpi['peak'], 0, ',', ' ')]) }}</p>
                <svg viewBox="0 0 100 32" preserveAspectRatio="none" class="mt-2 h-8 w-full" aria-hidden="true"><polyline points="{{ $onlineSpark }}" fill="none" stroke="var(--color-signal)" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round"/></svg>
            </div>
            <div class="rounded-xl border border-seam bg-slab p-4">
                <p class="text-sm text-moss">{{ __('cabinet.overview.k_uptime') }}</p>
                @if ($kpi['uptime'] === null)
                    <p class="mt-1 font-display text-2xl font-bold text-moss">—</p>
                    <p class="mt-1 text-xs text-moss">{{ __('cabinet.overview.k_no_data') }}</p>
                @else
                    <p class="mt-1 font-display text-2xl font-bold tabular-nums">{{ number_format($kpi['uptime'], 1, ',', ' ') }}%</p>
                    {{-- Кожна смужка — доба; колір — наскільки сервер був доступний --}}
                    <div class="mt-3 flex h-6 gap-px" aria-hidden="true">
                        @foreach ($days as $day)
                            <span @class(['flex-1 rounded-[2px]', 'bg-seam' => $day['uptime'] === null, 'bg-signal' => $day['uptime'] !== null && $day['uptime'] >= 99, 'bg-amber' => $day['uptime'] !== null && $day['uptime'] >= 90 && $day['uptime'] < 99, 'bg-fade' => $day['uptime'] !== null && $day['uptime'] < 90])
                                  title="{{ $day['date']->format('d.m') }}: {{ $day['uptime'] ?? '—' }}%"></span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)] lg:items-start">
            <div class="space-y-4">
                {{-- Голоси й онлайн за 30 днів --}}
                @php
                    $maxVotes = max(1, $days->max('votes'));
                    $maxOnline = max(1, $days->max('online'));
                    $bw = 600 / $days->count();
                    $line = $days->values()->map(fn ($d, $i) => round($i * $bw + $bw / 2, 1).','.round(160 - $d['online'] / $maxOnline * 140, 1))->implode(' ');
                @endphp
                <section class="rounded-xl border border-seam bg-slab p-5" aria-labelledby="ov-chart">
                    <h2 id="ov-chart" class="font-display text-lg font-medium">{{ __('cabinet.overview.chart') }}</h2>
                    <svg viewBox="0 0 600 160" preserveAspectRatio="none" class="mt-4 h-40 w-full" role="img" aria-label="{{ __('cabinet.overview.chart') }}">
                        @foreach ([40, 80, 120] as $y)
                            <line x1="0" x2="600" y1="{{ $y }}" y2="{{ $y }}" stroke="var(--color-seam)" stroke-dasharray="3 5" vector-effect="non-scaling-stroke"/>
                        @endforeach
                        @foreach ($days->values() as $i => $day)
                            @php $h = $day['votes'] / $maxVotes * 140; @endphp
                            <rect x="{{ round($i * $bw + $bw * 0.18, 1) }}" y="{{ round(160 - $h, 1) }}" width="{{ round($bw * 0.64, 1) }}" height="{{ round($h, 1) }}" rx="2" fill="var(--color-amber)" opacity=".85">
                                <title>{{ __('cabinet.overview.chart_tip', ['date' => $day['date']->format('d.m'), 'votes' => $day['votes'], 'online' => $day['online']]) }}</title>
                            </rect>
                        @endforeach
                        <polyline points="{{ $line }}" fill="none" stroke="var(--color-signal)" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round"/>
                    </svg>
                    <div class="mt-2 flex flex-wrap justify-between gap-2 text-xs text-moss">
                        <span class="tabular-nums">{{ $days->first()['date']->format('d.m') }}</span>
                        <span class="flex gap-4"><span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-amber"></span>{{ __('cabinet.overview.l_votes') }}</span><span class="flex items-center gap-1.5"><span class="h-0.5 w-3 bg-signal"></span>{{ __('cabinet.overview.l_online') }}</span></span>
                        <span class="tabular-nums">{{ $days->last()['date']->format('d.m') }}</span>
                    </div>
                </section>

                {{-- Останні голоси --}}
                <section class="rounded-xl border border-seam bg-slab p-5" aria-labelledby="ov-votes">
                    <h2 id="ov-votes" class="font-display text-lg font-medium">{{ __('cabinet.votes.title') }}</h2>
                    @forelse ($votes as $vote)
                        <p class="flex items-center gap-3 border-b border-seam py-2.5 text-sm last:border-0">
                            <span class="min-w-0 truncate font-medium">{{ $vote->nickname ?: __('cabinet.votes.anonymous') }}</span>
                            @if ($vote->callback_status && $vote->callback_status !== 'none')
                                <span @class(['shrink-0 rounded-full px-2 py-0.5 text-xs', 'bg-signal/10 text-signal' => $vote->callback_status === 'delivered', 'bg-amber/10 text-amber' => $vote->callback_status === 'pending', 'bg-fade/10 text-fade' => $vote->callback_status === 'failed'])>{{ __('cabinet.votes.callback_'.$vote->callback_status) }}</span>
                            @endif
                            <time class="ml-auto shrink-0 text-xs text-moss" datetime="{{ $vote->created_at->toIso8601String() }}">{{ $vote->created_at->locale($locale)->diffForHumans() }}</time>
                        </p>
                    @empty
                        <p class="mt-3 text-sm text-moss">{{ __('cabinet.votes.empty') }}</p>
                    @endforelse
                </section>
            </div>

            <div class="space-y-4">
                {{-- Що зробити --}}
                @php $doneCount = collect($todo)->where('done', true)->count(); @endphp
                <section class="rounded-xl border border-seam bg-slab p-5" aria-labelledby="ov-todo">
                    <h2 id="ov-todo" class="flex items-baseline justify-between font-display text-lg font-medium">{{ __('cabinet.overview.todo') }}<span class="font-sans text-sm text-moss tabular-nums">{{ $doneCount }} / {{ count($todo) }}</span></h2>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slab-2"><div class="h-full rounded-full bg-amber" style="width: {{ round($doneCount / count($todo) * 100) }}%"></div></div>
                    @if ($doneCount === count($todo))
                        <p class="mt-3 text-sm text-signal">{{ __('cabinet.overview.todo_done') }}</p>
                    @endif
                    <ul class="mt-3 space-y-2">
                        @foreach ($todo as $item)
                            <li class="flex items-center gap-3 rounded-lg border border-seam px-3 py-2.5 text-sm">
                                <span @class(['grid size-5 shrink-0 place-items-center rounded-full border-2 text-[11px] font-bold', 'border-signal bg-signal text-slab' => $item['done'], 'border-seam' => ! $item['done']]) aria-hidden="true">@if ($item['done'])✓@endif</span>
                                <span @class(['min-w-0 flex-1', 'text-moss line-through' => $item['done']])>{{ __('cabinet.overview.t_'.$item['key']) }}</span>
                                @unless ($item['done'])
                                    <a href="{{ $item['url'] }}" class="shrink-0 rounded-md border border-seam px-2.5 py-1 text-xs font-medium hover:border-amber">{{ __('cabinet.overview.go') }}</a>
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                </section>

                {{-- Активні послуги --}}
                <section class="rounded-xl border border-seam bg-slab p-5" aria-labelledby="ov-services">
                    <h2 id="ov-services" class="flex items-baseline justify-between font-display text-lg font-medium">{{ __('cabinet.overview.services') }}
                        <a href="{{ route('cabinet.servers.show', $current) }}#s-promo" class="font-sans text-sm text-moss hover:text-bone">{{ __('cabinet.overview.manage') }}</a></h2>
                    @forelse ($services as $order)
                        @php
                            $total = $order->starts_at && $order->ends_at ? max(1, $order->starts_at->diffInSeconds($order->ends_at)) : null;
                            $left = $total ? max(0, min(100, round(now()->diffInSeconds($order->ends_at, false) / $total * 100))) : null;
                            $ending = $order->ends_at && $order->ends_at->lt(now()->addDays(3));
                        @endphp
                        <div @class(['mt-3 rounded-lg border px-3 py-2.5 text-sm', 'border-fade/50' => $ending, 'border-seam' => ! $ending])>
                            <p class="flex items-baseline justify-between gap-2"><span class="font-medium">{{ $order->tariff->name }}</span>@if ($order->auto_renew)<span class="shrink-0 text-xs text-signal">{{ __('cabinet.overview.auto') }}</span>@endif</p>
                            @if ($order->ends_at)
                                <p @class(['text-xs', 'text-fade' => $ending, 'text-moss' => ! $ending])>{{ $ending ? __('cabinet.overview.ending', ['time' => $order->ends_at->locale($locale)->diffForHumans()]) : __('cabinet.overview.until', ['date' => $order->ends_at->copy()->subSecond()->format('d.m.Y')]) }}</p>
                                <div class="mt-2 h-1 overflow-hidden rounded-full bg-slab-2"><div @class(['h-full', 'bg-fade' => $ending, 'bg-amber' => ! $ending]) style="width: {{ $left }}%"></div></div>
                            @endif
                        </div>
                    @empty
                        <p class="mt-3 text-sm text-moss">{{ __('cabinet.overview.services_empty') }}</p>
                    @endforelse
                </section>
            </div>
        </div>

        {{-- Усі сервери: керування --}}
        <section class="mt-10" aria-labelledby="ov-all">
            <h2 id="ov-all" class="font-display text-lg font-medium">{{ __('cabinet.overview.all') }}</h2>
            <ul class="mt-4 border-t border-seam">
                @foreach ($servers as $server)
                    <li class="grid grid-cols-[1fr_auto] items-center gap-x-6 gap-y-1 border-b border-seam py-4 sm:grid-cols-[1fr_8rem_6rem_6rem_auto]">
                        <div class="min-w-0">
                            <a href="{{ route('cabinet.servers.show', $server) }}" class="font-medium hover:text-amber">{{ $server->name }}</a>
                            <p class="flex flex-wrap gap-x-3 text-sm text-moss"><span>{{ $server->game->name }}</span><span>{{ $server->address() }}</span></p>
                        </div>
                        <div class="hidden sm:block"><x-status-badge :status="$server->status" /></div>
                        <p class="hidden text-sm tabular-nums sm:block">{{ $server->rank ? '#'.$server->rank : '—' }}</p>
                        <p class="hidden text-sm tabular-nums text-moss sm:block">{{ $server->votes_month }} {{ trans_choice('ui.server.votes', $server->votes_month) }}</p>
                        <a href="{{ route('cabinet.servers.edit', $server) }}" class="text-sm text-moss hover:text-bone">{{ __('cabinet.edit') }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>
