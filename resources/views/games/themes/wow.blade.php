@php
    use App\Support\GameUrl;
    use App\Support\HomePageData;
    use App\Support\WowFactions;

    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
    $cover = HomePageData::url($game->cover);
    // Посилання з усіма поточними фільтрами, де змінено лише передане
    $url = fn (array $changes) => GameUrl::to($game, [...$active, 'sort' => $sort !== 'votes' ? $sort : null, 'q' => $search ?: null, ...$changes]);
    $versionField = $filters->firstWhere('key', 'expansion');
    $chipFields = $filters->reject(fn ($f) => $f->key === 'expansion');
    $perks = ['balanced' => __('ui.wow.balanced'), 'notempty' => __('ui.wow.notempty')];
    $title = trim($game->hero_title ?: $game->name);
    // «World of Warcraft» → дрібний рядок «World of» і великий «Warcraft»
    [$small, $big] = preg_match('/^(.+\s(?:of|the))\s+(\S.*)$/iu', $title, $m) ? [$m[1], $m[2]] : ['', $title];
    $champ = $fame->first();
    $client = [
        'game' => $game->slug,
        'seasonEnd' => $stats['season_end'],
        'feed' => ['url' => route('home.live'), 'after' => (int) \App\Models\Vote::where('created_at', '<', now()->subMinutes(10))->max('id'), 'enabled' => config('graj.home.live_feed') && $game->showsBlock('live')],
        'locale' => app()->getLocale(),
        't' => ['votes' => __('ui.server.votes'), 'untilStart' => __('ui.game.until_start'), 'copied' => __('ui.wow.copied'), 'voted' => __('ui.wow.toast_vote'), 'anon' => __('ui.wow.toast_anon')],
    ];
@endphp

