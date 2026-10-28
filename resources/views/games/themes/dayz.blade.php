@php
    use App\Support\DayzMaps;
    use App\Support\GameUrl;
    use App\Support\HomePageData;

    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
    // Назва гри по літерах — кожна мерехтить, як вивіска в тумані
    $letters = mb_str_split(trim($game->hero_title ?: $game->name));
    $cover = HomePageData::url($game->cover);
    // Посилання з усіма поточними фільтрами, де змінено лише передане
    $url = fn (array $changes) => GameUrl::to($game, [...$active, 'sort' => $sort !== 'votes' ? $sort : null, 'q' => $search ?: null, ...$changes]);
    // Мапа — топографічні карти; «так / ні» й короткі списки — перемикачі; решта — чипи
    $mapField = $filters->firstWhere('key', 'map') ?? $filters->firstWhere('type', 'select');
    $segFields = $filters->reject(fn ($f) => $f->is($mapField))->filter(fn ($f) => $f->type === 'boolean' || count($f->options) <= 3);
    $chipFields = $filters->reject(fn ($f) => $f->is($mapField) || $segFields->contains($f));
    $segLabel = fn ($field, $option) => $field->type === 'boolean' && $field->key === 'mods'
        ? __($option['value'] === '1' ? 'ui.dayz.mods_yes' : 'ui.dayz.mods_no')
        : $option['label'];
    $weather = (array) $game->pageText('dayz.weather');
    $mapCount = $mapField ? count($mapField->options) : 0;
    $client = [
        'game' => $game->slug,
        'seasonEnd' => $stats['season_end'],
        'feed' => ['url' => route('home.live'), 'after' => (int) \App\Models\Vote::where('created_at', '<', now()->subMinutes(10))->max('id'), 'enabled' => config('graj.home.live_feed') && $game->showsBlock('live')],
        'locale' => app()->getLocale(),
        'weather' => $weather,
        't' => ['votes' => __('ui.server.votes'), 'untilStart' => __('ui.game.until_start'), 'copied' => __('ui.dayz.copied'), 'anon' => __('ui.dayz.anon'), 'voted' => __('ui.dayz.voted'), 'channel' => __('ui.dayz.channel')],
    ];
@endphp

