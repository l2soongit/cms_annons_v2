@php
    use App\Support\CsMaps;
    use App\Support\HomePageData;

    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
    // «Counter-Strike» → «Counter-» і «Strike»: друге слово виділене, як у демо
    $words = preg_split('/(?<=-)|\s+/u', trim($game->hero_title ?: $game->name), -1, PREG_SPLIT_NO_EMPTY);
    $cover = HomePageData::url($game->cover);
    // Посилання з усіма поточними фільтрами, де змінено лише передане
    $url = fn (array $changes) => route('games.show', array_filter([
        'game' => $game->slug, ...$active, 'sort' => $sort !== 'votes' ? $sort : null, 'q' => $search ?: null, ...$changes,
    ], fn ($v) => $v !== null && $v !== ''));
    $switch = $filters->first();
    $chipFields = $filters->slice(1);
    $engines = ['CS2' => 'Source 2', 'CS 1.6' => 'GoldSrc'];
    $perkLabels = __('ui.cs.perks');
    $units = __('ui.game.units');
    $client = [
        'game' => $game->slug,
        'seasonEnd' => $stats['season_end'],
        'feed' => ['url' => route('home.live'), 'after' => (int) \App\Models\Vote::where('created_at', '<', now()->subMinutes(10))->max('id'), 'enabled' => (bool) config('graj.home.live_feed')],
        'locale' => app()->getLocale(),
        't' => ['votes' => __('ui.server.votes'), 'untilStart' => __('ui.game.until_start'), 'copied' => __('ui.cs.copied'), 'anon' => __('ui.cs.anon')],
    ];
@endphp

