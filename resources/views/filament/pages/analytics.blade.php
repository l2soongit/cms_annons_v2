@php
    use App\Analytics\Labels;
    use App\Analytics\Report;
    use App\Billing\Money;
    use App\Filament\Pages\Analytics;

    $r = $this->report();
    $prev = $r->previous();
    $labels = $this->labels();
    $money = Analytics::canSeeMoney();

    // Рядки таблиці «топ» з виміру: назва, числа, частка
    $rows = function (string $dim, int $limit = 10, array $cols = ['visitors', 'views'], ?callable $href = null, string $order = 'views') use ($r, $labels) {
        $top = $r->top($dim, $limit, $order);
        $labels->preload($dim, $top);

        return $top->map(fn ($t) => [
            'label' => $labels->label($dim, $t['key']),
            'values' => array_merge(array_map(fn ($c) => $t[$c], $cols), [$t['share'].'%']),
            'share' => $t['share'],
            'href' => $href ? $href($t['key']) : null,
        ]);
    };
    $delta = function ($now, $before, bool $inverse = false) {
        $d = Report::delta($now, $before);
        if ($d === null) return '';
        $good = $inverse ? $d <= 0 : $d >= 0;

        return '<span class="an-delta '.($good ? 'up' : 'down').'">'.($d > 0 ? '+' : '').$d.'%</span>';
    };
    $num = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $period = $r->from->format('d.m.Y').($r->days() > 1 ? ' — '.$r->to->format('d.m.Y') : '');
