@php
    use App\Support\HomeContent;
    use App\Support\HomePageData;

    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
    $kpis = $data['kpis'];
    $daysLeft = (int) max(0, ceil(now()->diffInDays(\Illuminate\Support\Carbon::parse($data['season_end']), false)));
    $rankingTarget = HomeContent::shows('ranking') ? '#top' : ($data['games'][0]['url'] ?? '#');

    // Міні-графік онлайну за добу, як у демо: 96×28, зелений якщо зросло
    $spark = function (array $pts): string {
        if (count($pts) < 2) {
            return '';
        }
        $max = max($pts);
        $min = min($pts);
        $last = count($pts) - 1;
        $d = collect($pts)->map(fn ($v, $i) => ($i ? 'L' : 'M').number_format($i / $last * 96, 1, '.', '').','.number_format(26 - ($v - $min) / (($max - $min) ?: 1) * 24, 1, '.', ''))->implode(' ');
        $color = end($pts) >= $pts[0] ? '#34d399' : '#fb7185';

        return '<svg class="spark" viewBox="0 0 96 28" aria-hidden="true"><path d="'.$d.'" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round"/></svg>';
    };

    $icons = [
        '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
        '<path d="M20 12v10H4V12M2 7h20v5H2zM12 22V7"/>',
        '<path d="M3 3v18h18M7 15l4-4 3 3 6-7"/>',
        '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
        '<path d="M16 18l6-6-6-6M8 6l-6 6 6 6"/>',
        '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
    ];
    $check = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>';

    $price = fn ($p) => $p === null ? '—' : $fmt($p)."\u{00A0}₴";
    $plans = [
        [__('ui.home.plan_base'), '0 ₴', __('ui.home.plan_forever'), false],
        [__('ui.home.plan_vip'), $data['plans']['vip'] !== null ? __('ui.home.plan_from').' '.$price($data['plans']['vip']) : '—', __('ui.home.plan_per'), true],
        [__('ui.home.plan_highlight'), $data['plans']['highlight'] !== null ? __('ui.home.plan_from').' '.$price($data['plans']['highlight']) : '—', __('ui.home.plan_per'), false],
    ];

    // Те, що скрипт малює сам: топ місяця, відліки, живі події
    $client = [
        'arena' => $data['arena'],
        'seasonEnd' => $data['season_end'],
        'feed' => ['url' => route('home.live'), 'after' => $feedAfter, 'enabled' => (bool) config('graj.home.live_feed')],
        'hints' => HomeContent::list('search_hints'),
        'locale' => app()->getLocale(),
        't' => [
            'firstPlace' => __('ui.home.first_place'),
            'perMonth' => __('ui.home.per_month'),
            'perDay' => __('ui.home.per_day'),
            'toLeader' => __('ui.home.to_leader'),
            'updated' => __('ui.home.updated'),
            'online' => __('ui.home.online'),
            'votes' => __('ui.server.votes'),
            'hintPrefix' => __('ui.home.hint_prefix'),
            'toastVote' => __('ui.home.toast_vote'),
            'toastFrom' => __('ui.home.toast_from'),
            'toastAnon' => __('ui.home.toast_anon'),
            'units' => [__('ui.home.unit_d'), __('ui.home.unit_h'), __('ui.home.unit_m'), __('ui.home.unit_s')],
        ],
    ];
@endphp

