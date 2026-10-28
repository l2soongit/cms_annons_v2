@php
    use App\Support\HomePageData;

    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
    $words = preg_split('/\s+/u', trim($game->hero_title ?: $game->name));
    $cover = HomePageData::url($game->cover);
    $addUrl = route('cabinet.servers.create', ['game' => $game->slug]);
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

<x-layouts.game :game="$game" :assets="['resources/css/game-l2.css', 'resources/js/game-l2.js']" fonts="family=Cormorant:wght@600;700">
  <x-slot:background><div class="aurora" aria-hidden="true"><i></i><i></i></div></x-slot:background>
  <x-slot:headerCta><a class="btn btn-gold btn-sm" href="{{ route('cabinet.servers.create') }}">{{ __('ui.nav.add_server') }}</a></x-slot:headerCta>

  <!-- Банер-«світ» гри -->
  <section class="ghero" aria-labelledby="game-h">
    <div @class(['scene', 'has-img' => $cover]) aria-hidden="true">
      @if ($cover)<div class="hero-img" style="background-image:url('{{ $cover }}')"></div>@endif
      <svg class="rune" viewBox="0 0 200 200" fill="none" stroke="#e8b04a" stroke-width="1.2"><circle cx="100" cy="100" r="96"/><circle cx="100" cy="100" r="84" stroke-dasharray="3 7"/><circle cx="100" cy="100" r="56"/>
        <path d="M100 4 L100 196 M4 100 L196 100 M32 32 L168 168 M168 32 L32 168"/><path d="M100 44 L148 128 L52 128 Z M100 156 L52 72 L148 72 Z"/></svg>
      <svg class="rune inner-r" viewBox="0 0 200 200" fill="none" stroke="#f5d28a" stroke-width="1.5"><circle cx="100" cy="100" r="90" stroke-dasharray="20 8 4 8"/><circle cx="100" cy="100" r="60"/><path d="M100 10 L100 40 M100 160 L100 190 M10 100 L40 100 M160 100 L190 100"/></svg>
      <svg class="castle" viewBox="0 0 500 160" preserveAspectRatio="none"><path fill="#0d0702" d="M0 160 V112 h22 v-14 h10 v14 h12 v-14 h10 v14 h20 V70 h12 v-12 h8 v12 h12 v-12 h8 v12 h12 V118 h50 V48 h14 V24 l15 -22 l15 22 V48 h14 V118 h50 V74 h12 v-12 h8 v12 h12 v-12 h8 v12 h12 v42 h20 v-14 h10 v14 h12 v-14 h10 v14 h22 v48 Z"/><rect x="243" y="84" width="14" height="24" rx="7" fill="#e8b04a" opacity=".85"/><rect x="146" y="88" width="6" height="12" rx="3" fill="#e8b04a" opacity=".6"/><rect x="348" y="90" width="6" height="12" rx="3" fill="#e8b04a" opacity=".6"/></svg>
      <div id="motes"></div>
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
      <a class="btn btn-gold" href="{{ $addUrl }}">{{ $game->pageText('game.add_own', ['game' => $game->shortName()]) }}</a>
    </div>
  </section>

  <div class="layout">
    <div>
      <!-- Фільтри — звичайні посилання (?chronicle=interlude); скрипт оновлює список без перезавантаження -->
      <section class="filters" aria-label="{{ __('ui.game.filters') }}" data-reveal>
        @foreach ($filters as $field)
          <div class="frow"><span>{{ $field->label }}</span><div class="chips">
            <a class="chip" href="{{ $filterUrl($field->key, null) }}" aria-pressed="{{ isset($active[$field->key]) ? 'false' : 'true' }}" data-filter-link>{{ __('ui.game.any') }}</a>
            @foreach ($field->options as $option)
              @php $n = $counts[$field->id][$option['value']] ?? 0; $on = ($active[$field->key] ?? null) === $option['value']; @endphp
              @if ($n || $on)
                <a class="chip" href="{{ $filterUrl($field->key, $on ? null : $option['value']) }}" aria-pressed="{{ $on ? 'true' : 'false' }}" data-filter-link>{{ $option['label'] }}<small class="num">{{ $n }}</small></a>
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
        @include('games.themes.l2-list')
      </div>
    </div>

    <aside class="side">
      @if ($game->showsBlock('soon'))
      <section class="panel" data-reveal>
        <h3>{{ $game->pageText('home.coming_soon') }}</h3>
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
                <a class="remind" href="{{ route('servers.show', $s) }}"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>{{ __('ui.home.details') }}</a>
              </div>
            </div>
          @empty
            <p class="muted">{{ __('ui.game.soon_empty') }}</p>
          @endforelse
        </div>
      </section>
      @endif

      @if ($game->showsBlock('fame'))
      <section class="panel fame" data-reveal>
        <h3><span>{{ $game->pageText('game.fame') }} <span class="lore gold" style="font-size:18px">{{ $game->shortName() }}</span></span></h3>
        @if ($fame->isNotEmpty())
          <ul id="fame">
            @foreach ($fame as $i => $winner)
              <li><svg class="m" width="18" height="18" viewBox="0 0 24 24" fill="{{ $i === 0 ? '#e8b04a' : '#71717a' }}" aria-hidden="true"><path d="M3 7l4.5 4L12 4l4.5 7L21 7l-2 12H5z"/></svg>
                <span>@if ($winner->server)<a href="{{ route('servers.show', $winner->server) }}"><b>{{ $winner->server_name }}</b></a>@else<b>{{ $winner->server_name }}</b>@endif<span class="mon">{{ \Illuminate\Support\Str::ucfirst($winner->month->translatedFormat('F')) }}</span></span>
                <span class="num" style="color:var(--muted)">{{ $fmt($winner->votes) }}</span></li>
            @endforeach
          </ul>
        @else
          <p class="muted">{{ __('ui.game.fame_empty') }}</p>
        @endif
      </section>
      @endif

      @if ($shares && $game->showsBlock('popular'))
        <section class="panel" data-reveal>
          <h3>{{ __('ui.game.popular_by')[$popularField->key] ?? __('ui.game.popular', ['field' => mb_strtolower($popularField->label)]) }}</h3>
          <div class="chron-bars" id="chron-bars">
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
