@php
    use App\Support\GameUrl;
    use App\Support\WowFactions;

    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
    // Посилання з усіма поточними фільтрами, де змінено лише передане
    $url = fn (array $changes) => GameUrl::to($game, [...$active, 'sort' => $sort !== 'votes' ? $sort : null, 'q' => $search ?: null, ...$changes]);
    $perks = ['balanced' => __('ui.wow.balanced'), 'notempty' => __('ui.wow.notempty')];
    $units = __('ui.game.units');
    $title = trim($game->hero_title ?: $game->name);
    // «World of Warcraft» → дрібний рядок «World of» і великий «Warcraft»
    [$small, $big] = preg_match('/^(.+\s(?:of|the))\s+(\S.*)$/iu', $title, $m) ? [$m[1], $m[2]] : ['', $title];
    $client = [
        'game' => $game->slug,
        'seasonEnd' => $stats['season_end'],
        'feed' => ['url' => route('home.live'), 'after' => (int) \App\Models\Vote::where('created_at', '<', now()->subMinutes(10))->max('id'), 'enabled' => config('graj.home.live_feed') && $game->showsBlock('live')],
        'locale' => app()->getLocale(),
        't' => ['votes' => __('ui.server.votes'), 'untilStart' => __('ui.game.until_start'), 'copied' => __('ui.wow.copied'), 'voted' => __('ui.wow.toast_vote'), 'anon' => __('ui.wow.toast_anon')],
    ];
@endphp

