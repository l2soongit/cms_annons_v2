@php
    use App\Support\HomePageData;
    use App\Support\McPixels;

    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
    // «Minecraft» → «MINE» і «CRAFT»: два блоки падають по черзі, як у демо; кілька слів — кожне окремо
    $title = trim($game->hero_title ?: $game->name);
    $words = preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY);
    if (count($words) === 1 && mb_strlen($title) >= 6) {
        $half = intdiv(mb_strlen($title), 2);
        $words = [mb_substr($title, 0, $half), mb_substr($title, $half)];
    }
    $cover = HomePageData::url($game->cover);
    // Посилання з усіма поточними фільтрами, де змінено лише передане
    $url = fn (array $changes) => route('games.show', array_filter([
        'game' => $game->slug, ...$active, 'sort' => $sort !== 'votes' ? $sort : null, 'q' => $search ?: null, ...$changes,
    ], fn ($v) => $v !== null && $v !== ''));
    $edition = $filters->firstWhere('key', 'edition') ?? $filters->first();
    $versions = $filters->firstWhere('key', 'version');
    $rowFields = $filters->reject(fn ($f) => $f->is($edition) || ($versions && $f->is($versions)));
    $splash = (array) $game->pageText('mc.splash');
    $units = __('ui.game.units');
    $medals = ['#ffd84a', '#e6e6e6', '#e8915a'];
    $client = [
        'game' => $game->slug,
        'seasonEnd' => $stats['season_end'],
        'feed' => ['url' => route('home.live'), 'after' => (int) \App\Models\Vote::where('created_at', '<', now()->subMinutes(10))->max('id'), 'enabled' => config('graj.home.live_feed') && $game->showsBlock('live')],
        'locale' => app()->getLocale(),
        'splash' => $splash,
        't' => ['votes' => __('ui.server.votes'), 'untilStart' => __('ui.game.until_start'), 'copied' => __('ui.mc.copied'), 'anon' => __('ui.mc.anon'), 'voted' => __('ui.mc.voted')],
    ];
@endphp

