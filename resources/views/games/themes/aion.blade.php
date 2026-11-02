@php
    use App\Support\GameUrl;

    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
    // Посилання з усіма поточними фільтрами, де змінено лише передане
    $url = fn (array $changes) => GameUrl::to($game, [...$active, 'sort' => $sort !== 'votes' ? $sort : null, 'q' => $search ?: null, ...$changes]);
    $versionField = $filters->firstWhere('key', 'version');
    $chipFields = $filters->reject(fn ($f) => $f->key === 'version');
    $perks = ['balanced' => __('ui.aion.balanced'), 'notempty' => __('ui.aion.notempty')];
    $champ = $fame->first();
    // Баланс рас: Елійці — колонка alliance, Асмодіанці — horde (див. WowFactions і API elyos / asmodians)
    $sides = ['asmo' => $war['horde'], 'ely' => $war['alliance']];
    $client = [
        'game' => $game->slug,
        'seasonEnd' => $stats['season_end'],
        'feed' => ['url' => route('home.live'), 'after' => (int) \App\Models\Vote::where('created_at', '<', now()->subMinutes(10))->max('id'), 'enabled' => config('graj.home.live_feed') && $game->showsBlock('live')],
        'locale' => app()->getLocale(),
        't' => ['votes' => __('ui.server.votes'), 'untilStart' => __('ui.game.until_start'), 'copied' => __('ui.aion.copied'), 'voted' => __('ui.aion.toast_vote'), 'anon' => __('ui.aion.toast_anon')],
    ];
@endphp