<x-layouts.game :game="$game" :assets="['resources/css/game-wow.css', 'resources/js/game-wow.js']" fonts="family=Cinzel:wght@700&family=JetBrains+Mono:wght@500">
  <!-- Банер-картка як у Lineage 2 (design/game/wow-b.html «Небо Азерота»): дракон над містом, цифри, таймер і кнопка; Альянс / Орда — у кутах -->
  <section class="ghero" id="whero" style="--art:url('{{ asset('images/games/wow/dragon.webp') }}')" aria-labelledby="game-h">
    <div class="factions">
      @foreach (['alli' => $war['alliance'], 'horde' => $war['horde']] as $side => $count)
        <button class="fpick {{ $side }}" type="button" aria-pressed="false" data-f="{{ $side }}" title="{{ __("ui.wow.pick_{$side}") }}">
          <i aria-hidden="true"></i><span>{{ __("ui.wow.{$side}") }}</span>@if ($war['realms'])<b class="num" data-count="{{ $count }}">0</b>@endif
        </button>
      @endforeach
    </div>
    <div class="info">
      <span class="eyebrow">{{ $game->eyebrow ?: __('ui.wow.eyebrow') }}</span>
      <h1 id="game-h">@if ($small !== '')<small>{{ $small }}</small>@endif{{ $big }}</h1>
      <p class="tagline">{{ $game->description ?: __('ui.wow.lead') }}</p>
      <dl class="gstats">
        <div><dd class="num" data-count="{{ $stats['servers'] }}">0</dd><dt>{{ trans_choice('ui.game.stat_servers', $stats['servers']) }}</dt></div>
        <div><dd class="num a" id="g-online" data-count="{{ $stats['online'] }}">0</dd><dt>{{ __('ui.home.kpi_online') }}</dt></div>
        <div><dd class="num h" id="g-votes" data-count="{{ $stats['votes'] }}">0</dd><dt>{{ __('ui.home.kpi_votes') }}</dt></div>
      </dl>
    </div>
    <div class="hero-cta">
      @if ($game->showsBlock('season'))
        <div class="season">{{ __('ui.home.season_left') }}
          <div class="clock" id="clock" data-end="{{ $stats['season_end'] }}" title="{{ $game->pageText('wow.season') }}">
            @foreach (['d', 'h', 'm', 's'] as $u)
              <div><b class="num" data-u="{{ $u }}">00</b><small>{{ __("ui.wow.u_{$u}") }}</small></div>
            @endforeach
          </div>
        </div>
      @endif
      <a class="btn" href="{{ route('cabinet.servers.create', ['game' => $game->slug]) }}">{{ __('ui.game.add_own', ['game' => $game->shortName()]) }}</a>
    </div>
  </section>
  <x-ad-banner placement="game" :game="$game" gap="20px auto 0" />

  <!-- Дашборд як у Lineage 2: ліворуч фільтри й реалми, праворуч бічна колонка -->
  <div class="layout">
    <div>
      <section class="filters" aria-label="{{ __('ui.game.filters') }}" data-reveal>
        @foreach ($filters as $field)
          <div class="frow"><span>{{ $field->label }}</span><div class="chips">
            <a class="chip" href="{{ $url([$field->key => null]) }}" aria-current="{{ isset($active[$field->key]) ? 'false' : 'true' }}" data-filter-link>{{ __('ui.game.any') }}</a>
            @foreach ($field->options as $option)
              @php $n = $counts[$field->id][$option['value']] ?? 0; $on = ($active[$field->key] ?? null) === $option['value']; @endphp
              @if ($n || $on)
                <a class="chip" href="{{ $url([$field->key => $on ? null : $option['value']]) }}" aria-current="{{ $on ? 'true' : 'false' }}" @if ($field->key === 'rates') style="--rc:{{ WowFactions::rateColor($option['label']) }}" @endif data-filter-link>{{ $option['label'] }}<small class="num">{{ $n }}</small></a>
              @endif
            @endforeach
          </div></div>
        @endforeach
        <div class="frow"><span>{{ __('ui.wow.factions') }}</span><div class="chips">
          @foreach ($perks as $perk => $label)
            <a class="chip" href="{{ $url([$perk => isset($active[$perk]) ? null : '1']) }}" aria-current="{{ isset($active[$perk]) ? 'true' : 'false' }}" data-filter-link>{{ $label }}</a>
          @endforeach
        </div></div>
        <div class="ftools">
          <form class="fsearch" method="get" action="{{ GameUrl::to($game, array_intersect_key($active, GameUrl::pathFields($game)->pluck('key')->flip()->all())) }}" role="search" id="search-form">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <label class="sr" for="q">{{ __('ui.wow.search_label') }}</label>
            <input id="q" name="q" type="search" maxlength="64" value="{{ $search }}" placeholder="{{ __('ui.wow.search_ph') }}">
            @foreach (array_diff_key($active, GameUrl::pathFields($game)->pluck('key')->flip()->all()) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            @if ($sort !== 'votes')<input type="hidden" name="sort" value="{{ $sort }}">@endif
            <button>{{ __('ui.search.submit') }}</button>
          </form>
          <div class="sorts" role="group" aria-label="{{ __('ui.game.sort') }}">
            @foreach (['votes', 'online', 'new', 'uptime'] as $s)
              <a href="{{ $url(['sort' => $s !== 'votes' ? $s : null]) }}" aria-current="{{ $sort === $s ? 'true' : 'false' }}" data-filter-link>{{ __("ui.wow.sort_{$s}") }}</a>
            @endforeach
          </div>
        </div>
      </section>

      <div id="results" aria-live="polite">
        @include('games.themes.wow-list')
      </div>
    </div>

    <aside class="side">
      {{-- Окреме платне місце; сайдбар серверів сюди не потрапляє --}}
      <x-ad-banner placement="game_side" :game="$game" gap="0 auto 20px" promo />

      @if ($game->showsBlock('soon'))
        <section class="panel" data-reveal>
          <h2>{{ $game->pageText('wow.soon') }}</h2>
          <div id="soon">
            @forelse ($comingSoon as $s)
              @php $opens = $s->opens_at->copy()->startOfDay(); @endphp
              <div class="soon-item">
                <a href="{{ route('servers.show', $s) }}" aria-label="{{ $s->name }}"></a>
                <div class="t"><b>{{ $s->name }}</b></div><small>{{ $s->fieldLabels() }}</small>
                <div class="cd" data-opens="{{ $opens->toIso8601String() }}" role="timer">
                  @foreach (['d', 'h', 'm', 's'] as $i => $u)
                    <div class="unit"><div class="tile" data-u="{{ $u }}"><span class="val">00</span></div><span class="lbl">{{ $units[$i] }}</span></div>
                  @endforeach
                </div>
                <div class="soon-meta">
                  <span>{!! __('ui.game.opens', ['date' => '<time datetime="'.$opens->toDateString().'">'.e($opens->translatedFormat('j F')).'</time>']) !!}</span>
                  <a class="remind" href="{{ route('servers.show', $s) }}">{{ __('ui.wow.details') }}</a>
                </div>
              </div>
            @empty
              <p class="muted">{{ __('ui.game.soon_empty') }}</p>
            @endforelse
          </div>
        </section>
      @endif

      <!-- Війна фракцій: з реалмів, що передають онлайн Альянсу й Орди -->
      <section class="panel war" data-reveal>
        <h2>{{ $game->pageText('wow.war') }}</h2>
        @if ($war['realms'])
          <div class="rope" style="--split:{{ $war['alliance_pct'] }}%">
            <span class="a">{{ number_format($war['alliance_pct'], 1, ',', '') }}%</span>
            <span class="h">{{ number_format(100 - $war['alliance_pct'], 1, ',', '') }}%</span>
          </div>
          <div class="lbl"><span class="a">{{ __('ui.wow.alli') }}</span><span class="h">{{ __('ui.wow.horde') }}</span></div>
          @if (count($war['rows']) > 1)
            <div class="rows">
              @foreach ($war['rows'] as $row)
                <div><b>{{ $row['label'] }}</b><i style="--p:{{ $row['pct'] }}%"></i><span class="num">{{ round($row['pct']) }}/{{ round(100 - $row['pct']) }}</span></div>
              @endforeach
            </div>
          @endif
        @else
          <p class="muted">{{ __('ui.wow.war_none') }}</p>
          <p class="note"><a href="{{ route('developers') }}#online">{{ __('ui.wow.war_how') }}</a></p>
        @endif
      </section>

      @if ($game->showsBlock('fame'))
        <section class="panel fame champ" style="--champ:url('{{ asset('images/games/wow/golden.webp') }}')" data-reveal>
          <h2>{{ $game->pageText('wow.fame') }}</h2>
          @if ($fame->isNotEmpty())
            <ul id="fame">
              @foreach ($fame as $i => $winner)
                <li><svg class="m" width="18" height="18" viewBox="0 0 24 24" fill="{{ $i === 0 ? '#ffb547' : '#71717a' }}" aria-hidden="true"><path d="M3 7l4.5 4L12 4l4.5 7L21 7l-2 12H5z"/></svg>
                  <span>@if ($winner->server)<a href="{{ route('servers.show', $winner->server) }}"><b>{{ $winner->server_name }}</b></a>@else<b>{{ $winner->server_name }}</b>@endif<span class="mon">{{ \Illuminate\Support\Str::ucfirst($winner->month->translatedFormat('F')) }}</span></span>
                  <span class="num" style="color:var(--muted)">{{ $fmt($winner->votes) }}</span></li>
              @endforeach
            </ul>
          @else
            <p class="champ-title">{{ __('ui.wow.champ_none') }}</p>
            <p class="muted">{{ __('ui.wow.champ_none_hint') }}</p>
          @endif
        </section>
      @endif

      @if ($shares && $game->showsBlock('popular'))
        <section class="panel" data-reveal>
          <h2>{{ __('ui.game.popular_by')[$popularField->key] ?? __('ui.game.popular', ['field' => mb_strtolower($popularField->label)]) }}</h2>
          <div class="chron-bars">
            @foreach ($shares as $share)
              <div class="cb"><div class="l"><b>{{ $share['label'] }}</b><span class="num">{{ $share['percent'] }}%</span></div><div class="track"><i data-w="{{ $share['width'] }}"></i></div></div>
            @endforeach
          </div>
        </section>
      @endif
    </aside>
  </div>

  <script type="application/json" id="game-data">@json($client)</script>
</x-layouts.game>