<x-layouts.home>
  <!-- Перший екран: пошук і живий топ місяця -->
  <section class="hero">
    @if ($heroImage = HomePageData::url(config('graj.home.hero_image')))
      <div class="hero-img" style="background-image:url('{{ $heroImage }}');--img-o:{{ max(5, min(100, (int) config('graj.home.hero_image_opacity', 35))) / 100 }}" aria-hidden="true"></div>
    @endif
    <div class="hero-fx" aria-hidden="true"><span class="beam b1"></span><span class="beam b2"></span><canvas id="embers"></canvas></div>
    <div class="wrap hero-grid">
      <div>
        <span class="label live">{{ trans_choice('ui.home.monitored', $kpis['servers'], ['n' => $fmt($kpis['servers'])]) }}</span>
        <h1 class="reveal-words" id="h1">{{ HomeContent::text('hero_title') }} <span class="grad shimmer">{{ HomeContent::text('hero_accent') }}</span></h1>
        <p class="sub">{{ HomeContent::text('hero_lead') }}</p>
        <form class="search" role="search" method="get" action="{{ route('search') }}">
          <label class="sr" for="q">{{ __('ui.search.label') }}</label>
          <input id="q" name="q" type="search" minlength="2" maxlength="64" placeholder="{{ __('ui.search.placeholder') }}">
          <button class="btn btn-hot">{{ __('ui.search.submit') }}</button>
        </form>
        @if ($quick = HomeContent::list('quick_links'))
          <div class="quick">{{ __('ui.home.quick') }} @foreach ($quick as $q)<a href="{{ route('search', ['q' => $q]) }}">{{ $q }}</a>@endforeach</div>
        @endif
        <dl class="kpis">
          <div><dd class="num" id="online" data-count="{{ $kpis['online'] }}">0</dd><dt>{{ __('ui.home.kpi_online') }}</dt></div>
          <div><dd class="num grad" id="votes" data-count="{{ $kpis['votes'] }}">0</dd><dt>{{ __('ui.home.kpi_votes') }}</dt></div>
          <div><dd class="num">{{ trans_choice('ui.home.kpi_days', $daysLeft, ['n' => $daysLeft]) }}</dd><dt>{{ __('ui.home.kpi_season') }}</dt></div>
        </dl>
      </div>

      @if (HomeContent::shows('arena') && $data['arena']['all']['rows'])
        <aside class="arena" aria-labelledby="arena-h">
          <div class="arena-head">
            <div>
              <div class="title"><h2 id="arena-h">{{ __('ui.home.arena_title') }}</h2><span class="live-badge">LIVE</span></div>
              <div class="season">{{ __('ui.home.season_left') }} <b class="num" id="season-left">&nbsp;</b></div>
            </div>
            <div class="seg" role="group" aria-label="{{ __('ui.home.arena_tabs') }}" id="arena-tabs">
              @foreach ($data['arena'] as $key => $tab)
                <button aria-pressed="{{ $loop->first ? 'true' : 'false' }}" data-game="{{ $key }}">{{ $tab['label'] }}</button>
              @endforeach
            </div>
          </div>
          {{-- Вміст будує скрипт з даних вкладки --}}
          <div class="arena-body" id="arena-body" aria-live="polite"></div>
          <div class="arena-foot"><span id="arena-updated">{{ __('ui.home.updated') }}</span><a href="{{ $rankingTarget }}" class="grad" style="font-weight:800">{{ __('ui.home.full_ranking_link') }}</a></div>
        </aside>
      @endif
    </div>
  </section>

  @if (HomeContent::shows('marquee') && $data['marquee'])
    <!-- Стрічка серверів онлайн; вміст дублюється для безшовної прокрутки -->
    @php
      $mq = collect($data['marquee'])->map(fn ($s) => '<a class="mq g-'.e($s['theme']).'" href="'.e($s['url']).'">'.($s['logo'] ? '<img class="mq-logo" src="'.e($s['logo']).'" alt="" width="24" height="24" loading="lazy">' : '').'<i class="dot"></i><span class="gtag">'.e($s['game_short']).'</span><b>'.e($s['name']).'</b><span class="num">'.e(__('ui.home.online', ['n' => $fmt($s['players'])])).'</span></a>')->implode('');
    @endphp
    <div class="marquee" aria-label="{{ __('ui.home.marquee') }}"><div class="marquee-track" id="marquee">{!! $mq !!}<span aria-hidden="true" inert style="display:contents">{!! $mq !!}</span></div></div>
  @endif

  @if (HomeContent::shows('games') && $data['games'])
    <!-- Ігри -->
    <section class="block alt" id="games">
      <div class="wrap">
        <div class="head"><div><span class="label">{{ __('ui.home.games_label') }}</span><h2>{{ __('ui.home.games_title') }}</h2></div><p>{{ __('ui.home.games_lead') }}</p></div>
        <div class="worlds" id="worlds">
          @foreach ($data['games'] as $game)
            <a @class(['world', 'g-'.$game['theme'], 'has-img' => $game['image']]) href="{{ $game['url'] }}">
              @if ($game['image'])
                <div class="scene scene-img" style="background-image:url('{{ $game['image'] }}')"></div>
              @else
                <div data-scene="{{ $game['theme'] }}" class="scene scene-{{ $game['theme'] }}"></div>
              @endif
              <span class="shine" aria-hidden="true"></span>
              @if ($game['icon'])<img class="gicon" src="{{ $game['icon'] }}" alt="" width="48" height="48" loading="lazy">@endif
              <h3 class="gtitle">{{ $game['name'] }}</h3>
              <div class="wmeta num"><span>{!! str_replace($fmt($game['servers']), '<b>'.$fmt($game['servers']).'</b>', e(trans_choice('ui.home.servers_n', $game['servers'], ['n' => $fmt($game['servers'])]))) !!}</span><span>{!! str_replace($fmt($game['online']), '<b>'.$fmt($game['online']).'</b>', e(__('ui.home.online', ['n' => $fmt($game['online'])]))) !!}</span></div>
              @if ($game['leader'])
                <div class="wlead">{{ __('ui.home.leader') }} <b style="color:var(--gc)">{{ $game['leader'] }}</b></div>
              @endif
            </a>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  @if (HomeContent::shows('ranking') && $data['ranking']['all']['rows'])
    <!-- Живий рейтинг -->
    <section class="block" id="top">
      <div class="wrap">
        <div class="head"><div><span class="label">{{ __('ui.home.ranking_label') }}</span><h2>{{ __('ui.home.ranking_title') }} <span class="grad">{{ __('ui.home.ranking_accent') }}</span></h2></div><p>{{ __('ui.home.ranking_lead') }}</p></div>
        <div class="table-card">
          <div class="table-top">
            <h3>{{ \Illuminate\Support\Str::ucfirst($data['month']) }}</h3>
            <div class="chips" role="group" aria-label="{{ __('ui.home.ranking_filter') }}" id="rank-chips">
              @foreach ($data['ranking'] as $key => $group)
                <button aria-pressed="{{ $loop->first ? 'true' : 'false' }}" data-group="{{ $key }}" @class(['hide-sm' => $loop->index > 3])>{{ $group['label'] }}</button>
              @endforeach
            </div>
          </div>
          <table>
            <thead><tr><th>{{ __('ui.home.col_place') }}</th><th>{{ __('ui.home.col_server') }}</th><th class="hide-sm">{{ __('ui.home.col_spark') }}</th><th class="r">{{ __('ui.home.col_players') }}</th><th class="r hide-sm">{{ __('ui.home.col_votes') }}</th><th></th></tr></thead>
            @foreach ($data['ranking'] as $key => $group)
              <tbody data-group="{{ $key }}" @unless ($loop->first) hidden @endunless>
                @foreach ($group['rows'] as $i => $row)
                  <tr style="--i:{{ $i }}">
                    <td><span class="medal {{ $i < 3 ? 'm'.($i + 1) : '' }}">{{ $i + 1 }}</span></td>
                    <td><a class="sname" href="{{ $row['url'] }}">@if ($row['logo'])<img class="slogo" src="{{ $row['logo'] }}" alt="" width="36" height="36" loading="lazy">@endif<span>{{ $row['name'] }}<small><span class="gtag g-{{ $row['theme'] }}">{{ $row['game'] }}</span> {{ $row['details'] }}</small></span></a></td>
                    <td class="hide-sm">{!! $spark($row['spark']) !!}</td>
                    <td class="r num"><span class="status {{ $row['online'] ? '' : 'off' }}">{{ $row['online'] ? $fmt($row['players']) : mb_strtolower(__('ui.server.offline')) }}</span></td>
                    <td class="r num hide-sm" data-server-votes="{{ $row['id'] }}">{{ $fmt($row['votes']) }}</td>
                    <td class="r"><a class="btn btn-ghost btn-sm" href="{{ $row['vote_url'] }}">{{ __('ui.server.vote') }}</a></td>
                  </tr>
                @endforeach
              </tbody>
            @endforeach
          </table>
        </div>
      </div>
    </section>
  @endif

  @if (HomeContent::shows('soon') && $data['soon'])
    <!-- Скоро відкриття -->
    <section class="block alt" id="soon">
      <div class="wrap">
        <div class="head"><div><span class="label">{{ __('ui.home.soon_label') }}</span><h2>{{ __('ui.home.soon_title') }} <span class="grad">{{ __('ui.home.soon_accent') }}</span></h2></div><p>{{ __('ui.home.soon_lead') }}</p></div>
        <div class="soon" id="soon-grid">
          @foreach ($data['soon'] as $s)
            <article class="soon-card g-{{ $s['theme'] }}" style="--c:var(--gc)">
              @if ($s['image'])<div class="soon-cover" style="background-image:url('{{ $s['image'] }}')" aria-hidden="true"></div>@endif
              <div class="gname">@if ($s['logo'])<img class="slogo sm" src="{{ $s['logo'] }}" alt="" width="24" height="24" loading="lazy">@endif{{ $s['game'] }}</div><h3 class="gtitle">{{ $s['name'] }}</h3><p>{{ $s['details'] ?: __('ui.home.opens', ['date' => $s['opens_label']]) }}</p>
              <div class="count num" role="timer" data-opens="{{ $s['opens_at'] }}">
                <div><b data-u="d">0</b><span>{{ __('ui.home.unit_d') }}</span></div><div><b data-u="h">00</b><span>{{ __('ui.home.unit_h') }}</span></div><div><b data-u="m">00</b><span>{{ __('ui.home.unit_m') }}</span></div><div><b data-u="s">00</b><span>{{ __('ui.home.unit_s') }}</span></div>
              </div>
              <a class="btn btn-ghost btn-sm" href="{{ $s['url'] }}">{{ __('ui.home.details') }}</a></article>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  @if (HomeContent::shows('owners'))
    <!-- Власникам -->
    <section class="block" id="owners">
      <div class="wrap">
        <div class="owners">
          <div>
            <span class="label">{{ __('ui.home.owners_label') }}</span>
            <h2>{{ HomeContent::text('owners_title') }}</h2>
            <p>{{ HomeContent::text('owners_lead') }}</p>
            <ol class="steps">
              @foreach (__('ui.home.steps') as $n => [$title, $text])
                <li><span class="n">{{ sprintf('%02d', $n + 1) }}</span><div><b>{{ $title }}</b><p>{{ $text }}</p></div></li>
              @endforeach
            </ol>
            <a class="btn btn-hot" href="{{ route('cabinet.servers.create') }}">{{ __('ui.home.add_free') }}</a>
          </div>
          @if ($sp = $data['spotlight'])
            @php $peak = max(1, max($sp['days'])); @endphp
            <div class="stat-card">
              <span class="label live tag">{{ __('ui.home.spotlight_tag') }}</span>
              <h3><a href="{{ $sp['url'] }}">{{ __('ui.home.spotlight_title', ['server' => $sp['name']]) }}</a></h3>
              <p class="finding">{{ __('ui.home.spotlight_finding') }}</p>
              <div class="bars" id="bars" aria-hidden="true">
                @foreach ($sp['days'] as $i => $v)
                  <span class="{{ $i >= 23 ? 'hi' : '' }}" style="height:{{ max(2, round($v / $peak * 100)) }}%;--i:{{ $i }}"></span>
                @endforeach
              </div>
              <div class="axis"><span>{{ $sp['from'] }}</span><span>{{ __('ui.home.last_week') }}</span><span>{{ $sp['to'] }}</span></div>
              <div class="stat-row">
                <div><small>{{ __('ui.home.place') }}</small><b class="grad">№ 1</b></div>
                <div><small>{{ __('ui.home.votes') }}</small><b class="num">{{ $fmt($sp['votes']) }}</b></div>
                <div><small>{{ __('ui.home.uptime') }}</small><b class="num">{{ number_format($sp['uptime'], 1, ',', '') }}%</b></div>
              </div>
            </div>
          @endif
        </div>
        <div class="features" id="features">
          @foreach (__('ui.home.features') as $n => [$title, $text])
            <article class="feature"><span class="ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$n] ?? '' !!}</svg></span><h3>{{ $title }}</h3><p>{{ $text }}</p></article>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  @if (HomeContent::shows('plans'))
    <!-- Тарифи -->
    <section class="block alt" id="plans">
      <div class="wrap">
        <div class="head"><div><span class="label">{{ __('ui.home.plans_label') }}</span><h2>{{ __('ui.home.plans_title') }}</h2></div><p>{{ __('ui.home.plans_lead') }}</p></div>
        <div class="plans" id="plans-grid">
          @foreach ($plans as [[$name, $for, $items], $amount, $per, $pop])
            <article class="plan {{ $pop ? 'pop' : '' }}">
              @if ($pop)<span class="badge">{{ __('ui.home.plan_popular') }}</span>@endif
              <h3>{{ $name }}</h3><div class="for">{{ $for }}</div><div class="price">{{ $amount }}<small> {{ $per }}</small></div>
              <ul>@foreach ($items as $item)<li>{!! $check !!}{{ str_replace(':n', $data['plans']['vip_slots'], $item) }}</li>@endforeach</ul>
              <a class="btn {{ $pop ? 'btn-hot' : 'btn-ghost' }}" href="{{ route('cabinet.servers.create') }}">{{ $loop->first ? __('ui.nav.add_server') : ($pop ? __('ui.home.plan_choose').' '.$name : __('ui.home.plan_choose')) }}</a>
            </article>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  @if (HomeContent::shows('cta'))
    <div class="wrap" style="margin-top:96px">
      <div class="cta">
        @if ($ctaImage = HomePageData::url(config('graj.home.cta_image')))<span class="cta-img" style="background-image:url('{{ $ctaImage }}')" aria-hidden="true"></span>@endif
        <div><h2>{{ HomeContent::text('cta_title') }}</h2><p>{{ HomeContent::text('cta_text') }}</p></div>
        <a class="btn btn-hot" href="{{ route('cabinet.servers.create') }}">{{ __('ui.nav.add_server') }}</a>
      </div>
    </div>
  @endif

  <script type="application/json" id="home-data">@json($client)</script>
</x-layouts.home>
