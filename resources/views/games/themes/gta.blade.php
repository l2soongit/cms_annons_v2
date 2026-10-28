@php
    use App\Support\HomePageData;

    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
    $words = preg_split('/\s+/u', trim($game->hero_title ?: $game->name));
    $cover = HomePageData::url($game->cover);
    // Посилання фільтра: значення ставиться або знімається, сторінка скидається
    $filterUrl = fn (string $key, ?string $value) => route('games.show', array_filter([
        'game' => $game->slug, ...$active, $key => $value, 'sort' => $sort !== 'votes' ? $sort : null, 'q' => $search ?: null,
    ]));
    $sortUrl = fn (string $s) => route('games.show', array_filter(['game' => $game->slug, ...$active, 'sort' => $s !== 'votes' ? $s : null, 'q' => $search ?: null]));
    $units = __('ui.game.units');
    $client = [
        'game' => $game->slug,
        'seasonEnd' => $stats['season_end'],
        'feed' => ['url' => route('home.live'), 'after' => (int) \App\Models\Vote::where('created_at', '<', now()->subMinutes(10))->max('id'), 'enabled' => config('graj.home.live_feed') && $game->showsBlock('live')],
        'locale' => app()->getLocale(),
        't' => ['toastVote' => __('ui.home.toast_vote'), 'toastFrom' => __('ui.home.toast_from'), 'toastAnon' => __('ui.home.toast_anon'), 'votes' => __('ui.server.votes'), 'untilStart' => __('ui.game.until_start')],
    ];
@endphp