<x-layouts.game :game="$game" :assets="['resources/css/game-dayz.css', 'resources/js/game-dayz.js']" fonts="family=Stalinist+One&family=Oswald:wght@500;600;700&family=JetBrains+Mono:wght@500;700">
  <x-slot:background><div class="comms" id="comms" aria-live="polite"></div></x-slot:background>
  <x-slot:headerCta><a class="btn sm go" href="{{ route('cabinet.servers.create') }}">{{ __('ui.nav.add_server') }}</a></x-slot:headerCta>

  <!-- Банер: ніч у лісі, туман, дощ, ліхтарик і сигнальна ракета -->
  <section class="zhero" id="zhero" aria-labelledby="game-h">
    <div class="sky" id="sky" aria-hidden="true"></div>
    @if ($cover)<div class="hero-img" style="background-image:url('{{ $cover }}')" aria-hidden="true"></div>@endif
    <span class="moon" aria-hidden="true"></span>
    <svg class="mast" viewBox="0 0 60 150" aria-hidden="true"><g stroke="#3d4a34" stroke-width="2" fill="none"><path d="M30 6 14 150M30 6l16 144M18 120h24M21 92h18M24 64h12M26 38h8M14 150l28-30M46 150 18 120M18 120l21-28M42 120 21 92M21 92l15-28M39 92 24 64"/></g><circle class="lamp" cx="30" cy="6" r="3.5" fill="#ff5a3c"/><circle class="lamp" cx="30" cy="6" r="9" fill="#ff5a3c" opacity=".25"/></svg>
    <span class="flare" id="flare" aria-hidden="true"></span>
    <svg class="forest far" id="f-far" viewBox="0 0 1200 300" preserveAspectRatio="none" aria-hidden="true"></svg>
    <svg class="forest mid" id="f-mid" viewBox="0 0 1200 260" preserveAspectRatio="none" aria-hidden="true"></svg>
    <div class="fog f3" aria-hidden="true"></div>
    <div class="fog f1" aria-hidden="true"></div>
    <canvas id="rain" aria-hidden="true"></canvas>
    <svg class="forest near" id="f-near" viewBox="0 0 1200 200" preserveAspectRatio="none" aria-hidden="true"></svg>
    <div class="fog f2" aria-hidden="true"></div>
    <div class="dark" id="dark" aria-hidden="true"></div>
    <div class="zcontent">
      <span class="tag">{{ $game->eyebrow ?: ($mapField ? collect($mapField->options)->pluck('label')->implode(' · ') : $game->name) }}</span>
      <h1 id="game-h" aria-label="{{ $game->hero_title ?: $game->name }}">@foreach ($letters as $letter)<span aria-hidden="true">{{ $letter }}</span>@endforeach</h1>
      @if ($game->description)<p>{{ $game->description }}</p>@endif
    </div>
    <div class="coords" aria-hidden="true">
      <div><b>N</b> 51°08′ <b>E</b> 28°42′</div>
      <div id="weather">{{ $weather[0] ?? '' }}</div>
    </div>
  </section>
  <x-ad-banner placement="game" :game="$game" gap="20px auto 0" />

  <!-- HUD: іконки стану, як у грі -->
  <div class="hud" aria-label="{{ __('ui.game.stats') }}">
    <div><span class="ic" data-arrows="▲"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 20 12 4l9 16z"/><path d="M12 20v-6"/></svg></span><div><b class="num" data-count="{{ $stats['servers'] }}">0</b><span>{{ trans_choice('ui.game.stat_servers', $stats['servers']) }}</span></div></div>
    <div><span class="ic" data-arrows="▲▲"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.5-7 10-7 10z"/></svg></span><div><b class="num" id="g-online" data-count="{{ $stats['online'] }}">0</b><span>{{ __('ui.dayz.online') }}</span></div></div>
    <div><span class="ic" data-arrows="▲"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="7" y="7" width="10" height="14" rx="2"/><path d="M10 7V3M10 12h4M10 16h4"/></svg></span><div><b class="num" id="g-votes" data-count="{{ $stats['votes'] }}">0</b><span>{{ __('ui.dayz.votes') }}</span></div></div>
    @if ($game->showsBlock('season'))
      <div class="warn"><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 14V5a2 2 0 1 1 4 0v9a4 4 0 1 1-4 0z"/><path d="M12 11v5"/></svg></span><div><b class="num" id="season">&nbsp;</b><span>{{ $game->pageText('dayz.season') }}</span></div></div>
    @endif
  </div>

  <!-- Скоро відкриття: радіоперехоплення -->
  @if ($game->showsBlock('soon'))
    <section style="margin-top:36px" aria-labelledby="soon-h" data-reveal>
      <h2 class="sec" id="soon-h">{{ $game->pageText('dayz.soon') }}</h2>
      @if ($comingSoon->isNotEmpty())
        <div class="radio-row" id="soon">
          @foreach ($comingSoon as $i => $s)
            @php $opens = $s->opens_at->copy()->startOfDay(); $freq = number_format(87.5 + ($s->id * 7.3) % 20, 1, '.', ''); @endphp
            <article class="radio">
              <div class="top"><span>FM <span class="freq">{{ $freq }}</span> {{ __('ui.dayz.mhz') }}</span><span class="wave" aria-hidden="true">@for ($k = 0; $k < 9; $k++)<i style="--i:{{ $k + $i * 3 }}"></i>@endfor</span></div>
              <h3><a href="{{ route('servers.show', $s) }}">{{ $s->name }}</a></h3>
              <div class="meta">{{ $s->fieldLabels() }} · {{ $opens->translatedFormat('j F') }}</div>
              <div class="cd" data-opens="{{ $opens->toIso8601String() }}" role="timer" aria-label="{{ __('ui.dayz.until') }}">
                @foreach (['d', 'h', 'm', 's'] as $k => $u)
                  <span class="unit"><span class="tile" data-u="{{ $u }}"><span class="val">00</span></span><small>{{ __('ui.game.units')[$k] }}</small></span>
                @endforeach
              </div>
              <a class="btn sm" href="{{ route('servers.show', $s) }}">{{ __('ui.dayz.details') }}</a>
            </article>
          @endforeach
        </div>
      @else
        <p class="muted">{{ __('ui.dayz.soon_empty') }}</p>
      @endif
    </section>
  @endif

  <div class="zlayout">
    <!-- Фільтри як польове спорядження — посилання, працюють і без скрипта -->
    <aside class="kit" aria-label="{{ $game->pageText('dayz.filters') }}" data-reveal>
      @if ($mapField)
        <h2>{{ $mapField->label }}</h2>
        <div class="maps" data-swap="f-{{ $mapField->key }}">
          @foreach ($mapField->options as $i => $option)
            @php $n = $counts[$mapField->id][$option['value']] ?? 0; $on = ($active[$mapField->key] ?? null) === $option['value']; @endphp
            <a class="map" href="{{ $url([$mapField->key => $on ? null : $option['value']]) }}" style="--paper:{{ DayzMaps::paper($option['value'], $i) }}" aria-current="{{ $on ? 'true' : 'false' }}"
               aria-label="{{ $option['label'] }}, {{ trans_choice('ui.game.stat_servers', $n) }}: {{ $n }}" data-filter-link>{!! DayzMaps::contours($option['value']) !!}<b>{{ $option['label'] }}</b><span class="cnt num">{{ $n }}</span></a>
          @endforeach
        </div>
      @endif

      @foreach ($chipFields as $field)
        <h2>{{ $field->label }}</h2>
        <div class="chips" data-swap="f-{{ $field->key }}">
          @foreach ($field->options as $option)
            @php $n = $counts[$field->id][$option['value']] ?? 0; $on = ($active[$field->key] ?? null) === $option['value']; @endphp
            @if ($n || $on)
              <a class="chip" href="{{ $url([$field->key => $on ? null : $option['value']]) }}" aria-current="{{ $on ? 'true' : 'false' }}" data-filter-link>{{ $option['label'] }}<small class="num">{{ $n }}</small></a>
            @endif
          @endforeach
        </div>
      @endforeach

      @foreach ($segFields as $field)
        <h2>{{ $field->label }}</h2>
        <div class="seg" style="--n:{{ count($field->options) + 1 }}" data-swap="f-{{ $field->key }}">
          <a href="{{ $url([$field->key => null]) }}" aria-current="{{ isset($active[$field->key]) ? 'false' : 'true' }}" data-filter-link>{{ __('ui.dayz.any') }}</a>
          @foreach ($field->options as $option)
            @php $on = ($active[$field->key] ?? null) === $option['value']; @endphp
            <a href="{{ $url([$field->key => $on ? null : $option['value']]) }}" aria-current="{{ $on ? 'true' : 'false' }}" data-filter-link>{{ $segLabel($field, $option) }}</a>
          @endforeach
        </div>
      @endforeach

      <div data-swap="free">
        <a class="check" href="{{ $url(['free' => isset($active['free']) ? null : '1']) }}" aria-current="{{ isset($active['free']) ? 'true' : 'false' }}" data-filter-link><span class="box" aria-hidden="true"></span>{{ $game->pageText('dayz.free') }}</a>
      </div>

      <h2>{{ __('ui.dayz.search') }}</h2>
      <form class="search" method="get" action="{{ GameUrl::to($game, array_intersect_key($active, GameUrl::pathFields($game)->pluck('key')->flip()->all())) }}" role="search" id="search-form">
        <label class="sr" for="q">{{ __('ui.dayz.search_label') }}</label>
        <input id="q" name="q" type="search" maxlength="64" value="{{ $search }}" placeholder="{{ __('ui.dayz.search_ph') }}">
        @foreach (array_diff_key($active, GameUrl::pathFields($game)->pluck('key')->flip()->all()) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
        @if ($sort !== 'votes')<input type="hidden" name="sort" value="{{ $sort }}">@endif
      </form>
    </aside>

    <!-- Журнал серверів -->
    <section aria-label="{{ __('ui.game.col_server') }}" data-reveal>
      <div class="log-head">
        <span class="total" data-swap="total">{{ __('ui.dayz.total') }} <b class="num" id="count">{{ $fmt($servers->total()) }}</b></span>
        <div class="sorts" role="group" aria-label="{{ __('ui.game.sort') }}">
          @foreach (['votes', 'online', 'ping', 'uptime'] as $s)
            <a href="{{ $url(['sort' => $s !== 'votes' ? $s : null]) }}" aria-current="{{ $sort === $s ? 'true' : 'false' }}" data-filter-link>{{ __("ui.dayz.sort_{$s}") }}</a>
          @endforeach
        </div>
      </div>
      <div id="results" aria-live="polite">
        @include('games.themes.dayz-list')
      </div>
    </section>
  </div>

  @if ($game->showsBlock('fame') || $game->showsBlock('popular'))
    <section class="band" data-reveal>
      @if ($game->showsBlock('fame'))
        <div class="panel"><h2 class="sec">{{ $game->pageText('dayz.fame') }}</h2>
          @if ($fame->isNotEmpty())
            <div class="tags">
              @foreach ($fame->take(3) as $i => $winner)
                <div class="dogtag" style="--r:{{ [-3, 2, -1][$i] ?? 0 }}deg">
                  <small>{{ \Illuminate\Support\Str::ucfirst($winner->month->translatedFormat('F Y')) }}</small>
                  <b>@if ($winner->server)<a href="{{ route('servers.show', $winner->server) }}">{{ $winner->server_name }}</a>@else{{ $winner->server_name }}@endif</b>
                  <span>{{ $fmt($winner->votes) }} {{ trans_choice('ui.server.votes', $winner->votes) }}</span>
                </div>
              @endforeach
            </div>
          @else
            <p class="muted">{{ __('ui.dayz.fame_empty') }}</p>
          @endif
        </div>
      @endif
      @if ($game->showsBlock('popular'))
        <div class="panel"><h2 class="sec">{{ $game->pageText('dayz.shares') }}</h2>
          @if ($shares)
            <div class="shares">
              @foreach ($shares as $i => $share)
                @php $value = $popularField ? (collect($popularField->options)->firstWhere('label', $share['label'])['value'] ?? $share['label']) : $share['label']; @endphp
                <div class="share"><div class="row">{{ $share['label'] }}<span class="num">{{ $share['percent'] }}%</span></div><div class="track"><i data-w="{{ $share['width'] }}" style="--mc:{{ DayzMaps::color($value, $i) }}"></i></div></div>
              @endforeach
            </div>
          @else
            <p class="muted">{{ __('ui.dayz.shares_empty') }}</p>
          @endif
        </div>
      @endif
    </section>
  @endif

  <script type="application/json" id="game-data">@json($client)</script>
</x-layouts.game>