<x-layouts.game :game="$game" :assets="['resources/css/game-wow.css', 'resources/js/game-wow.js']" fonts="family=Marcellus&family=PT+Serif:ital,wght@0,400;0,700;1,400&family=JetBrains+Mono:wght@500">
  <x-slot:background>
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <symbol id="curl" viewBox="0 0 34 34"><path d="M2 32 V10 C2 5 5 2 10 2 H32" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M6 32 V13 C6 9 9 6 13 6 H32" fill="none" stroke="currentColor" stroke-width=".8" opacity=".7"/><path d="M10 10 c6 0 9 3 9 7 c0 3-2 5-5 5 c-2 0-3.5-1.5-3.5-3.5 c0-1.6 1.2-2.6 2.6-2.6" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="2" cy="32" r="1.6" fill="currentColor"/><circle cx="32" cy="2" r="1.6" fill="currentColor"/></symbol>
      <symbol id="crest-alli" viewBox="0 0 54 54"><path d="M27 3 l20 8 v18 c0 12-8 20-20 24 C15 49 7 41 7 29 V11Z" fill="#1f4fa8" stroke="#e5c06a" stroke-width="2.5"/><path d="M27 16 l3.4 7 7.6.9-5.6 5.1 1.6 7.5L27 32.7l-7 3.8 1.6-7.5-5.6-5.1 7.6-.9z" fill="#e5c06a"/></symbol>
      <symbol id="crest-horde" viewBox="0 0 54 54"><circle cx="27" cy="27" r="23" fill="#2a0d0b" stroke="#c7b08a" stroke-width="2.5"/><path d="M15 15 L39 39 M39 15 L15 39" stroke="#c7b08a" stroke-width="4" stroke-linecap="round"/><path d="M12 12 c-4 4-4 10 1 13 l5-9Z M42 12 c4 4 4 10-1 13 l-5-9Z" fill="#c7b08a"/></symbol>
      <symbol id="crest-neutral" viewBox="0 0 54 54"><circle cx="27" cy="27" r="23" fill="#0b3a4f" stroke="#c9b48a" stroke-width="2.5"/><path d="M27 10 l4 12 12 0-10 8 4 12-10-8-10 8 4-12-10-8 12 0z" fill="#e9dcc0"/></symbol>
    </svg>
  </x-slot:background>
  <x-slot:headerCta><a class="btn sm" href="{{ route('cabinet.servers.create') }}">{{ __('ui.nav.add_server') }}</a></x-slot:headerCta>

  <!-- Банер: захід сонця над сніжними горами; ліворуч Альянс, праворуч Орда. Клік по стороні — обрати фракцію -->
  <section class="whero bleed" id="whero" aria-labelledby="game-h">
    <div class="sky" aria-hidden="true"></div>
    @if ($cover)<div class="hero-img" style="background-image:url('{{ $cover }}')" aria-hidden="true"></div>@endif
    <div class="clouds" aria-hidden="true"></div>
    <svg class="land" viewBox="0 0 1440 560" preserveAspectRatio="none" aria-hidden="true">
      <defs>
        <linearGradient id="mtn1" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#8f86c9"/><stop offset="1" stop-color="#4b4f9a"/></linearGradient>
        <linearGradient id="mtn2" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5b5fae"/><stop offset="1" stop-color="#2c3577"/></linearGradient>
        <linearGradient id="lake" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#e8a7a0" stop-opacity=".8"/><stop offset="1" stop-color="#3a4f9c" stop-opacity=".9"/></linearGradient>
      </defs>
      <path fill="url(#mtn1)" d="M0 330 L120 220 L190 260 L300 120 L380 200 L470 150 L560 230 L660 90 L760 200 L860 140 L960 220 L1060 110 L1170 210 L1270 150 L1440 250 V560 H0Z"/>
      <path fill="#f4ecff" opacity=".75" d="M300 120 L270 156 L292 150 L306 166 L322 146 L338 158 Z M660 90 L628 130 L650 124 L664 142 L684 120 L702 132 Z M1060 110 L1030 146 L1052 140 L1066 156 L1084 136 L1100 146 Z M860 140 L838 166 L856 162 L872 172 Z"/>
      <path fill="url(#mtn2)" d="M0 400 L140 300 L260 360 L380 280 L520 370 L640 320 L760 390 L900 300 L1040 380 L1180 310 L1320 370 L1440 330 V560 H0Z"/>
      <path fill="url(#lake)" d="M380 430 C 560 410, 900 410, 1080 432 L1080 450 C 900 440, 560 440, 380 452 Z"/>
      <g fill="#1d2450">
        <path d="M0 470 C 200 430, 380 450, 560 440 S 900 450, 1080 440 S 1300 430, 1440 450 V560 H0Z"/>
        <path d="M240 440 V300 l8 -40 l8 40 V440 Z M228 440 V340 l6 -20 l6 20 V440Z M264 440 V350 l6 -24 l6 24 V440Z"/>
        <path d="M1180 446 V360 h70 v86Z M1170 362 l45 -60 l45 60Z M1176 312 l-20 -18 l30 8Z M1254 312 l20 -18 l-30 8Z"/>
      </g>
      <circle class="beacon" cx="248" cy="262" r="5" fill="#bfe3ff"/>
      <rect class="torch" x="1208" y="400" width="10" height="16" fill="#ff8a3d"/>
    </svg>
    <div class="haze" aria-hidden="true"></div>

    @foreach (['alli' => $war['alliance'], 'horde' => $war['horde']] as $side => $count)
      <button class="side {{ $side }}" type="button" aria-pressed="false" data-f="{{ $side }}" aria-label="{{ __("ui.wow.pick_{$side}") }}">
        @if ($side === 'alli')
          <svg class="banner" viewBox="0 0 120 220" aria-hidden="true">
            <rect x="0" y="0" width="120" height="8" rx="3" fill="#c9a24a"/><circle cx="4" cy="4" r="6" fill="#e5c06a"/><circle cx="116" cy="4" r="6" fill="#e5c06a"/>
            <path d="M8 8 H112 V190 L60 214 L8 190 Z" fill="#1f4fa8"/><path d="M8 8 H112 V190 L60 214 L8 190 Z" fill="none" stroke="#e5c06a" stroke-width="4"/>
            <path d="M18 18 H102 V184 L60 204 L18 184 Z" fill="none" stroke="#e5c06a" stroke-width="1.2" opacity=".6"/>
            <path d="M60 56 l26 10 v26 c0 18-11 32-26 38 c-15-6-26-20-26-38 V66 Z" fill="#e5c06a"/>
            <path d="M60 64 l19 7 v21 c0 13-8 24-19 29 c-11-5-19-16-19-29 V71 Z" fill="#163c86"/>
            <path d="M60 74 l4.5 9.5 10.5 1.2-7.8 7 2.2 10.3L60 96.8l-9.4 5.2 2.2-10.3-7.8-7 10.5-1.2z" fill="#e5c06a"/>
            <path d="M30 140 c10 6 20 8 30 8 s20-2 30-8" fill="none" stroke="#e5c06a" stroke-width="3" stroke-linecap="round"/>
            <path d="M34 150 l8-4 M44 154 l6-6 M76 154 l-6-6 M86 150 l-8-4" stroke="#e5c06a" stroke-width="2.4" stroke-linecap="round"/>
          </svg>
        @else
          <svg class="banner" viewBox="0 0 120 220" aria-hidden="true">
            <rect x="0" y="0" width="120" height="8" rx="2" fill="#3a2a22"/>
            <path d="M8 8 H112 V170 L92 186 L80 214 L60 196 L40 214 L28 186 L8 170 Z" fill="#8f1b16"/>
            <path d="M8 8 H112 V170 L92 186 L80 214 L60 196 L40 214 L28 186 L8 170 Z" fill="none" stroke="#2a0d0b" stroke-width="5"/>
            <circle cx="60" cy="92" r="30" fill="#2a0d0b"/><circle cx="60" cy="92" r="24" fill="none" stroke="#c7b08a" stroke-width="2.5"/>
            <path d="M36 70 L84 118 M84 70 L36 118" stroke="#c7b08a" stroke-width="5" stroke-linecap="round"/>
            <path d="M30 64 c-6 6-6 16 2 20 l8-14Z M90 64 c6 6 6 16-2 20 l-8-14Z" fill="#c7b08a"/>
            <path d="M60 52 l5 10h-10Z M60 132 l5-10h-10Z M20 92 l10-5v10Z M100 92 l-10-5v10Z" fill="#c7b08a"/>
            <path d="M24 150 h72 M30 160 h60" stroke="#2a0d0b" stroke-width="4"/>
          </svg>
        @endif
        <span class="fname">{{ __("ui.wow.{$side}") }}</span>
        @if ($war['realms'])
          <span class="fsub">{{ __('ui.wow.on_realms') }}</span>
          <span class="fnum num" data-count="{{ $count }}">0</span>
        @endif
        <span class="fpick">{{ __("ui.wow.pick_{$side}") }}</span>
      </button>
    @endforeach
    <span class="vs" aria-hidden="true">VS</span>

    <div class="center">
      <span class="eyebrow">{{ $game->eyebrow ?: __('ui.wow.eyebrow') }}</span>
      <h1 id="game-h">@if ($small !== '')<small>{{ $small }}</small>@endif{{ $big }}</h1>
      <p class="lead">{{ $game->description ?: __('ui.wow.lead') }}</p>
      <div class="ctas"><a class="btn" href="#realms">{{ __('ui.wow.find') }}</a><a class="btn steel" href="{{ route('cabinet.servers.create') }}">{{ __('ui.wow.add') }}</a></div>
    </div>
  </section>

  <!-- Смуга відліку до фіналу сезону -->
  @if ($game->showsBlock('season'))
    <div class="band bleed">
      <div class="wrap">
        <div class="msg"><svg aria-hidden="true"><use href="#crest-neutral"/></svg><span>{{ $game->pageText('wow.season') }}</span></div>
        <div class="clock" id="clock" data-end="{{ $stats['season_end'] }}" aria-label="{{ __('ui.home.season_left') }}">
          @foreach (['d', 'h', 'm', 's'] as $u)
            @if (! $loop->first)<i aria-hidden="true">:</i>@endif
            <div><b class="num" data-u="{{ $u }}">00</b><small>{{ __("ui.wow.u_{$u}") }}</small></div>
          @endforeach
        </div>
      </div>
    </div>
  @endif
  <x-ad-banner placement="game" :game="$game" gap="20px auto 0" />

  <!-- Пергамент: війна фракцій і сервер місяця -->
  <section class="parch bleed" aria-labelledby="war-h">
    <div class="wrap grid2">
      <div data-reveal>
        <span class="eyebrow">{{ __('ui.wow.war_eyebrow') }}</span>
        <h2 class="big" id="war-h">{{ $game->pageText('wow.war') }}</h2>
        @if ($war['realms'])
          <p class="lead">{{ trans_choice('ui.wow.war_lead', $war['realms'], ['n' => $war['realms']]) }}</p>
          <div class="war">
            <div class="rope" style="--split:{{ $war['alliance_pct'] }}%"><span class="a">{{ __('ui.wow.alli') }}</span><span class="h">{{ __('ui.wow.horde') }}</span></div>
            <div class="legend"><span>{{ __('ui.wow.alli') }} · {{ $fmt($war['alliance']) }} ({{ number_format($war['alliance_pct'], 1, ',', '') }}%)</span><span>{{ __('ui.wow.horde') }} · {{ $fmt($war['horde']) }} ({{ number_format(100 - $war['alliance_pct'], 1, ',', '') }}%)</span></div>
            @if (count($war['rows']) > 1)
              <div class="rows">
                @foreach ($war['rows'] as $row)
                  <div class="row"><span>{{ $row['label'] }}</span><span class="t"><i style="width:{{ $row['pct'] }}%"></i><b></b></span><span class="num">{{ round($row['pct']) }} / {{ round(100 - $row['pct']) }}</span></div>
                @endforeach
              </div>
            @endif
          </div>
        @else
          <p class="lead">{{ __('ui.wow.war_none') }}</p>
          <p class="note"><a href="{{ route('developers') }}#online">{{ __('ui.wow.war_how') }}</a></p>
        @endif
      </div>
      <div class="frame" data-reveal>
        <svg class="curl tl" aria-hidden="true"><use href="#curl"/></svg><svg class="curl tr" aria-hidden="true"><use href="#curl"/></svg><svg class="curl bl" aria-hidden="true"><use href="#curl"/></svg><svg class="curl br" aria-hidden="true"><use href="#curl"/></svg>
        <div class="champ">
          <svg class="ice" viewBox="0 0 600 220" preserveAspectRatio="none" aria-hidden="true">
            <path fill="#2b4f8f" opacity=".6" d="M0 220 V120 L60 80 L110 110 L180 40 L240 100 L300 60 L360 120 L430 30 L500 100 L560 70 L600 100 V220Z"/>
            <path fill="#d9ecff" opacity=".35" d="M180 40 L166 62 L184 58 L196 70Z M430 30 L414 56 L434 52 L446 64Z"/>
            <path fill="#0e1838" d="M0 220 V170 L120 150 L240 175 L380 150 L500 170 L600 155 V220Z"/>
            <path fill="#0e1838" d="M400 160 V70 l10 -40 l10 40 V160Z M380 160 V100 l8 -24 l8 24 V160Z M424 160 V96 l8 -22 l8 22 V160Z"/>
            <path fill="#8fd3ff" d="M408 90 h4 v10 h-4Z" opacity=".9"/>
          </svg>
          @if ($champ)
            <span class="eyebrow">{{ __('ui.wow.champ', ['month' => $champ->month->translatedFormat('F')]) }}</span>
            <h3>@if ($champ->server)<a href="{{ route('servers.show', $champ->server) }}">{{ $champ->server_name }}</a>@else{{ $champ->server_name }}@endif</h3>
            <div class="stats"><span><b class="num">{{ $fmt($champ->votes) }}</b>{{ trans_choice('ui.server.votes', $champ->votes) }}</span></div>
          @else
            <span class="eyebrow">{{ __('ui.wow.champ_none_eyebrow') }}</span>
            <h3>{{ __('ui.wow.champ_none') }}</h3>
            <p>{{ __('ui.wow.champ_none_hint') }}</p>
          @endif
        </div>
      </div>
    </div>
    <svg class="ruins" viewBox="0 0 1440 110" preserveAspectRatio="none" aria-hidden="true"><path fill="currentColor" d="M0 110 V70 L40 64 L60 72 L90 60 L120 66 L150 40 L156 66 L200 62 L230 70 L260 52 L270 30 L278 58 L320 64 L380 60 L420 68 L460 56 L470 20 L476 10 L482 24 L490 58 L540 64 L600 58 L640 66 L700 54 L720 34 L726 56 L780 62 L840 56 L860 30 L866 6 L874 30 L880 58 L940 64 L1000 56 L1040 64 L1080 50 L1092 18 L1104 52 L1150 62 L1200 56 L1240 64 L1290 52 L1300 36 L1308 56 L1360 62 L1400 58 L1440 64 V110Z"/></svg>
  </section>

  <!-- Реалми: вкладки версій, фільтри, список -->
  <section class="realms-sec bleed" id="realms" aria-labelledby="realms-h">
    <div class="wrap">
      <div class="intro" data-reveal>
        <span class="eyebrow">{{ __('ui.wow.realms_eyebrow') }}</span>
        <h2 class="big" id="realms-h">{{ $game->pageText('wow.realms') }}</h2>
        <p class="lead">{{ __('ui.wow.realms_lead') }}</p>
      </div>

      @if ($versionField)
        <nav class="tabs" aria-label="{{ $versionField->label }}" data-swap="f-expansion">
          <a href="{{ $url(['expansion' => null]) }}" aria-current="{{ isset($active['expansion']) ? 'false' : 'true' }}" data-filter-link>{{ __('ui.wow.all_versions') }}<small>{{ $fmt($stats['servers']) }} {{ trans_choice('ui.game.stat_servers', $stats['servers']) }}</small></a>
          @foreach ($versionField->options as $option)
            @php $n = $counts[$versionField->id][$option['value']] ?? 0; $on = ($active['expansion'] ?? null) === $option['value']; @endphp
            @if ($n || $on)
              @php [$name, $build] = array_pad(explode(' ', $option['label'], 2), 2, ''); @endphp
              <a href="{{ $url(['expansion' => $on ? null : $option['value']]) }}" aria-current="{{ $on ? 'true' : 'false' }}" data-filter-link>{{ $name }}<small>{{ $build !== '' ? $build : $n }}</small></a>
            @endif
          @endforeach
        </nav>
      @endif

      <div class="filters" data-swap="f-chips">
        @foreach ($chipFields as $field)
          @php $shown = collect($field->options)->filter(fn ($o) => ($counts[$field->id][$o['value']] ?? 0) || ($active[$field->key] ?? null) === $o['value']); @endphp
          @if ($shown->isNotEmpty())
            <div class="fgroup"><span>{{ $field->label }}</span>
              @foreach ($shown as $option)
                @php $on = ($active[$field->key] ?? null) === $option['value']; @endphp
                <a href="{{ $url([$field->key => $on ? null : $option['value']]) }}" aria-current="{{ $on ? 'true' : 'false' }}" @if ($field->key === 'rates') style="--c:{{ WowFactions::rateColor($option['label']) }}" @endif data-filter-link>{{ $option['label'] }}<small class="num">{{ $counts[$field->id][$option['value']] ?? 0 }}</small></a>
              @endforeach
            </div>
          @endif
        @endforeach
        <div class="fgroup checks">
          @foreach ($perks as $perk => $label)
            <a class="check" href="{{ $url([$perk => isset($active[$perk]) ? null : '1']) }}" aria-current="{{ isset($active[$perk]) ? 'true' : 'false' }}" data-filter-link><span class="box" aria-hidden="true"></span>{{ $label }}</a>
          @endforeach
        </div>
        <form class="search" method="get" action="{{ GameUrl::to($game, array_intersect_key($active, GameUrl::pathFields($game)->pluck('key')->flip()->all())) }}" role="search" id="search-form">
          <label class="sr" for="q">{{ __('ui.wow.search_label') }}</label>
          <input id="q" name="q" type="search" maxlength="64" value="{{ $search }}" placeholder="{{ __('ui.wow.search_ph') }}">
          @foreach (array_diff_key($active, GameUrl::pathFields($game)->pluck('key')->flip()->all()) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
          @if ($sort !== 'votes')<input type="hidden" name="sort" value="{{ $sort }}">@endif
        </form>
      </div>

      <div class="list-head">
        <span class="total" data-swap="total">{{ __('ui.wow.found') }} <b class="num">{{ $fmt($servers->total()) }}</b></span>
        <div class="sorts" role="group" aria-label="{{ __('ui.game.sort') }}">
          @foreach (['votes', 'online', 'new', 'uptime'] as $s)
            <a href="{{ $url(['sort' => $s !== 'votes' ? $s : null]) }}" aria-current="{{ $sort === $s ? 'true' : 'false' }}" data-filter-link>{{ __("ui.wow.sort_{$s}") }}</a>
          @endforeach
        </div>
      </div>
      <div id="results" aria-live="polite">
        @include('games.themes.wow-list')
      </div>
    </div>
  </section>

  @if ($game->showsBlock('soon') && $comingSoon->isNotEmpty())
    <section class="soon bleed" aria-labelledby="soon-h">
      <div class="wrap">
        <div class="intro" data-reveal><span class="eyebrow">{{ __('ui.wow.soon_eyebrow') }}</span><h2 class="big" id="soon-h">{{ $game->pageText('wow.soon') }}</h2></div>
        <div class="soon-grid">
          @foreach ($comingSoon as $i => $server)
            <article class="frame" data-reveal>
              <svg class="curl tl" aria-hidden="true"><use href="#curl"/></svg><svg class="curl tr" aria-hidden="true"><use href="#curl"/></svg><svg class="curl bl" aria-hidden="true"><use href="#curl"/></svg><svg class="curl br" aria-hidden="true"><use href="#curl"/></svg>
              <div class="soon-card" style="--art:{{ ['#3c6bb0, #172a55', '#b98a3c, #3b2a14', '#3f9a5a, #12301c'][$i % 3] }}">
                <span class="eyebrow">{{ $server->fieldLabels() }}</span>
                <h3><a href="{{ route('servers.show', $server) }}">{{ $server->name }}</a></h3>
                <div class="when">
                  <div class="cd" data-opens="{{ $server->opens_at->toIso8601String() }}">
                    @foreach (['d', 'h', 'm', 's'] as $u)<span class="tile" data-u="{{ $u }}"><span class="val">00</span><small>{{ __("ui.wow.u_{$u}") }}</small></span>@endforeach
                  </div>
                  <a class="btn sm steel" href="{{ route('servers.show', $server) }}">{{ __('ui.wow.details') }}</a>
                </div>
              </div>
            </article>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  @if ($game->showsBlock('fame'))
    <section class="fame-sec bleed" aria-labelledby="fame-h">
      <div class="wrap">
        <div class="intro" data-reveal><span class="eyebrow">{{ __('ui.wow.fame_eyebrow') }}</span><h2 class="big" id="fame-h">{{ $game->pageText('wow.fame') }}</h2></div>
        @if ($fame->isNotEmpty())
          <div class="fame" data-reveal>
            @foreach ($fame as $winner)
              <div><svg class="crest" aria-hidden="true"><use href="#crest-neutral"/></svg>
                <small>{{ \Illuminate\Support\Str::ucfirst($winner->month->translatedFormat('F Y')) }}</small>
                <b>@if ($winner->server)<a href="{{ route('servers.show', $winner->server) }}">{{ $winner->server_name }}</a>@else{{ $winner->server_name }}@endif</b>
                <span>{{ $fmt($winner->votes) }} {{ trans_choice('ui.server.votes', $winner->votes) }}</span></div>
            @endforeach
          </div>
        @else
          <p class="lead center-text">{{ __('ui.wow.fame_empty') }}</p>
        @endif
      </div>
    </section>
  @endif

  <script type="application/json" id="game-data">@json($client)</script>
</x-layouts.game>