<x-layouts.game :game="$game" :assets="['resources/css/game-cs.css', 'resources/js/game-cs.js']" fonts="family=Exo+2:ital,wght@0,600;0,800;1,800">
  <x-slot:background><div class="grid-bg" aria-hidden="true"></div><div class="killfeed" id="killfeed" aria-live="polite"></div></x-slot:background>
  <x-slot:headerCta><a class="btn btn-ct btn-sm" href="{{ route('cabinet.servers.create') }}">{{ __('ui.nav.add_server') }}</a></x-slot:headerCta>

  <!-- Банер: тактичний радар, приціл за мишею -->
  <section class="ghero" id="hero" aria-labelledby="game-h">
    @if ($cover)<div class="hero-img" style="background-image:url('{{ $cover }}')" aria-hidden="true"></div>@endif
    <span class="corner tl" aria-hidden="true"></span><span class="corner tr" aria-hidden="true"></span><span class="corner bl" aria-hidden="true"></span><span class="corner br" aria-hidden="true"></span>
    <span class="xhair" aria-hidden="true"></span>
    <div>
      @if ($game->eyebrow)<span class="eyebrow">{{ $game->eyebrow }}</span>@endif
      <h1 id="game-h">@foreach ($words as $i => $word)<span @class(['w', 'accent' => $loop->last && count($words) > 1]) style="--i:{{ $i }}">{{ $word }}</span>@endforeach</h1>
      @if ($game->description)<p class="tagline">{{ $game->description }}</p>@endif
      <div class="hudstats">
        <div><svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 3h4v7h7v4h-7v7h-4v-7H3v-4h7z"/></svg><span><b class="num" data-count="{{ $stats['servers'] }}">0</b>{{ trans_choice('ui.game.stat_servers', $stats['servers']) }}</span></div>
        <div><svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l8 3v6c0 5-3.5 9.5-8 11-4.5-1.5-8-6-8-11V5z"/></svg><span><b class="num" id="g-online" data-count="{{ $stats['online'] }}">0</b>{{ __('ui.home.kpi_online') }}</span></div>
        <div class="t"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 2v5M12 17v5M2 12h5M17 12h5"/></svg><span><b class="num" id="g-votes" data-count="{{ $stats['votes'] }}">0</b>{{ __('ui.home.kpi_votes') }}</span></div>
      </div>
      <div class="season"><span class="led" aria-hidden="true"></span><span><small>{{ __('ui.cs.season') }}</small><b class="num" id="season">&nbsp;</b></span></div>
    </div>
    <div class="radar" aria-hidden="true">
      <span class="sweep"></span>
      <span class="me"></span>
      <span class="bomb">C4</span>
      <div id="blips"></div>
      <span class="radar-label">{{ __('ui.cs.radar') }}</span>
    </div>
  </section>

  <!-- Версія, режим, пошук, сортування — посилання з GET-параметрами -->
  <section class="controls" aria-label="{{ __('ui.game.filters') }}" data-reveal>
    @if ($switch)
      <div class="ver-switch" role="group" aria-label="{{ $switch->label }}" data-swap="versions">
        <a href="{{ $url([$switch->key => null]) }}" aria-pressed="{{ isset($active[$switch->key]) ? 'false' : 'true' }}" data-filter-link>{{ __('ui.game.any') }}<small>{{ __('ui.cs.all_versions') }}</small></a>
        @foreach ($switch->options as $option)
          @php $on = ($active[$switch->key] ?? null) === $option['value']; @endphp
          <a href="{{ $url([$switch->key => $on ? null : $option['value']]) }}" aria-pressed="{{ $on ? 'true' : 'false' }}" data-v="{{ $option['label'] }}" data-filter-link>{{ str_replace('CS ', '', $option['label']) }}<small>{{ $engines[$option['label']] ?? '' }}</small></a>
        @endforeach
      </div>
    @endif
    <div class="modes" data-swap="modes">
      @foreach ($chipFields as $field)
        <a class="mode-chip" href="{{ $url([$field->key => null]) }}" aria-pressed="{{ isset($active[$field->key]) ? 'false' : 'true' }}" data-filter-link>{{ __('ui.game.any') }}</a>
        @foreach ($field->options as $option)
          @php $n = $counts[$field->id][$option['value']] ?? 0; $on = ($active[$field->key] ?? null) === $option['value']; @endphp
          @if ($n || $on)
            <a class="mode-chip" href="{{ $url([$field->key => $on ? null : $option['value']]) }}" aria-pressed="{{ $on ? 'true' : 'false' }}" data-filter-link>{{ $option['label'] }}<small class="num">{{ $n }}</small></a>
          @endif
        @endforeach
      @endforeach
    </div>
    <div class="tools">
      <form class="search" method="get" action="{{ route('games.show', $game) }}" role="search" id="search-form">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <label class="sr" for="q">{{ __('ui.cs.search') }}</label>
        <input id="q" name="q" type="search" maxlength="64" value="{{ $search }}" placeholder="{{ __('ui.cs.search') }}">
        @foreach ($active as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
        @if ($sort !== 'votes')<input type="hidden" name="sort" value="{{ $sort }}">@endif
        <button>{{ __('ui.search.submit') }}</button>
      </form>
      <div class="sorts" role="group" aria-label="{{ __('ui.game.sort') }}">
        @foreach (['votes', 'online', 'ping'] as $s)
          <a href="{{ $url(['sort' => $s !== 'votes' ? $s : null]) }}" aria-pressed="{{ $sort === $s ? 'true' : 'false' }}" data-filter-link>{{ __("ui.cs.sort_{$s}") }}</a>
        @endforeach
      </div>
    </div>
  </section>

  <div class="main">
    <!-- Меню закупівлі: швидкі фільтри, вмикаються кліком або клавішами 1–6 -->
    <aside class="pool" aria-label="{{ __('ui.cs.buy') }}" data-reveal>
      <h3>{{ __('ui.cs.buy') }} <small>{{ __('ui.cs.buy_keys') }}</small></h3>
      <div class="buy" data-swap="buy">
        @foreach (\App\Http\Controllers\GameController::PERKS as $i => $perk)
          @php $on = isset($active[$perk]); $n = $perks[$perk] ?? 0; @endphp
          @if ($n || $on)
            <a class="buy-item" href="{{ $url([$perk => $on ? null : '1']) }}" aria-pressed="{{ $on ? 'true' : 'false' }}" data-key="{{ $i + 1 }}" data-filter-link><kbd>{{ $i + 1 }}</kbd><span>{{ $perkLabels[$perk] }}</span><span class="n num">{{ $n }}</span></a>
          @else
            <span class="buy-item" aria-disabled="true" style="opacity:.4;cursor:not-allowed"><kbd>{{ $i + 1 }}</kbd><span>{{ $perkLabels[$perk] }}</span><span class="n num">0</span></span>
          @endif
        @endforeach
      </div>
      <p class="buy-hint">{{ __('ui.cs.buy_hint') }}</p>
    </aside>

    <!-- Таблиця рахунку -->
    <section class="board" aria-label="{{ __('ui.cs.board') }}" data-reveal>
      <div class="board-head"><h3>{{ __('ui.cs.board') }}</h3><span class="live">{{ __('ui.cs.live') }}</span></div>
      <div class="cols" aria-hidden="true"><span class="c">{{ __('ui.cs.col_rank') }}</span><span>{{ __('ui.game.col_server') }}</span><span>{{ __('ui.cs.col_map') }}</span><span>{{ __('ui.home.col_players') }}</span><span class="r">{{ __('ui.cs.col_ping') }}</span><span class="r c-votes">{{ __('ui.game.col_votes') }}</span><span></span></div>
      <div id="results" aria-live="polite">
        @include('games.themes.cs-list')
      </div>
    </section>
  </div>

  <section class="band">
    <div class="panel" data-reveal>
      <h3>{{ __('ui.cs.soon') }}</h3>
      <div class="matches" id="soon">
        @forelse ($comingSoon as $s)
          @php $opens = $s->opens_at->copy()->startOfDay(); @endphp
          <div class="match">
            <a href="{{ route('servers.show', $s) }}" aria-label="{{ $s->name }}"></a>
            <div class="top"><b>{{ $s->name }}</b><span class="when">{{ $opens->translatedFormat('j M') }}</span></div><small>{{ $s->fieldLabels() }}</small>
            <div class="cd" data-opens="{{ $opens->toIso8601String() }}" role="timer">
              @foreach (['d', 'h', 'm', 's'] as $i => $u)
                <div class="unit"><div class="tile" data-u="{{ $u }}"><span class="val">00</span></div><span class="lbl">{{ $units[$i] }}</span></div>
              @endforeach
            </div>
          </div>
        @empty
          <p class="muted">{{ __('ui.game.soon_empty') }}</p>
        @endforelse
      </div>
    </div>
    <div class="panel" data-reveal>
      <h3>{{ __('ui.cs.mvp') }}</h3>
      @if ($fame->isNotEmpty())
        <ul class="mvp" id="mvp">
          @foreach ($fame->take(3) as $winner)
            <li><span class="star">MVP</span>
              <span>@if ($winner->server)<a href="{{ route('servers.show', $winner->server) }}"><b>{{ $winner->server_name }}</b></a>@else<b>{{ $winner->server_name }}</b>@endif<small>{{ \Illuminate\Support\Str::ucfirst($winner->month->translatedFormat('F')) }}</small></span>
              <span class="n num">{{ $fmt($winner->votes) }}</span></li>
          @endforeach
        </ul>
      @else
        <p class="muted">{{ __('ui.game.fame_empty') }}</p>
      @endif
    </div>
    <div class="panel" data-reveal>
      <h3>{{ __('ui.cs.popular_maps') }}</h3>
      @if ($maps)
        <div class="popmaps" id="popmaps">
          @foreach ($maps as $m)
            <div class="pm" style="--m:{{ CsMaps::color($m['map']) }}"><span class="mtag" aria-hidden="true">{{ CsMaps::code($m['map']) }}</span><div><div class="l">{{ CsMaps::name($m['map']) }}<span class="num">{{ $m['percent'] }}%</span></div><div class="track"><i data-w="{{ $m['width'] }}"></i></div></div></div>
          @endforeach
        </div>
      @else
        <p class="muted">{{ __('ui.cs.maps_empty') }}</p>
      @endif
    </div>
  </section>

  <script type="application/json" id="game-data">@json($client)</script>
</x-layouts.game>