<x-layouts.game :game="$game" :assets="['resources/css/game-mc.css', 'resources/js/game-mc.js']" fonts="family=Press+Start+2P">
  <x-slot:background><div class="chat" id="chat" aria-live="polite"></div></x-slot:background>
  <x-slot:headerCta><a class="btn btn-green btn-sm" href="{{ route('cabinet.servers.create') }}">{{ __('ui.nav.add_server') }}</a></x-slot:headerCta>

  <!-- Банер: піксельний світ — небо, рельєф з рудами, дерева, блок трави, що обертається -->
  <section class="mhero" aria-labelledby="game-h">
    <div class="sky" aria-hidden="true"></div>
    @if ($cover)<div class="hero-img" style="background-image:url('{{ $cover }}')" aria-hidden="true"></div>@endif
    <span class="msun" aria-hidden="true"></span>
    <span class="mcloud" style="top:48px;animation-duration:70s;animation-delay:-10s" aria-hidden="true"></span>
    <span class="mcloud" style="top:96px;animation-duration:95s;animation-delay:-50s" aria-hidden="true"></span>
    <span class="mcloud" style="top:30px;animation-duration:120s;animation-delay:-80s" aria-hidden="true"></span>
    <div class="terrain" id="terrain" aria-hidden="true"></div>
    <div id="trees" aria-hidden="true"></div>
    <div class="cube-shadow" aria-hidden="true"></div>
    <div class="cube-wrap" aria-hidden="true"><div class="cube"><i></i><i></i><i></i><i></i><i></i><i></i></div></div>
    <div class="content">
      @if ($game->eyebrow)<span class="tagpx">{{ $game->eyebrow }}</span>@endif
      <h1 id="game-h">@foreach ($words as $i => $word)<span class="w" style="--i:{{ $i }}">{{ $word }}</span>@endforeach</h1>
      @if ($game->description)<p class="lead">{{ $game->description }}</p>@endif
    </div>
    @if ($splash)<span class="splash" id="splash" aria-hidden="true">{{ $splash[0] }}</span>@endif
  </section>
  <div class="hotbar" aria-label="{{ __('ui.game.title', ['game' => $game->name]) }}">
    <div><b class="num" data-count="{{ $stats['servers'] }}">0</b><span>{{ trans_choice('ui.game.stat_servers', $stats['servers']) }}</span></div>
    <div><b class="num g" id="g-online" data-count="{{ $stats['online'] }}">0</b><span>{{ __('ui.home.kpi_online') }}</span></div>
    <div><b class="num y" id="g-votes" data-count="{{ $stats['votes'] }}">0</b><span>{{ __('ui.mc.stat_votes') }}</span></div>
    @if ($game->showsBlock('season'))<div><b class="num" id="season">&nbsp;</b><span>{{ $game->pageText('mc.season') }}</span></div>@endif
  </div>
  <x-ad-banner placement="game" :game="$game" gap="20px auto 0" />

  <!-- «Скоро відкриття» як спливні досягнення -->
  @if ($comingSoon->isNotEmpty() && $game->showsBlock('soon'))
    <section class="achs" aria-labelledby="soon-h" data-reveal>
      <h2 class="sec-px" id="soon-h">{{ $game->pageText('mc.soon') }}</h2>
      <div class="ach-row" id="soon">
        @foreach ($comingSoon as $s)
          @php
              $opens = $s->opens_at->copy()->startOfDay();
              $mode = optional($s->fieldValues->first(fn ($v) => $v->field?->key === 'mode'), fn ($v) => collect($v->field?->options)->firstWhere('value', $v->value)['label'] ?? $v->value);
          @endphp
          <article class="ach">
            <span class="aico">{!! McPixels::soon($mode) !!}</span>
            <div><div class="atitle">{{ $game->pageText('mc.soon_title') }}</div><div class="aname">{{ $s->name }}</div><div class="ainfo">{{ $s->fieldLabels() }}</div></div>
            <div class="cd" data-opens="{{ $opens->toIso8601String() }}" role="timer">
              @foreach (['d', 'h', 'm', 's'] as $i => $u)
                <div class="unit"><div class="tile" data-u="{{ $u }}"><span class="val">00</span></div><span class="lbl">{{ $units[$i] }}</span></div>
              @endforeach
            </div>
            <div class="afoot"><span>{{ __('ui.mc.soon_start', ['date' => $opens->translatedFormat('j F')]) }}</span><a class="btn btn-sm" href="{{ route('servers.show', $s) }}">{{ __('ui.mc.details') }}</a></div>
          </article>
        @endforeach
      </div>
    </section>
  @endif

  <div class="mlayout">
    <!-- Фільтри як інвентар: посилання з GET-параметрами, працюють і без скрипта -->
    <aside class="inv" aria-label="{{ __('ui.game.filters') }}" data-reveal>
      @if ($edition)
        <h3>{{ $edition->label }}</h3>
        <div class="lever" role="group" aria-label="{{ $edition->label }}" data-swap="edition">
          <a href="{{ $url([$edition->key => null]) }}" aria-pressed="{{ isset($active[$edition->key]) ? 'false' : 'true' }}" data-filter-link>{{ __('ui.game.any') }}</a>
          @foreach ($edition->options as $option)
            @php $on = ($active[$edition->key] ?? null) === $option['value']; @endphp
            <a href="{{ $url([$edition->key => $on ? null : $option['value']]) }}" aria-pressed="{{ $on ? 'true' : 'false' }}" data-filter-link>{{ ($option['short'] ?? '') ?: $option['label'] }}</a>
          @endforeach
        </div>
      @endif
      @if ($versions)
        <h3>{{ $versions->label }}</h3>
        <div class="slots" data-swap="versions">
          @foreach ($versions->options as $option)
            @php $n = $counts[$versions->id][$option['value']] ?? 0; $on = ($active[$versions->key] ?? null) === $option['value']; @endphp
            <a class="slot" href="{{ $url([$versions->key => $on ? null : $option['value']]) }}" aria-pressed="{{ $on ? 'true' : 'false' }}" aria-label="{{ $versions->label }} {{ $option['label'] }}" data-filter-link>{{ $option['label'] }}<span class="cnt">{{ $n ?: '' }}</span></a>
          @endforeach
        </div>
      @endif
      @foreach ($rowFields as $field)
        <h3>{{ $field->label }}</h3>
        <div class="slot-row" data-swap="field-{{ $field->key }}">
          @foreach ($field->options as $option)
            @php $n = $counts[$field->id][$option['value']] ?? 0; $on = ($active[$field->key] ?? null) === $option['value']; @endphp
            <a class="mode-slot" href="{{ $url([$field->key => $on ? null : $option['value']]) }}" aria-pressed="{{ $on ? 'true' : 'false' }}" data-filter-link><span class="ic">{!! McPixels::mode($option['label']) !!}</span>{{ $option['label'] }}<span class="cnt">{{ $n }}</span></a>
          @endforeach
        </div>
      @endforeach
      <h3>{{ __('ui.search.submit') }}</h3>
      <form method="get" action="{{ route('games.show', $game) }}" role="search" id="search-form">
        <label class="sr" for="q">{{ __('ui.mc.search_label') }}</label>
        <input id="q" name="q" type="search" maxlength="64" value="{{ $search }}" placeholder="{{ __('ui.mc.search') }}">
        @foreach ($active as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
        @if ($sort !== 'votes')<input type="hidden" name="sort" value="{{ $sort }}">@endif
      </form>
      <div data-swap="reset">
        @if ($active || $search !== '')<a class="btn btn-sm reset" href="{{ route('games.show', $game) }}" data-filter-link>{{ __('ui.game.reset') }}</a>@endif
      </div>
    </aside>

    <!-- Список як меню «Мережева гра» -->
    <section class="mp" aria-label="{{ __('ui.game.col_server') }}" data-reveal>
      <div class="mp-head">
        <span data-swap="count">{{ __('ui.mc.servers') }} <span class="num" id="count">{{ $servers->total() }}</span></span>
        <div class="sorts" role="group" aria-label="{{ __('ui.game.sort') }}">
          @foreach (['votes', 'online', 'ping', 'new', 'opening'] as $s)
            <a href="{{ $url(['sort' => $s !== 'votes' ? $s : null]) }}" aria-pressed="{{ $sort === $s ? 'true' : 'false' }}" data-filter-link>{{ __("ui.mc.sort_{$s}") }}</a>
          @endforeach
        </div>
      </div>
      <div id="results" aria-live="polite">
        @include('games.themes.mc-list')
      </div>
    </section>
  </div>

  @if ($game->showsBlock('fame') || $game->showsBlock('popular'))
  <section class="band" data-reveal>
    @if ($game->showsBlock('fame'))
    <div class="panel fame">
      <h3>{{ $game->pageText('mc.fame') }}</h3>
      @if ($fame->isNotEmpty())
        <ul>
          @foreach ($fame->take(3) as $winner)
            <li><span class="trophy">{!! McPixels::trophy($medals[$loop->index] ?? $medals[2]) !!}</span>
              <span>@if ($winner->server)<a href="{{ route('servers.show', $winner->server) }}"><b>{{ $winner->server_name }}</b></a>@else<b>{{ $winner->server_name }}</b>@endif<small>{{ \Illuminate\Support\Str::ucfirst($winner->month->translatedFormat('F')) }}</small></span>
              <span class="v num">{{ $fmt($winner->votes) }}</span></li>
          @endforeach
        </ul>
      @else
        <p class="muted">{{ __('ui.game.fame_empty') }}</p>
      @endif
    </div>
    @endif
    @if ($game->showsBlock('popular'))
    <div class="panel">
      <h3>{{ $game->pageText('mc.popular') }}</h3>
      @forelse ($shares as $share)
        <div class="xp"><div class="l"><b>{{ $share['label'] }}</b><span class="num">{{ $share['percent'] }}%</span></div><div class="track"><i data-w="{{ $share['width'] }}"></i></div></div>
      @empty
        <p class="muted">{{ __('ui.mc.popular_empty') }}</p>
      @endforelse
    </div>
    @endif
  </section>
  @endif

  <script type="application/json" id="game-data">@json($client)</script>
</x-layouts.game>
