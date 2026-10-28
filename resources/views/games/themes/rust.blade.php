@php
    use App\Support\GameUrl;
    use App\Support\HomePageData;
    use App\Support\RustLook;

    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
    $letters = mb_str_split(trim($game->hero_title ?: $game->name));
    $cover = HomePageData::url($game->cover);
    // Посилання з усіма поточними фільтрами, де змінено лише передане
    $url = fn (array $changes) => GameUrl::to($game, [...$active, 'sort' => $sort !== 'votes' ? $sort : null, 'q' => $search ?: null, ...$changes]);
    $wipeField = $filters->firstWhere('key', 'wipe');
    $groupField = $filters->firstWhere('key', 'group');
    $rateField = $filters->firstWhere('key', 'rate');
    $sizeField = $filters->firstWhere('key', 'map_size');
    $otherFields = $filters->reject(fn ($f) => in_array($f->key, ['wipe', 'group', 'rate', 'map_size'], true));
    $perks = ['fresh' => __('ui.rust.fresh'), 'free' => __('ui.rust.noq'), 'vanilla' => __('ui.rust.vanilla')];
    $client = [
        'game' => $game->slug,
        'seasonEnd' => $stats['season_end'],
        'forcedWipe' => $forcedWipe?->toIso8601String(),
        'feed' => ['url' => route('home.live'), 'after' => (int) \App\Models\Vote::where('created_at', '<', now()->subMinutes(10))->max('id'), 'enabled' => config('graj.home.live_feed') && $game->showsBlock('live')],
        'locale' => app()->getLocale(),
        'seed' => crc32($game->slug) % 100000,
        't' => ['votes' => __('ui.server.votes'), 'untilStart' => __('ui.game.until_start'), 'copied' => __('ui.rust.copied'), 'anon' => __('ui.rust.anon'), 'voted' => __('ui.rust.voted')],
    ];
@endphp

