@php
    use App\Support\GameUrl;

    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
    // Посилання з усіма поточними фільтрами, де змінено лише передане
    $url = fn (array $changes) => GameUrl::to($game, [...$active, 'sort' => $sort !== 'votes' ? $sort : null, 'q' => $search ?: null, ...$changes]);
    $units = __('ui.game.units');
    $title = trim($game->hero_title ?: $game->name);
    // «MU Online» → дрібний рядок «MU» і великий «Online»
    [$small, $big] = preg_match('/^(\S+)\s+(\S.*)$/u', $title, $m) ? [$m[1], $m[2]] : ['', $title];
    $client = [
        'game' => $game->slug,
        'seasonEnd' => $stats['season_end'],
        'feed' => ['url' => route('home.live'), 'after' => (int) \App\Models\Vote::where('created_at', '<', now()->subMinutes(10))->max('id'), 'enabled' => config('graj.home.live_feed') && $game->showsBlock('live')],
        'locale' => app()->getLocale(),
        't' => ['toastVote' => __('ui.home.toast_vote'), 'toastFrom' => __('ui.home.toast_from'), 'toastAnon' => __('ui.home.toast_anon'), 'votes' => __('ui.server.votes'), 'untilStart' => __('ui.game.until_start')],
    ];
@endphp

<x-layouts.game :game="$game" :assets="['resources/css/game-mu.css', 'resources/js/game-mu.js']" fonts="family=Cinzel:wght@700">
  <!-- Банер-картка як у Lineage 2 (design/game/mu-b.html «Золотий лицар»): офіційний арт Webzen, цифри, таймер і кнопка -->
  <section class="ghero" style="--art:url('{{ asset('images/games/mu/knight.webp') }}')" aria-labelledby="game-h">
    <div class="info">
      @if ($game->eyebrow)<span class="eyebrow">{{ $game->eyebrow }}</span>@endif
      <h1 id="game-h">@if ($small !== '')<small>{{ $small }}</small>@endif{{ $big }}</h1>
      @if ($game->description)<p class="tagline">{{ $game->description }}</p>@endif
      <dl class="gstats">
        <div><dd class="num" data-count="{{ $stats['servers'] }}">0</dd><dt>{{ trans_choice('ui.game.stat_servers', $stats['servers']) }}</dt></div>
        <div><dd class="num gold" id="g-online" data-count="{{ $stats['online'] }}">0</dd><dt>{{ __('ui.home.kpi_online') }}</dt></div>
        <div><dd class="num red" id="g-votes" data-count="{{ $stats['votes'] }}">0</dd><dt>{{ __('ui.home.kpi_votes') }}</dt></div>
      </dl>
    </div>
    <div class="hero-cta">
      @if ($game->showsBlock('season'))
        <div class="season">{{ $game->pageText('home.season_left') }}
          <div class="clock" id="clock" data-end="{{ $stats['season_end'] }}">
            @foreach (['d', 'h', 'm', 's'] as $i => $u)
              <div><b class="num" data-u="{{ $u }}">00</b><small>{{ $units[$i] }}</small></div>
            @endforeach
          </div>
        </div>
      @endif
      <a class="btn" href="{{ route('cabinet.servers.create', ['game' => $game->slug]) }}">{{ $game->pageText('game.add_own', ['game' => $game->shortName()]) }}</a>
    </div>
  </section>
  <x-ad-banner placement="game" :game="$game" gap="20px auto 0" />

  <!-- Дашборд як у Lineage 2: ліворуч фільтри й список, праворуч бічна колонка -->
  <div class="layout">
    <div>
      <section class="filters" aria-label="{{ __('ui.game.filters') }}" data-reveal>
        @foreach ($filters as $field)
          <div class="frow"><span>{{ $field->label }}</span><div class="chips">
            <a class="chip" href="{{ $url([$field->key => null]) }}" aria-current="{{ isset($active[$field->key]) ? 'false' : 'true' }}" data-filter-link>{{ __('ui.game.any') }}</a>
            @foreach ($field->options as $option)
              @php $n = $counts[$field->id][$option['value']] ?? 0; $on = ($active[$field->key] ?? null) === $option['value']; @endphp
              @if ($n || $on)
                <a class="chip" href="{{ $url([$field->key => $on ? null : $option['value']]) }}" aria-current="{{ $on ? 'true' : 'false' }}" data-filter-link>{{ $option['label'] }}<small class="num">{{ $n }}</small></a>
              @endif
            @endforeach
          </div></div>
        @endforeach
        <div class="ftools">
          <form class="fsearch" method="get" action="{{ GameUrl::to($game, array_intersect_key($active, GameUrl::pathFields($game)->pluck('key')->flip()->all())) }}" role="search" id="search-form">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <label class="sr" for="q">{{ __('ui.game.search_label') }}</label>
            <input id="q" name="q" type="search" maxlength="64" value="{{ $search }}" placeholder="{{ __('ui.game.search_placeholder') }}">
            @foreach (array_diff_key($active, GameUrl::pathFields($game)->pluck('key')->flip()->all()) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            @if ($sort !== 'votes')<input type="hidden" name="sort" value="{{ $sort }}">@endif
            <button>{{ __('ui.search.submit') }}</button>
          </form>
          <div class="sorts" role="group" aria-label="{{ __('ui.game.sort') }}">
            @foreach (['votes', 'online', 'new', 'opening'] as $s)
              <a href="{{ $url(['sort' => $s !== 'votes' ? $s : null]) }}" aria-current="{{ $sort === $s ? 'true' : 'false' }}" data-filter-link>{{ __("ui.game.sort_short_{$s}") }}</a>
            @endforeach
          </div>
        </div>
      </section>

      <div id="results" aria-live="polite">
        @include('games.themes.mu-list')
      </div>
    </div>

    <aside class="side">
      {{-- Окреме платне місце; сайдбар серверів сюди не потрапляє --}}
      <x-ad-banner placement="game_side" :game="$game" gap="0 auto 20px" promo />

      @if ($game->showsBlock('soon'))
        <section class="panel" data-reveal>
          <h2>{{ $game->pageText('home.coming_soon') }}</h2>
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
                  <a class="remind" href="{{ route('servers.show', $s) }}">{{ __('ui.home.details') }}</a>
                </div>
              </div>
            @empty
              <p class="muted">{{ __('ui.game.soon_empty') }}</p>
            @endforelse
          </div>
        </section>
      @endif

      @if ($game->showsBlock('fame'))
        <section class="panel fame champ" style="--champ:url('{{ asset('images/games/mu/lord.webp') }}')" data-reveal>
          <h2>{{ $game->pageText('game.fame') }}</h2>
          @if ($fame->isNotEmpty())
            <ul id="fame">
              @foreach ($fame as $i => $winner)
                <li><svg class="m" width="18" height="18" viewBox="0 0 24 24" fill="{{ $i === 0 ? '#f2c14e' : '#71717a' }}" aria-hidden="true"><path d="M3 7l4.5 4L12 4l4.5 7L21 7l-2 12H5z"/></svg>
                  <span>@if ($winner->server)<a href="{{ route('servers.show', $winner->server) }}"><b>{{ $winner->server_name }}</b></a>@else<b>{{ $winner->server_name }}</b>@endif<span class="mon">{{ \Illuminate\Support\Str::ucfirst($winner->month->translatedFormat('F')) }}</span></span>
                  <span class="num" style="color:var(--muted)">{{ $fmt($winner->votes) }}</span></li>
              @endforeach
            </ul>
          @else
            <p class="muted champ-note">{{ __('ui.game.fame_empty') }}</p>
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