<x-layouts.game :game="$game" :assets="['resources/css/game-gta.css', 'resources/js/game-gta.js']" fonts="family=Russo+One">
  <x-slot:background><div class="aurora" aria-hidden="true"><i></i><i></i></div></x-slot:background>
  <x-slot:headerCta><a class="btn btn-gold btn-sm" href="{{ route('cabinet.servers.create') }}">{{ __('ui.nav.add_server') }}</a></x-slot:headerCta>

  <!-- Банер-«світ» гри: нічне місто на заході сонця -->
  <section class="ghero" aria-labelledby="game-h">
    <div @class(['scene', 'has-img' => $cover]) aria-hidden="true">
      @if ($cover)<div class="hero-img" style="background-image:url('{{ $cover }}')"></div>@endif
      <span class="stars"></span>
      <span class="sun"></span>
      <svg class="city" viewBox="0 0 600 200" preserveAspectRatio="none">
        <path fill="#14031f" d="M0 200 V120 h30 V80 h26 v40 h18 V50 h34 v70 h14 V96 h22 v-30 h20 v54 h18 V30 h12 V14 h8 v16 h12 v90 h20 V60 h30 v60 h14 V94 h26 v-38 h22 v64 h20 V36 h34 v84 h14 V76 h30 v44 h20 V52 h26 v68 h24 V90 h30 v110 Z"/>
        <g id="windows" fill="#ffb3d9"></g>
        <rect x="203" y="10" width="4" height="6" fill="#ff3b3b"><animate attributeName="opacity" values="1;.1;1" dur="1.6s" repeatCount="indefinite"/></rect>
      </svg>
      <svg class="palm" viewBox="0 0 100 200"><path d="M52 200 C50 150 46 100 54 60" stroke="#14031f" stroke-width="7" fill="none"/><g fill="#14031f"><path d="M54 60 C30 40 8 48 0 64 C20 52 38 54 54 62Z"/><path d="M54 60 C76 38 96 44 100 60 C82 50 68 52 54 62Z"/><path d="M54 60 C40 30 44 10 58 0 C52 22 56 40 56 60Z"/><path d="M54 60 C70 70 80 92 78 110 C70 90 62 76 52 64Z"/><path d="M54 60 C34 70 24 92 26 110 C34 90 44 76 56 64Z"/></g></svg>
      <span class="road"></span>
      <span class="car c1"></span><span class="car c2"></span>
    </div>
    <div>
      @if ($game->eyebrow)<span class="eyebrow">{{ $game->eyebrow }}</span>@endif
      <h1 id="game-h">@foreach ($words as $i => $word)<span @class(['w', 'gold' => $loop->last && count($words) > 1]) style="--i:{{ $i }}">{{ $word }}</span>@if (! $loop->last) @endif @endforeach</h1>
      @if ($game->description)<p class="tagline">{{ $game->description }}</p>@endif
      <dl class="gstats">
        <div><dd class="num" data-count="{{ $stats['servers'] }}">0</dd><dt>{{ trans_choice('ui.game.stat_servers', $stats['servers']) }}</dt></div>
        <div><dd class="num gold" id="g-online" data-count="{{ $stats['online'] }}">0</dd><dt>{{ __('ui.home.kpi_online') }}</dt></div>
        <div><dd class="num" id="g-votes" data-count="{{ $stats['votes'] }}">0</dd><dt>{{ __('ui.home.kpi_votes') }}</dt></div>
      </dl>
    </div>
    <div class="hero-cta">
      @if ($game->showsBlock('season'))<div class="season">{{ $game->pageText('home.season_left') }}<b class="num" id="season">&nbsp;</b></div>@endif
      <a class="btn btn-gold" href="{{ route('cabinet.servers.create', ['game' => $game->slug]) }}">{{ $game->pageText('game.add_own', ['game' => $game->shortName()]) }}</a>
    </div>
  </section>
  <x-ad-banner placement="game" :game="$game" gap="20px auto 0" />

  <!-- GTA: без бічної колонки. Вгорі — білборди «Скоро відкриття», далі сітка карток на всю ширину -->
  @if ($comingSoon->isNotEmpty() && $game->showsBlock('soon'))
    <section class="billboards" aria-labelledby="soon-h" data-reveal>
      <h2 id="soon-h" class="sec-title">{!! str_replace(':accent', '<span class="lore">'.e($game->pageText('game.soon_accent')).'</span>', e($game->pageText('game.soon_title_gta'))) !!}</h2>
      <div class="bb-row" id="soon">
        @foreach ($comingSoon as $s)
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
              <a class="remind" href="{{ route('servers.show', $s) }}"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>{{ __('ui.home.details') }}</a>
            </div>
          </div>
        @endforeach
      </div>
    </section>
  @endif

  <div class="layout gta-layout">
    <div>
      <!-- Фільтри — звичайні посилання (?platform=fivem); скрипт оновлює список без перезавантаження -->
      <section class="filters" aria-label="{{ __('ui.game.filters') }}" data-reveal>
        @foreach ($filters as $field)
          <div class="frow"><span>{{ $field->label }}</span><div class="chips">
            <a class="chip" href="{{ $filterUrl($field->key, null) }}" aria-pressed="{{ isset($active[$field->key]) ? 'false' : 'true' }}" data-filter-link>{{ __('ui.game.any') }}</a>
            @foreach ($field->options as $option)
              @php $n = $counts[$field->id][$option['value']] ?? 0; $on = ($active[$field->key] ?? null) === $option['value']; @endphp
              @if ($n || $on)
                <a @class(['chip', 'tinted' => ! empty($option['color'])]) href="{{ $filterUrl($field->key, $on ? null : $option['value']) }}" aria-pressed="{{ $on ? 'true' : 'false' }}" data-v="{{ $option['label'] }}" @if (! empty($option['color'])) style="--p:{{ $option['color'] }}" @endif data-filter-link>{{ $option['label'] }}<small class="num">{{ $n }}</small></a>
              @endif
            @endforeach
          </div></div>
        @endforeach
        <div class="ftools">
          <form class="fsearch" method="get" action="{{ route('games.show', $game) }}" role="search" id="search-form">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <label class="sr" for="q">{{ __('ui.game.search_label') }}</label>
            <input id="q" name="q" type="search" maxlength="64" value="{{ $search }}" placeholder="{{ __('ui.game.search_placeholder') }}">
            @foreach ($active as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            @if ($sort !== 'votes')<input type="hidden" name="sort" value="{{ $sort }}">@endif
            <button>{{ __('ui.search.submit') }}</button>
          </form>
          <div class="sorts" role="group" aria-label="{{ __('ui.game.sort') }}">
            @foreach (['votes', 'online', 'new', 'opening'] as $s)
              <a href="{{ $sortUrl($s) }}" aria-pressed="{{ $sort === $s ? 'true' : 'false' }}" data-filter-link>{{ __("ui.game.sort_short_{$s}") }}</a>
            @endforeach
          </div>
        </div>
      </section>

      <div id="results" aria-live="polite">
        @include('games.themes.gta-list')
      </div>
    </div>
  </div>

  <!-- Широка смуга внизу: зал слави і популярність платформ -->
  <section class="band" data-reveal>
    @if ($game->showsBlock('fame'))
    <div class="panel fame">
      <h3><span>{{ $game->pageText('game.fame') }} <span class="lore gold" style="font-size:18px">{{ $game->shortName() }}</span></span></h3>
      @if ($fame->isNotEmpty())
        <ul id="fame">
          @foreach ($fame as $i => $winner)
            <li><svg class="m" width="18" height="18" viewBox="0 0 24 24" fill="{{ $i === 0 ? '#ff4fa3' : '#71717a' }}" aria-hidden="true"><path d="M3 7l4.5 4L12 4l4.5 7L21 7l-2 12H5z"/></svg>
              <span>@if ($winner->server)<a href="{{ route('servers.show', $winner->server) }}"><b>{{ $winner->server_name }}</b></a>@else<b>{{ $winner->server_name }}</b>@endif<span class="mon">{{ \Illuminate\Support\Str::ucfirst($winner->month->translatedFormat('F')) }}</span></span>
              <span class="num" style="color:var(--muted)">{{ $fmt($winner->votes) }}</span></li>
          @endforeach
        </ul>
      @else
        <p class="muted">{{ __('ui.game.fame_empty') }}</p>
      @endif
    </div>
    @endif
    @if ($shares && $game->showsBlock('popular'))
      <div class="panel">
        <h3>{{ __('ui.game.popular_by')[$popularField->key] ?? __('ui.game.popular', ['field' => mb_strtolower($popularField->label)]) }}</h3>
        <div class="chron-bars" id="chron-bars">
          @foreach ($shares as $share)
            <div class="cb"><div class="l"><b>{{ $share['label'] }}</b><span class="num">{{ $share['percent'] }}%</span></div><div class="track"><i data-w="{{ $share['width'] }}"></i></div></div>
          @endforeach
        </div>
      </div>
    @endif
  </section>

  <script type="application/json" id="game-data">@json($client)</script>
</x-layouts.game>