<x-layouts.game :game="$game" :assets="['resources/css/game-rust.css', 'resources/js/game-rust.js']" fonts="family=Rubik+Dirt&family=Roboto+Condensed:wght@400;500;700;800&family=JetBrains+Mono:wght@500;700">
  <x-slot:background><div class="chat" id="chat" aria-live="polite"></div></x-slot:background>
  <x-slot:headerCta><a class="btn sm go" href="{{ route('cabinet.servers.create') }}">{{ __('ui.nav.add_server') }}</a></x-slot:headerCta>

  <!-- Банер: вечірній острів, багаття з іскрами, мапа з сіткою; клік — підрив C4 -->
  <section class="rhero" id="rhero" aria-labelledby="game-h">
    <div class="sky" aria-hidden="true"></div>
    @if ($cover)<div class="hero-img" style="background-image:url('{{ $cover }}')" aria-hidden="true"></div>@endif
    <span class="sun" aria-hidden="true"></span>
    <div class="sea" aria-hidden="true"></div>
    <svg class="skyline" viewBox="0 0 1200 280" preserveAspectRatio="none" aria-hidden="true">
      <path d="M0 200 C 160 150, 260 175, 380 150 S 620 120, 760 160 S 1020 130, 1200 170 V280 H0Z" fill="#2a1d16"/>
      <g fill="none" stroke="#1a120d" stroke-width="3"><path d="M930 160 L960 40 L990 160M940 120h40M948 88h24M955 60h10M930 160 975 100M990 160 945 100"/></g>
      <circle class="lamp" cx="960" cy="38" r="3" fill="#ff5a3c"/>
      <g fill="#140e0a"><path d="M560 150h90v-46h-90z"/><path d="M550 106l55-26 55 26z"/><path d="M612 104h22v-36h-22z"/><path d="M606 70l17-10 17 10z"/><rect x="574" y="120" width="16" height="30" fill="#ffb05a" opacity=".55"/></g>
      <path d="M0 240 C 140 205, 300 230, 460 210 S 780 200, 900 230 S 1100 215, 1200 230 V280 H0Z" fill="#120c09"/>
    </svg>
    <div class="fire" aria-hidden="true"><i></i><i></i><i></i><b></b></div>
    <canvas id="embers" aria-hidden="true"></canvas>
    <div class="flash" id="flash" aria-hidden="true"></div>

    <div class="plate">
      <span class="rv l" aria-hidden="true"></span><span class="rv r" aria-hidden="true"></span>
      <span class="eyebrow">{{ $game->eyebrow ?: __('ui.rust.eyebrow') }}</span>
      <h1 id="game-h" aria-label="{{ $game->hero_title ?: $game->name }}">@foreach ($letters as $i => $letter)<span style="--i:{{ $i }}" aria-hidden="true">{{ $letter }}</span>@endforeach</h1>
      @if ($game->description)<p>{{ $game->description }}</p>@endif
      <div class="hint" aria-hidden="true">{{ __('ui.rust.hint') }}</div>
    </div>

    <div class="mapbox" aria-hidden="true">
      <canvas id="map" width="320" height="320"></canvas>
      <span class="grid" id="gridref">G7</span>
      <div class="meta"><span>{{ __('ui.rust.seed') }} <b class="num">{{ $client['seed'] }}</b></span><span>{{ $fmt($stats['servers']) }} {{ trans_choice('ui.game.stat_servers', $stats['servers']) }}</span></div>
    </div>
  </section>
  <x-ad-banner placement="game" :game="$game" gap="20px auto 0" />

  <!-- Смуги стану гравця -->
  <div class="vitals" aria-label="{{ __('ui.game.stats') }}">
    <div class="vital" style="--c:var(--hp)"><div class="lbl"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 3h4v7h7v4h-7v7h-4v-7H3v-4h7z"/></svg>{{ __('ui.rust.v_servers') }}</div><div class="bar"><i data-w="72"></i><b class="num" data-count="{{ $stats['servers'] }}">0</b></div></div>
    <div class="vital" style="--c:var(--water)"><div class="lbl"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2s7 8 7 13a7 7 0 0 1-14 0c0-5 7-13 7-13z"/></svg>{{ __('ui.rust.v_online') }}</div><div class="bar"><i data-w="64"></i><b class="num" id="g-online" data-count="{{ $stats['online'] }}">0</b></div></div>
    <div class="vital" style="--c:var(--food)"><div class="lbl"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 3h3v8a2 2 0 0 1-1 1.7V21H6v-8.3A2 2 0 0 1 5 11V3zm9 0c2 0 4 2 4 6s-2 5-3 5v7h-2V3z"/></svg>{{ __('ui.rust.v_votes') }}</div><div class="bar"><i data-w="81"></i><b class="num" id="g-votes" data-count="{{ $stats['votes'] }}">0</b></div></div>
    @if ($game->showsBlock('season'))
      <div class="vital wipe" style="--c:var(--amber)"><div class="lbl"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2M9 2h6"/></svg>{{ $game->pageText('rust.v_forced') }}</div><div class="bar"><b class="num" id="forced" title="{{ $forcedWipe?->translatedFormat('j F, H:i') }}">&nbsp;</b></div></div>
    @endif
  </div>

  <!-- Вайп-календар на тиждень -->
  @if ($game->showsBlock('soon'))
    <section style="margin-top:36px" aria-labelledby="cal-h" data-reveal>
      <h2 class="sec" id="cal-h">{{ $game->pageText('rust.calendar') }}</h2>
      <div class="cal" id="cal">
        @foreach ($wipes as $i => $day)
          <div @class(['day', 'today' => $i === 0])>
            <div class="dh" data-today="{{ __('ui.rust.today') }}">{{ $day['date']->translatedFormat('D') }}<b class="num">{{ $day['date']->day }}</b></div>
            @forelse ($day['servers'] as $w)
              @php $rate = $w['server']->fieldOption('rate'); $group = $w['server']->fieldOption('group'); @endphp
              <a class="wchip" href="{{ route('servers.show', $w['server']) }}" data-go="{{ $w['server']->id }}" style="--wc:{{ RustLook::rateColor($rate['value'] ?? null) }}">{{ $w['server']->name }}
                <small>{{ $w['at']->format('H:i') }}@if ($group) · {{ $group['label'] }}@endif @if ($rate) · {{ $rate['label'] }}@endif</small></a>
            @empty
              <span class="none">{{ __('ui.rust.no_wipes') }}</span>
            @endforelse
          </div>
        @endforeach
      </div>
    </section>
  @endif

  <div class="rlayout">
    <!-- Верстак: фільтри — посилання, працюють і без скрипта -->
    <aside class="bench" aria-label="{{ $game->pageText('rust.filters') }}" data-reveal>
      @if ($wipeField)
        <h2>{{ $wipeField->label }}</h2>
        <div class="bp" data-swap="f-wipe">
          @foreach ($wipeField->options as $option)
            @php $n = $counts[$wipeField->id][$option['value']] ?? 0; $on = ($active['wipe'] ?? null) === $option['value']; @endphp
            <a class="bpc" href="{{ $url(['wipe' => $on ? null : $option['value']]) }}" aria-current="{{ $on ? 'true' : 'false' }}" data-filter-link>
              <b>{{ ! empty($option['days']) ? __('ui.rust.days', ['n' => $option['days']]) : $option['label'] }}</b><small>{{ $option['label'] }} · {{ $n }}</small></a>
          @endforeach
        </div>
      @endif

      @if ($groupField)
        <h2>{{ $groupField->label }}</h2>
        <div class="group" data-swap="f-group">
          @foreach ($groupField->options as $option)
            @php $n = $counts[$groupField->id][$option['value']] ?? 0; $on = ($active['group'] ?? null) === $option['value']; @endphp
            <a class="grp" href="{{ $url(['group' => $on ? null : $option['value']]) }}" aria-current="{{ $on ? 'true' : 'false' }}" title="{{ $option['label'] }}: {{ $n }}" data-filter-link>
              <span class="ppl">{!! RustLook::people(RustLook::groupSize($option)) !!}</span><span>{{ $option['label'] }}</span></a>
          @endforeach
        </div>
      @endif

      @if ($rateField)
        <h2>{{ $rateField->label }}</h2>
        <div class="rates" data-swap="f-rate">
          @foreach ($rateField->options as $option)
            @php $n = $counts[$rateField->id][$option['value']] ?? 0; $on = ($active['rate'] ?? null) === $option['value']; @endphp
            @if ($n || $on)
              <a class="rate" href="{{ $url(['rate' => $on ? null : $option['value']]) }}" aria-current="{{ $on ? 'true' : 'false' }}" data-filter-link><i style="--res:{{ RustLook::rateColor($option['value']) }}"></i>{{ $option['label'] }}<small class="num">{{ $n }}</small></a>
            @endif
          @endforeach
        </div>
      @endif

      @if ($sizeField)
        <h2>{{ $sizeField->label }}</h2>
        <div class="sizes" style="--n:{{ count($sizeField->options) + 1 }}" data-swap="f-map_size">
          <a href="{{ $url(['map_size' => null]) }}" aria-current="{{ isset($active['map_size']) ? 'false' : 'true' }}" data-filter-link>{{ __('ui.rust.any') }}</a>
          @foreach ($sizeField->options as $option)
            @php $on = ($active['map_size'] ?? null) === $option['value']; @endphp
            <a href="{{ $url(['map_size' => $on ? null : $option['value']]) }}" aria-current="{{ $on ? 'true' : 'false' }}" data-filter-link>{{ RustLook::size($option['label']) }}</a>
          @endforeach
        </div>
      @endif

      @foreach ($otherFields as $field)
        <h2>{{ $field->label }}</h2>
        <div class="rates" data-swap="f-{{ $field->key }}">
          @foreach ($field->options as $option)
            @php $n = $counts[$field->id][$option['value']] ?? 0; $on = ($active[$field->key] ?? null) === $option['value']; @endphp
            @if ($n || $on)
              <a class="rate" href="{{ $url([$field->key => $on ? null : $option['value']]) }}" aria-current="{{ $on ? 'true' : 'false' }}" data-filter-link>{{ $option['label'] }}<small class="num">{{ $n }}</small></a>
            @endif
          @endforeach
        </div>
      @endforeach

      <div class="checks" data-swap="perks">
        @foreach ($perks as $perk => $label)
          <a class="check" href="{{ $url([$perk => isset($active[$perk]) ? null : '1']) }}" aria-current="{{ isset($active[$perk]) ? 'true' : 'false' }}" data-filter-link><span class="box" aria-hidden="true"></span>{{ $label }}</a>
        @endforeach
      </div>

      <h2>{{ __('ui.rust.search') }}</h2>
      <form class="search" method="get" action="{{ GameUrl::to($game, array_intersect_key($active, GameUrl::pathFields($game)->pluck('key')->flip()->all())) }}" role="search" id="search-form">
        <label class="sr" for="q">{{ __('ui.rust.search_label') }}</label>
        <input id="q" name="q" type="search" maxlength="64" value="{{ $search }}" placeholder="{{ __('ui.rust.search_ph') }}">
        @foreach (array_diff_key($active, GameUrl::pathFields($game)->pluck('key')->flip()->all()) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
        @if ($sort !== 'votes')<input type="hidden" name="sort" value="{{ $sort }}">@endif
      </form>
    </aside>

    <!-- Сервери -->
    <section aria-label="{{ __('ui.game.col_server') }}" data-reveal>
      <div class="list-head">
        <span class="total" data-swap="total">{{ __('ui.rust.found') }} <b class="num" id="count">{{ $fmt($servers->total()) }}</b></span>
        <div class="sorts" role="group" aria-label="{{ __('ui.game.sort') }}">
          @foreach (['votes', 'online', 'wipe', 'ping'] as $s)
            <a href="{{ $url(['sort' => $s !== 'votes' ? $s : null]) }}" aria-current="{{ $sort === $s ? 'true' : 'false' }}" data-filter-link>{{ __("ui.rust.sort_{$s}") }}</a>
          @endforeach
        </div>
      </div>
      <div id="results" aria-live="polite">
        @include('games.themes.rust-list')
      </div>
    </section>
  </div>

  @if ($game->showsBlock('fame') || $game->showsBlock('popular'))
    <section class="band" data-reveal>
      @if ($game->showsBlock('fame'))
        <div class="panel"><h2 class="sec">{{ $game->pageText('rust.fame') }}</h2>
          @if ($fame->isNotEmpty())
            <div class="fame">
              @foreach ($fame->take(3) as $i => $winner)
                <div class="trophy" style="--r:{{ [-2, 2, -1][$i] ?? 0 }}deg"><div class="pl">#1</div>
                  <b>@if ($winner->server)<a href="{{ route('servers.show', $winner->server) }}">{{ $winner->server_name }}</a>@else{{ $winner->server_name }}@endif</b>
                  <small>{{ \Illuminate\Support\Str::ucfirst($winner->month->translatedFormat('F Y')) }} · {{ $fmt($winner->votes) }} {{ trans_choice('ui.server.votes', $winner->votes) }}</small></div>
              @endforeach
            </div>
          @else
            <p class="muted">{{ __('ui.rust.fame_empty') }}</p>
          @endif
        </div>
      @endif
      @if ($game->showsBlock('popular'))
        <div class="panel"><h2 class="sec">{{ $game->pageText('rust.shares') }}</h2>
          @if ($shares)
            <div class="modes">
              @foreach ($shares as $share)
                <div class="mode"><div class="row">{{ $share['label'] }}<span class="num">{{ $share['percent'] }}%</span></div><div class="track"><i data-w="{{ $share['width'] }}"></i></div></div>
              @endforeach
            </div>
          @else
            <p class="muted">{{ __('ui.rust.shares_empty') }}</p>
          @endif
        </div>
      @endif
    </section>
  @endif

  <script type="application/json" id="game-data">@json($client)</script>
</x-layouts.game>