@endphp
<x-filament-panels::page>
<style>
    .an{--an-accent:#6366f1;--an-line:rgba(127,127,127,.18);--an-card:rgba(127,127,127,.06)}
    .an-bar{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
    .an-chip{border:1px solid var(--an-line);border-radius:999px;padding:5px 12px;font-size:13px;cursor:pointer;background:transparent;color:inherit;line-height:1.2}
    .an-chip[aria-pressed=true]{background:var(--an-accent);border-color:var(--an-accent);color:#fff}
    .an-tabs{display:flex;gap:2px;overflow-x:auto;border-bottom:1px solid var(--an-line);scrollbar-width:none}
    .an-tab{padding:9px 14px;font-size:14px;border:0;background:none;color:inherit;opacity:.65;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;white-space:nowrap}
    .an-tab[aria-selected=true]{opacity:1;border-bottom-color:var(--an-accent);font-weight:600}
    .an-grid{display:grid;gap:16px}
    .an-kpis{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr))}
    .an-kpi{border:1px solid var(--an-line);border-radius:12px;padding:14px 16px;background:var(--an-card)}
    .an-kpi dt{font-size:12px;opacity:.65}
    .an-kpi dd{margin:6px 0 0;font-size:22px;font-weight:650;font-variant-numeric:tabular-nums;display:flex;align-items:baseline;gap:8px;flex-wrap:wrap}
    .an-kpi small{font-size:12px;font-weight:400;opacity:.6}
    .an-delta{font-size:12px;font-weight:600;padding:1px 6px;border-radius:6px}
    .an-delta.up{color:#16a34a;background:rgba(22,163,74,.12)}
    .an-delta.down{color:#dc2626;background:rgba(220,38,38,.12)}
    .an-card{border:1px solid var(--an-line);border-radius:12px;padding:16px;background:var(--an-card);min-width:0}
    .an-card h3{font-size:14px;font-weight:600;margin:0 0 12px}
    .an-cols{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr))}
    .an-note{font-size:12px;opacity:.6}
    .an-funnel{display:grid;gap:8px}
    .an-step{display:grid;grid-template-columns:minmax(120px,220px) 1fr auto;gap:10px;align-items:center;font-size:13px}
    .an-step i{display:block;height:22px;border-radius:6px;background:var(--an-accent);opacity:.85;min-width:2px}
    .an-hours{display:flex;align-items:flex-end;gap:3px;height:120px}
    .an-hours div{flex:1;background:var(--an-accent);opacity:.75;border-radius:3px 3px 0 0;min-height:1px}
    .an-live{display:inline-flex;align-items:center;gap:8px}
    .an-live:before{content:"";width:10px;height:10px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 0 rgba(34,197,94,.6);animation:an-pulse 1.6s infinite}
    @keyframes an-pulse{to{box-shadow:0 0 0 10px rgba(34,197,94,0)}}
    @media (prefers-reduced-motion:reduce){.an-live:before{animation:none}}
</style>

<div class="an an-grid">
    {{-- Період --}}
    <div class="an-bar" role="group" aria-label="Період">
        @foreach (Analytics::PERIODS as $key => $label)
            <button type="button" class="an-chip" wire:click="setPeriod('{{ $key }}')" aria-pressed="{{ $this->period === (string) $key ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
        @if ($this->period === 'custom')
            <input type="date" wire:model.live="from" class="an-chip" aria-label="З" max="{{ today()->toDateString() }}">
            <span>—</span>
            <input type="date" wire:model.live="to" class="an-chip" aria-label="По" max="{{ today()->toDateString() }}">
        @endif
        <span class="an-note" style="margin-left:auto">{{ $period }} · порівняння з {{ $prev->from->format('d.m') }}–{{ $prev->to->format('d.m') }}</span>
    </div>

    {{-- Вкладки --}}
    <div class="an-tabs" role="tablist">
        @foreach (Analytics::TABS as $key => $label)
            @continue($key === 'business' && ! $money)
            <button type="button" role="tab" class="an-tab" wire:click="setTab('{{ $key }}')" aria-selected="{{ $this->tab === $key ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div wire:loading.delay style="font-size:12px;opacity:.6">Завантаження…</div>

        {{-- ═════════ Огляд ═════════ --}}
        @if ($this->tab === 'overview')
            @php
                $t = $r->totals();
                $p = $prev->totals();
                $ev = $r->sums('event');
                $evPrev = $prev->sums('event');
                $biz = $this->business()->totals();
                $bizPrev = $this->business()->totals($prev);
            @endphp
            <dl class="an-kpis">
                <div class="an-kpi"><dt>Відвідувачі</dt><dd>{{ $num($t['visitors']) }} {!! $delta($t['visitors'], $p['visitors']) !!}</dd></div>
                <div class="an-kpi"><dt>Перегляди</dt><dd>{{ $num($t['views']) }} {!! $delta($t['views'], $p['views']) !!}</dd></div>
                <div class="an-kpi"><dt>Візити</dt><dd>{{ $num($t['sessions']) }} {!! $delta($t['sessions'], $p['sessions']) !!}</dd></div>
                <div class="an-kpi"><dt>Сторінок за візит</dt><dd>{{ $t['per_session'] ?? '—' }} {!! $delta($t['per_session'], $p['per_session']) !!}</dd></div>
                <div class="an-kpi"><dt>Відмови</dt><dd>{{ $t['bounce_rate'] !== null ? $t['bounce_rate'].'%' : '—' }} {!! $delta($t['bounce_rate'], $p['bounce_rate'], true) !!}</dd></div>
                <div class="an-kpi"><dt>Тривалість візиту</dt><dd>{{ Labels::duration($t['avg_duration']) }} {!! $delta($t['avg_duration'], $p['avg_duration']) !!}</dd></div>
                <div class="an-kpi"><dt>Голоси</dt><dd>{{ $num($biz['votes']) }} {!! $delta($biz['votes'], $bizPrev['votes']) !!}</dd></div>
                <div class="an-kpi"><dt>Реєстрації</dt><dd>{{ $num($biz['users']) }} {!! $delta($biz['users'], $bizPrev['users']) !!}</dd></div>
                <div class="an-kpi"><dt>Нові сервери</dt><dd>{{ $num($biz['servers']) }} {!! $delta($biz['servers'], $bizPrev['servers']) !!}</dd></div>
                <div class="an-kpi"><dt>Переходи на сайти серверів</dt><dd>{{ $num(($ev['website'] ?? 0) + ($ev['discord'] ?? 0)) }} {!! $delta(($ev['website'] ?? 0) + ($ev['discord'] ?? 0), ($evPrev['website'] ?? 0) + ($evPrev['discord'] ?? 0)) !!}</dd></div>
                @if ($money)
                    <div class="an-kpi"><dt>Поповнення</dt><dd>{{ Money::format($biz['deposits']) }} {!! $delta($biz['deposits'], $bizPrev['deposits']) !!}</dd></div>
                @endif
            </dl>

            <section class="an-card">
                <h3>Відвідуваність</h3>
                @if ($t['views'] === 0)
                    <p class="an-note">За цей період ще немає даних. Перегляди записуються з моменту оновлення сайту, звіти перераховуються кожні 5 хвилин (кнопка «Оновити зараз» — одразу).</p>
                @endif
                @php $__points1 = $r->series(); @endphp
                @php $__series2 = [['key' => 'visitors', 'label' => 'Відвідувачі', 'color' => '#6366f1'], ['key' => 'views', 'label' => 'Перегляди', 'color' => '#f59e0b']]; @endphp
                <x-stats.chart :points="$__points1" :series="$__series2" />
            </section>

            <div class="an-cols">
                @php $__rows3 = $rows('source', 7, ['visitors', 'views']); @endphp
                <x-stats.table class="an-card" title="Джерела" :rows="$__rows3" :columns="['Відвідувачі', 'Візити', '%']" empty="Поки немає даних" />
                @php $__rows4 = $rows('page', 7, ['visitors', 'views'], fn ($k) => url($k)); @endphp
                <x-stats.table class="an-card" title="Популярні сторінки" :rows="$__rows4" :columns="['Відвідувачі', 'Перегляди', '%']" empty="Поки немає даних" />
                @php $__rows5 = $rows('device', 5); @endphp
                <x-stats.table class="an-card" title="Пристрої" :rows="$__rows5" :columns="['Відвідувачі', 'Перегляди', '%']" empty="Поки немає даних" />
                @php $__rows6 = $rows('game', 7); @endphp
                <x-stats.table class="an-card" title="Ігри" :rows="$__rows6" :columns="['Відвідувачі', 'Перегляди', '%']" empty="Поки немає даних" />
            </div>

        {{-- ═════════ Джерела ═════════ --}}
        @elseif ($this->tab === 'sources')
            <div class="an-cols">
                @php $__rows7 = $rows('source', 10); @endphp
                <x-stats.table class="an-card" title="Канали" :rows="$__rows7" :columns="['Відвідувачі', 'Візити', '%']" empty="Поки немає даних" />
                @php $__rows8 = $rows('referrer', 30, ['visitors', 'views'], fn ($k) => 'https://'.$k); @endphp
                <x-stats.table class="an-card" title="Сайти-джерела" :rows="$__rows8" :columns="['Відвідувачі', 'Візити', '%']" empty="Переходів з інших сайтів ще не було" />
                @php $__rows9 = $rows('utm', 30); @endphp
                <x-stats.table class="an-card" title="Кампанії (utm_source / utm_medium / utm_campaign)" :rows="$__rows9" :columns="['Відвідувачі', 'Візити', '%']" empty="Додайте до посилань ?utm_source=…&utm_campaign=… — тут з’являться кампанії" />
                @php $__rows10 = $rows('landing', 30, ['visitors', 'views'], fn ($k) => url($k)); @endphp
                <x-stats.table class="an-card" title="Сторінки входу" :rows="$__rows10" :columns="['Відвідувачі', 'Входи', '%']" empty="Поки немає даних" />
            </div>
            <p class="an-note">Канал визначається за першою сторінкою візиту: пошук (Google, Bing…), соцмережі, месенджери (Telegram, Discord…), інші сайти, прямі заходи (закладки, введена адреса) або UTM-мітка в посиланні.</p>

        {{-- ═════════ Вміст ═════════ --}}
        @elseif ($this->tab === 'content')
            <div class="an-cols">
                @php $__rows11 = $rows('route', 15); @endphp
                <x-stats.table class="an-card" title="Типи сторінок" :rows="$__rows11" :columns="['Відвідувачі', 'Перегляди', '%']" empty="Поки немає даних" />
                @php $__rows12 = $rows('game', 20); @endphp
                <x-stats.table class="an-card" title="Ігри" :rows="$__rows12" :columns="['Відвідувачі', 'Перегляди', '%']" empty="Поки немає даних" />
            </div>
            @php
                $servers = $r->top('server', 30);
                $labels->preload('server', $servers);
                $slugs = \App\Models\Server::withTrashed()->whereKey($servers->pluck('key')->map(fn ($k) => (int) $k))->pluck('slug', 'id');
            @endphp
            @php $__rows13 = $servers->map(fn ($t) => ['label' => $labels->label('server', $t['key']), 'values' => [$t['visitors'], $t['views'], $t['share'].'%'], 'share' => $t['share'], 'href' => isset($slugs[(int) $t['key']]) ? route('servers.show', $slugs[(int) $t['key']]) : null]); @endphp
            <x-stats.table class="an-card" title="Сервери за відвідуваністю сторінок" :rows="$__rows13" :columns="['Відвідувачі', 'Перегляди', '%']" empty="Поки немає даних" />
            @php $__rows14 = $rows('page', 50, ['visitors', 'views'], fn ($k) => url($k)); @endphp
            <x-stats.table class="an-card" title="Усі сторінки" :rows="$__rows14" :columns="['Відвідувачі', 'Перегляди', '%']" empty="Поки немає даних" />
            <div class="an-cols">
                @php $__rows15 = $rows('search', 30, ['visitors', 'views']); @endphp
                <x-stats.table class="an-card" title="Пошук на сайті" :rows="$__rows15" :columns="['Людей', 'Запитів', '%']" empty="Пошуком ще не користувалися" />
                @php $__rows16 = $rows('outbound', 30, ['visitors', 'views'], fn ($k) => 'https://'.$k); @endphp
                <x-stats.table class="an-card" title="Переходи на інші сайти" :rows="$__rows16" :columns="['Людей', 'Переходів', '%']" empty="Поки немає даних" />
                @php $__rows17 = $rows('locale', 5); @endphp
                <x-stats.table class="an-card" title="Мовні версії сайту" :rows="$__rows17" :columns="['Відвідувачі', 'Перегляди', '%']" empty="Поки немає даних" />
            </div>
            <p class="an-note">«Пошук на сайті»: запити з позначкою «нічого не знайдено» показують, яких серверів або ігор шукають, а в каталозі їх немає.</p>

        {{-- ═════════ Аудиторія ═════════ --}}
        @elseif ($this->tab === 'audience')
            <div class="an-cols">
                @php $__rows18 = $rows('device', 5); @endphp
                <x-stats.table class="an-card" title="Пристрої" :rows="$__rows18" :columns="['Відвідувачі', 'Перегляди', '%']" empty="Поки немає даних" />
                @php $__rows19 = $rows('browser', 12); @endphp
                <x-stats.table class="an-card" title="Браузери" :rows="$__rows19" :columns="['Відвідувачі', 'Перегляди', '%']" empty="Поки немає даних" />
                @php $__rows20 = $rows('os', 12); @endphp
                <x-stats.table class="an-card" title="Операційні системи" :rows="$__rows20" :columns="['Відвідувачі', 'Перегляди', '%']" empty="Поки немає даних" />
                @php $__rows21 = $rows('lang', 15); @endphp
                <x-stats.table class="an-card" title="Мови браузера" :rows="$__rows21" :columns="['Відвідувачі', 'Перегляди', '%']" empty="Поки немає даних" />
                @php $countries = $rows('country', 25); @endphp
                <div class="an-card">
                    <x-stats.table title="Країни" :rows="$countries" :columns="['Відвідувачі', 'Перегляди', '%']" empty="Поки немає даних" />
                    @if ($countries->count() <= 1 && ($countries->first()['label'] ?? '') === __('analytics.unknown'))
                        <p class="an-note" style="margin-top:10px">Країну передає CDN або хостинг (заголовок CF-IPCountry від Cloudflare чи GeoIP веб-сервера). Підключіть сайт до Cloudflare — і тут з’являться країни; до того орієнтуйтеся на «Мови браузера».</p>
                    @endif
                </div>
                <section class="an-card">
                    <h3>Активність за годинами доби</h3>
                    @php $hours = $r->hours(); @endphp
                    @php $peak = max(1, max($hours)); @endphp
                    <div class="an-hours" role="img" aria-label="Перегляди за годинами">
                        @foreach ($hours as $h => $n)
                            <div style="height:{{ $n ? max(2, round($n / $peak * 100)) : 0 }}%" title="{{ sprintf('%02d:00', $h) }} — {{ $num($n) }} переглядів"></div>
                        @endforeach
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:11px;opacity:.55;margin-top:4px"><span>00</span><span>06</span><span>12</span><span>18</span><span>23</span></div>
                    <p class="an-note" style="margin-top:8px">Найкращий час для анонсів і відкриттів — пікові години.</p>
                </section>
            </div>

        {{-- ═════════ Дії й конверсії ═════════ --}}
        @elseif ($this->tab === 'actions')
            @php
                $ev = $r->sums('event');
                $evPrev = $prev->sums('event');
                $evVisitors = $r->sums('event', 'visitors');
                $funnel = $r->sums('funnel', 'visitors');
                $base = max(1, $funnel['visit'] ?? 0);
            @endphp
            <dl class="an-kpis">
                @foreach (\App\Analytics\Tracker::EVENTS as $name)
                    @continue(! isset($ev[$name]) && ! isset($evPrev[$name]))
                    <div class="an-kpi"><dt>{{ __("analytics.event.{$name}") }}</dt><dd>{{ $num($ev[$name] ?? 0) }} {!! $delta($ev[$name] ?? 0, $evPrev[$name] ?? 0) !!} <small>{{ $num($evVisitors[$name] ?? 0) }} люд.</small></dd></div>
                @endforeach
            </dl>
            @if (! $ev)
                <p class="an-note">Подій за цей період ще немає.</p>
            @endif

            <section class="an-card">
                <h3>Воронка гравця: від заходу на сайт до голосу</h3>
                <div class="an-funnel">
                    @foreach (['visit', 'server_view', 'vote_click', 'vote', 'website'] as $step)
                        @php $n = $funnel[$step] ?? 0; @endphp
                        <div class="an-step"><span>{{ __("analytics.funnel.{$step}") }}</span><i style="width:{{ round($n / $base * 100, 1) }}%"></i><b style="font-variant-numeric:tabular-nums">{{ $num($n) }} <small style="opacity:.6;font-weight:400">{{ round($n / $base * 100, 1) }}%</small></b></div>
                    @endforeach
                </div>
            </section>
            <section class="an-card">
                <h3>Воронка власника: від заходу до доданого сервера</h3>
                <div class="an-funnel">
                    @foreach (['visit', 'register', 'server_add'] as $step)
                        @php $n = $funnel[$step] ?? 0; @endphp
                        <div class="an-step"><span>{{ __("analytics.funnel.{$step}") }}</span><i style="width:{{ round($n / $base * 100, 2) }}%"></i><b style="font-variant-numeric:tabular-nums">{{ $num($n) }} <small style="opacity:.6;font-weight:400">{{ round($n / $base * 100, 2) }}%</small></b></div>
                    @endforeach
                </div>
                <p class="an-note" style="margin-top:8px">Кожен крок — частка всіх відвідувачів періоду (одна людина рахується один раз на день).</p>
            </section>

            <div class="an-cols">
                <section class="an-card">
                    <h3>Рекламні банери</h3>
                    @php $banners = \App\Models\Banner::where('ends_at', '>=', $r->from)->where('starts_at', '<=', $r->to->copy()->endOfDay())->orderByDesc('clicks')->limit(20)->get(); @endphp
                    @php $__rows22 = $banners->map(fn ($b) => ['label' => '#'.$b->id.' · '.$b->placement.' · '.parse_url((string) $b->url, PHP_URL_HOST), 'values' => [$b->impressions, $b->clicks, $b->ctr().'%'], 'share' => min(100, $b->ctr() * 10)]); @endphp
                    <x-stats.table :rows="$__rows22" :columns="['Показів', 'Кліків', 'CTR']" empty="Банерів у цьому періоді не було" />
                    <p class="an-note" style="margin-top:8px">Покази й кліки — за весь час показу банера.</p>
                </section>
                @php $__rows23 = $rows('banner', 20, ['visitors', 'views']); @endphp
                <x-stats.table class="an-card" title="Кліки по банерах за період" :rows="$__rows23" :columns="['Людей', 'Кліків', '%']" empty="Кліків не було" />
            </div>

        {{-- ═════════ Бізнес ═════════ --}}
        @elseif ($this->tab === 'business')
            @php
                $biz = $this->business();
                $b = $biz->totals();
                $bp = $biz->totals($prev);
                $series = $biz->series();
                $visitors = $r->totals()['visitors'];
            @endphp
            <dl class="an-kpis">
                <div class="an-kpi"><dt>Поповнення</dt><dd>{{ Money::format($b['deposits']) }} {!! $delta($b['deposits'], $bp['deposits']) !!}</dd></div>
                <div class="an-kpi"><dt>Оплачено послуг</dt><dd>{{ Money::format($b['spent']) }} {!! $delta($b['spent'], $bp['spent']) !!} <small>{{ $num($b['orders']) }} замовл.</small></dd></div>
                <div class="an-kpi"><dt>Платників</dt><dd>{{ $num($b['payers']) }} {!! $delta($b['payers'], $bp['payers']) !!}</dd></div>
                <div class="an-kpi"><dt>Середнє поповнення на платника</dt><dd>{{ $b['payers'] ? Money::format($b['deposits'] / $b['payers']) : '—' }}</dd></div>
                <div class="an-kpi"><dt>Реєстрації</dt><dd>{{ $num($b['users']) }} {!! $delta($b['users'], $bp['users']) !!} <small>{{ $visitors ? round($b['users'] / $visitors * 100, 2).'% відвідувачів' : '' }}</small></dd></div>
                <div class="an-kpi"><dt>Нові сервери</dt><dd>{{ $num($b['servers']) }} {!! $delta($b['servers'], $bp['servers']) !!}</dd></div>
                <div class="an-kpi"><dt>Голоси</dt><dd>{{ $num($b['votes']) }} {!! $delta($b['votes'], $bp['votes']) !!}</dd></div>
                <div class="an-kpi"><dt>Підозрілі голоси</dt><dd>{{ $num($b['suspicious']) }} {!! $delta($b['suspicious'], $bp['suspicious'], true) !!} <small>{{ $b['votes'] + $b['suspicious'] ? round($b['suspicious'] / ($b['votes'] + $b['suspicious']) * 100, 1).'%' : '' }}</small></dd></div>
                <div class="an-kpi"><dt>Відгуки</dt><dd>{{ $num($b['reviews']) }} {!! $delta($b['reviews'], $bp['reviews']) !!}</dd></div>
            </dl>
            <section class="an-card">
                <h3>Поповнення по днях</h3>
                @php $__series24 = [['key' => 'deposits', 'label' => 'Поповнення', 'color' => '#16a34a']]; @endphp
                <x-stats.chart :points="$series" :series="$__series24" money />
            </section>
            <section class="an-card">
                <h3>Зростання порталу</h3>
                @php $__series25 = [['key' => 'votes', 'label' => 'Голоси', 'color' => '#6366f1'], ['key' => 'users', 'label' => 'Реєстрації', 'color' => '#f59e0b'], ['key' => 'servers', 'label' => 'Нові сервери', 'color' => '#ec4899']]; @endphp
                <x-stats.chart :points="$series" :series="$__series25" />
            </section>
            <div class="an-cols">
                @php $services = collect($biz->services()); @endphp
                @php $servicesSum = max(1, $services->sum('total')); @endphp
                @php $__rows26 = $services->map(fn ($s) => ['label' => $s['label'], 'values' => [$s['count'], Money::format($s['total'])], 'share' => round($s['total'] / $servicesSum * 100, 1)]); @endphp
                <x-stats.table class="an-card" title="Виручка за послугами" :rows="$__rows26" :columns="['Замовлень', 'Сума']" empty="Послуг за період не купували" color="#16a34a" />
                @php $games = collect($biz->games()); @endphp
                @php $gamesSum = max(1, $games->sum('votes')); @endphp
                @php $__rows27 = $games->map(fn ($g) => ['label' => $g['name'], 'values' => [$g['votes'], round($g['votes'] / $gamesSum * 100, 1).'%'], 'share' => round($g['votes'] / $gamesSum * 100, 1)]); @endphp
                <x-stats.table class="an-card" title="Ігри за голосами" :rows="$__rows27" :columns="['Голосів', '%']" empty="Голосів за період не було" />
            </div>

        {{-- ═════════ Роботи ═════════ --}}
        @elseif ($this->tab === 'bots')
            <section class="an-card">
                <h3>Відвідування пошукових і AI-роботів</h3>
                @php $__points28 = $r->series('bot'); @endphp
                @php $__series29 = [['key' => 'views', 'label' => 'Сторінок проіндексовано', 'color' => '#0ea5e9']]; @endphp
                <x-stats.chart :points="$__points28" :series="$__series29" />
            </section>
            @php $__rows30 = $rows('bot', 40, ['views']); @endphp
            <x-stats.table class="an-card" title="Роботи" :rows="$__rows30" :columns="['Запитів', '%']" empty="Роботи ще не заходили" color="#0ea5e9" />
            <p class="an-note">Роботи не враховуються у відвідувачах. Якщо Google чи Bing заходять рідко — перевірте sitemap.xml у Search Console / Bing Webmaster.</p>

        {{-- ═════════ Зараз на сайті ═════════ --}}
        @elseif ($this->tab === 'realtime')
            <div wire:poll.15s class="an-grid">
                @php $live = Report::realtime(); @endphp
                <dl class="an-kpis">
                    <div class="an-kpi"><dt>Зараз на сайті</dt><dd><span class="an-live">{{ $num($live['now']) }}</span> <small>за 5 хв</small></dd></div>
                    <div class="an-kpi"><dt>Переглядів за 30 хвилин</dt><dd>{{ $num($live['views']) }}</dd></div>
                </dl>
                <section class="an-card">
                    <h3>Перегляди похвилинно (30 хв)</h3>
                    @php $peak = max(1, max($live['minutes'])); @endphp
                    <div class="an-hours" role="img" aria-label="Перегляди за хвилину">
                        @foreach ($live['minutes'] as $i => $n)
                            <div style="height:{{ $n ? max(3, round($n / $peak * 100)) : 0 }}%" title="{{ 29 - $i }} хв тому — {{ $n }}"></div>
                        @endforeach
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:11px;opacity:.55;margin-top:4px"><span>−30 хв</span><span>−15 хв</span><span>зараз</span></div>
                </section>
                <div class="an-cols">
                    @php $pagesSum = max(1, $live['pages']->sum('visitors')); @endphp
                    @php $__rows31 = $live['pages']->map(fn ($p) => ['label' => $p->key, 'values' => [$p->visitors, $p->views], 'share' => round($p->visitors / $pagesSum * 100, 1), 'href' => url($p->key)]); @endphp
                    <x-stats.table class="an-card" title="Що дивляться" :rows="$__rows31" :columns="['Людей', 'Переглядів']" empty="Зараз нікого немає" />
                    @php $srcSum = max(1, $live['sources']->sum('visitors')); @endphp
                    @php $__rows32 = $live['sources']->map(fn ($s) => ['label' => trans()->has('analytics.source.'.$s->key) ? __('analytics.source.'.$s->key) : $s->key, 'values' => [$s->visitors, $s->views], 'share' => round($s->visitors / $srcSum * 100, 1)]); @endphp
                    <x-stats.table class="an-card" title="Звідки прийшли" :rows="$__rows32" :columns="['Людей', 'Переглядів']" empty="—" />
                </div>
                <p class="an-note">Оновлюється кожні 15 секунд.</p>
            </div>
    @endif

    <p class="an-note">Власна аналітика без cookies і сторонніх сервісів: відвідувач — анонімний ідентифікатор на одну добу (IP не зберігається), роботи відфільтровано, адміністратори й модератори не враховуються. Звіти оновлюються кожні 5 хвилин; сирі дані зберігаються {{ config('graj.analytics.raw_retention_days') }} днів, зведення — назавжди.</p>
</div>
</x-filament-panels::page>