<x-layouts.game :game="$game" :assets="['resources/css/game-aion.css', 'resources/js/game-aion.js']" fonts="family=Cormorant+SC:wght@600;700">
  <x-slot:headerCta><a class="btn sm" href="{{ route('cabinet.servers.create') }}">{{ __('ui.nav.add_server') }}</a></x-slot:headerCta>

  <!-- Банер: дві раси дивляться одна на одну через розлом. Клік по стороні — обрати расу -->
  <section class="ahero bleed" id="ahero" style="--art:url('{{ asset('images/games/aion/faces.webp') }}')" aria-labelledby="game-h">
    <h1 class="sr" id="game-h">{{ trim($game->hero_title ?: __('ui.aion.title_sr')) }}</h1>
    @foreach ($sides as $side => $count)
      <button class="side {{ $side }}" type="button" aria-pressed="false" data-f="{{ $side }}" aria-label="{{ __("ui.aion.pick_{$side}") }}">
        <span class="eyebrow">{{ __("ui.aion.{$side}_land") }}</span>
        <span class="fname">{{ __("ui.aion.{$side}") }}</span>
        <span class="fabout">{{ __("ui.aion.{$side}_about") }}</span>
        @if ($war['realms'])
          <span class="fnum num" data-count="{{ $count }}">0</span>
          <span class="fsub">{{ __('ui.aion.on_servers') }}</span>
        @endif
        <span class="fpick">{{ __("ui.aion.pick_{$side}") }}</span>
      </button>
    @endforeach
  </section>

  <!-- Смуга-дашборд: сервери, онлайн, голоси (як на сторінках інших ігор) і відлік до фіналу сезону -->
  <div class="band bleed">
    <div class="wrap">
      <dl class="gstats">
        <div><dd class="num" data-count="{{ $stats['servers'] }}">0</dd><dt>{{ trans_choice('ui.game.stat_servers', $stats['servers']) }}</dt></div>
        <div><dd class="num ely" id="g-online" data-count="{{ $stats['online'] }}">0</dd><dt>{{ __('ui.home.kpi_online') }}</dt></div>
        <div><dd class="num asmo" id="g-votes" data-count="{{ $stats['votes'] }}">0</dd><dt>{{ __('ui.home.kpi_votes') }}</dt></div>
      </dl>
      @if ($game->showsBlock('season'))
        <div class="season">
          <div class="msg">{{ $game->pageText('aion.season') }}</div>
          <div class="clock" id="clock" data-end="{{ $stats['season_end'] }}" aria-label="{{ __('ui.home.season_left') }}">
            @foreach (['d', 'h', 'm', 's'] as $u)
              <div><b class="num" data-u="{{ $u }}">00</b><small>{{ __("ui.aion.u_{$u}") }}</small></div>
            @endforeach
          </div>
        </div>
      @endif
    </div>
  </div>
  <x-ad-banner placement="game" :game="$game" gap="20px auto 0" />

  <!-- Баланс рас і сервер місяця -->
  <section class="duo" aria-labelledby="war-h">
    <div class="panel" data-reveal>
      <span class="eyebrow">{{ __('ui.aion.war_eyebrow') }}</span>
      <h2 class="h2" id="war-h">{{ $game->pageText('aion.war') }}</h2>
      @if ($war['realms'])
        <p class="muted">{{ trans_choice('ui.aion.war_lead', $war['realms'], ['n' => $war['realms']]) }}</p>
        <div class="balbar" style="--split:{{ $war['alliance_pct'] }}%">
          <span class="e">{{ __('ui.aion.ely') }} {{ number_format($war['alliance_pct'], 1, ',', '') }}%</span>
          <span class="a">{{ __('ui.aion.asmo') }} {{ number_format(100 - $war['alliance_pct'], 1, ',', '') }}%</span>
        </div>
        @if (count($war['rows']) > 1)
          <div class="vrows">
            @foreach ($war['rows'] as $row)
              <div><b>{{ $row['label'] }}</b><i style="--e:{{ $row['pct'] }}%"></i><span class="num">{{ round($row['pct']) }} / {{ round(100 - $row['pct']) }}</span></div>
            @endforeach
          </div>
        @endif
      @else
        <p class="muted">{{ __('ui.aion.war_none') }}</p>
        <p class="note"><a href="{{ route('developers') }}#online">{{ __('ui.aion.war_how') }}</a></p>
      @endif
    </div>
    <div class="champ" style="--champ:url('{{ asset('images/games/aion/drakan.webp') }}')" data-reveal>
      @if ($champ)
        <span class="eyebrow">{{ __('ui.aion.champ', ['month' => $champ->month->translatedFormat('F')]) }}</span>
        <h3>@if ($champ->server)<a href="{{ route('servers.show', $champ->server) }}">{{ $champ->server_name }}</a>@else{{ $champ->server_name }}@endif</h3>
        <p><b class="num">{{ $fmt($champ->votes) }}</b> {{ trans_choice('ui.server.votes', $champ->votes) }}</p>
      @else
        <span class="eyebrow">{{ __('ui.aion.champ_none_eyebrow') }}</span>
        <h3>{{ __('ui.aion.champ_none') }}</h3>
        <p>{{ __('ui.aion.champ_none_hint') }}</p>
      @endif
    </div>
  </section>

  <!-- Сервери: вкладки версій, фільтри, список -->
  <section class="servers" id="servers" aria-labelledby="servers-h">
    <div class="head" data-reveal>
      <div><span class="eyebrow">{{ __('ui.aion.servers_eyebrow') }}</span><h2 class="h2 big" id="servers-h">{{ $game->pageText('aion.servers') }}</h2></div>
      <p class="muted">{{ __('ui.aion.servers_lead') }}</p>
    </div>

    @if ($versionField)
      <nav class="tabs" aria-label="{{ $versionField->label }}" data-swap="f-version">
        <a href="{{ $url(['version' => null]) }}" aria-current="{{ isset($active['version']) ? 'false' : 'true' }}" data-filter-link>{{ __('ui.aion.all_versions') }}</a>
        @foreach ($versionField->options as $option)
          @php $n = $counts[$versionField->id][$option['value']] ?? 0; $on = ($active['version'] ?? null) === $option['value']; @endphp
          @if ($n || $on)
            <a href="{{ $url(['version' => $on ? null : $option['value']]) }}" aria-current="{{ $on ? 'true' : 'false' }}" data-filter-link>{{ $option['label'] }}<small class="num">{{ $n }}</small></a>
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
              <a href="{{ $url([$field->key => $on ? null : $option['value']]) }}" aria-current="{{ $on ? 'true' : 'false' }}" data-filter-link>{{ $option['label'] }}<small class="num">{{ $counts[$field->id][$option['value']] ?? 0 }}</small></a>
            @endforeach
          </div>
        @endif
      @endforeach
      <div class="fgroup">
        @foreach ($perks as $perk => $label)
          <a href="{{ $url([$perk => isset($active[$perk]) ? null : '1']) }}" aria-current="{{ isset($active[$perk]) ? 'true' : 'false' }}" data-filter-link>{{ $label }}</a>
        @endforeach
      </div>
      <form class="search" method="get" action="{{ GameUrl::to($game, array_intersect_key($active, GameUrl::pathFields($game)->pluck('key')->flip()->all())) }}" role="search" id="search-form">
        <label class="sr" for="q">{{ __('ui.aion.search_label') }}</label>
        <input id="q" name="q" type="search" maxlength="64" value="{{ $search }}" placeholder="{{ __('ui.aion.search_ph') }}">
        @foreach (array_diff_key($active, GameUrl::pathFields($game)->pluck('key')->flip()->all()) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
        @if ($sort !== 'votes')<input type="hidden" name="sort" value="{{ $sort }}">@endif
      </form>
    </div>

    <div class="list-head">
      <span class="total" data-swap="total">{{ __('ui.aion.found') }} <b class="num">{{ $fmt($servers->total()) }}</b></span>
      <div class="sorts" role="group" aria-label="{{ __('ui.game.sort') }}">
        @foreach (['votes', 'online', 'new', 'uptime'] as $s)
          <a href="{{ $url(['sort' => $s !== 'votes' ? $s : null]) }}" aria-current="{{ $sort === $s ? 'true' : 'false' }}" data-filter-link>{{ __("ui.aion.sort_{$s}") }}</a>
        @endforeach
      </div>
    </div>
    <div id="results" aria-live="polite">
      @include('games.themes.aion-list')
    </div>
  </section>

  @if ($game->showsBlock('soon') && $comingSoon->isNotEmpty())
    <section class="soon bleed" aria-labelledby="soon-h">
      <div class="wrap">
        <div class="head" data-reveal><div><span class="eyebrow">{{ __('ui.aion.soon_eyebrow') }}</span><h2 class="h2 big" id="soon-h">{{ $game->pageText('aion.soon') }}</h2></div></div>
        <div class="soon-grid">
          @foreach ($comingSoon as $i => $server)
            <article class="soon-card" style="--g1:{{ ['#f3c96955', '#8f6bff66', '#4fd18b44'][$i % 3] }}" data-reveal>
              <span class="eyebrow">{{ $server->fieldLabels() }}</span>
              <h3><a href="{{ route('servers.show', $server) }}">{{ $server->name }}</a></h3>
              <div class="when">
                <div class="cd" data-opens="{{ $server->opens_at->toIso8601String() }}">
                  @foreach (['d', 'h', 'm', 's'] as $u)<span class="tile" data-u="{{ $u }}"><span class="val">00</span><small>{{ __("ui.aion.u_{$u}") }}</small></span>@endforeach
                </div>
                <a class="btn sm" href="{{ route('servers.show', $server) }}">{{ __('ui.aion.details') }}</a>
              </div>
            </article>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  @if ($game->showsBlock('fame'))
    <section class="fame-sec" aria-labelledby="fame-h">
      <div class="head" data-reveal><div><span class="eyebrow">{{ __('ui.aion.fame_eyebrow') }}</span><h2 class="h2 big" id="fame-h">{{ $game->pageText('aion.fame') }}</h2></div></div>
      @if ($fame->isNotEmpty())
        <div class="fame" data-reveal>
          @foreach ($fame as $winner)
            <div>
              <small>{{ \Illuminate\Support\Str::ucfirst($winner->month->translatedFormat('F Y')) }}</small>
              <b>@if ($winner->server)<a href="{{ route('servers.show', $winner->server) }}">{{ $winner->server_name }}</a>@else{{ $winner->server_name }}@endif</b>
              <span>{{ $fmt($winner->votes) }} {{ trans_choice('ui.server.votes', $winner->votes) }}</span>
            </div>
          @endforeach
        </div>
      @else
        <p class="muted">{{ __('ui.aion.fame_empty') }}</p>
      @endif
    </section>
  @endif

  <script type="application/json" id="game-data">@json($client)</script>
</x-layouts.game>
