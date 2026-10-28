@php
    use App\Support\DayzMaps;

    $map = $server->fieldOption('map');
    $style = $server->fieldOption('style');
    $perspective = $server->fieldOption('perspective');
    $mods = $server->fieldOption('mods');
    $online = $server->is_online;
    $ping = $server->ping_ms;
    $level = ! $online || $ping === null ? '' : ($ping < 30 ? 'p4' : ($ping < 50 ? 'p3' : ($ping < 90 ? 'p2' : 'p1')));
    [$bars, $state, $color] = DayzMaps::condition($server->uptime !== null ? (float) $server->uptime : null);
    $uptime = number_format((float) $server->uptime, 1, ',', '');
    // Ігровий час з моніторингу (A2S): скрипт рахує далі з урахуванням прискорення від моменту перевірки
    $time = $online ? $server->game_time : null;
    $day = $time !== null && (int) substr($time, 0, 2) >= 6 && (int) substr($time, 0, 2) < 20;
    $accel = $server->time_accel ? rtrim(rtrim(number_format($server->time_accel, 1, '.', ''), '0'), '.') : null;
    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
@endphp
<article @class(['srv', 'vip' => $vip, 'hl' => ! $vip && $server->isHighlighted()]) data-name="{{ $server->id }}">
  <span @class(['rank', 'r'.$rank => ! $vip && $rank <= 3])>{{ $vip ? '★' : $rank }}</span>
  <div>
    <div class="name"><a href="{{ route('servers.show', $server) }}">{{ $server->name }}</a>
      @if ($vip)<span class="badge vip">{{ __('ui.dayz.sponsor') }}</span>@endif
      @if ($server->created_at?->gt(now()->subDays(7)))<span class="badge new">{{ __('ui.game.badge_new') }}</span>@endif
    </div>
    <div class="meta">
      @if ($map)<span class="m"><i class="dot" style="--mc:{{ DayzMaps::color($map['value']) }}"></i>{{ $map['label'] }}</span>@endif
      @if ($style)<span class="m">{{ $style['label'] }}</span>@endif
      @if ($perspective)<span class="m"><span class="pp">{{ $perspective['label'] }}</span></span>@endif
      @if ($mods)<span class="m">{{ $mods['value'] === '1' ? __('ui.dayz.with_mods') : __('ui.dayz.no_mods') }}</span>@endif
    </div>
    <div class="ip">{{ $server->address() }}</div>
  </div>
  <div class="world">
    @if ($time !== null)
      <span @class(['gtime', 'day' => $day, 'night' => ! $day]) data-time="{{ $time }}" data-accel="{{ $server->time_accel ?? 1 }}" data-at="{{ $server->checked_at?->getTimestampMs() }}" title="{{ __('ui.dayz.time_hint', ['n' => $accel ?? 1]) }}">
        <svg class="ic-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        <svg class="ic-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/></svg>
        <span class="num" data-clock>{{ $time }}</span>@if ($accel)<small>×{{ $accel }}</small>@endif
      </span>
    @else
      <span class="gtime none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><small>{{ __('ui.dayz.no_time') }}</small></span>
    @endif
    <span class="cond" style="--cc:{{ $color }}" title="{{ __('ui.dayz.uptime_hint', ['n' => $uptime]) }}">{{ __('ui.dayz.condition') }}: <b>{{ __("ui.dayz.states.{$state}") }}</b>
      <span class="bar5" aria-hidden="true">@for ($k = 1; $k <= 5; $k++)<i @class(['on' => $k <= $bars])></i>@endfor</span></span>
  </div>
  <div class="right">
    <span class="players">
      @if ($online)
        <span><b class="num" data-online>{{ $fmt($server->players) }}</b>/{{ $fmt($server->max_players) }}</span>
        @if ($server->queue)<span class="q">{{ __('ui.dayz.queue', ['n' => $server->queue]) }}</span>@endif
      @else
        <span class="off">{{ __('ui.dayz.offline') }}</span>
      @endif
      <span class="ping {{ $level }}" title="{{ $online && $ping !== null ? __('ui.dayz.ms', ['n' => $ping]) : __('ui.dayz.no_ping') }}" role="img" aria-label="{{ $online && $ping !== null ? __('ui.dayz.ms', ['n' => $ping]) : __('ui.dayz.no_ping') }}"><i></i><i></i><i></i><i></i></span>
    </span>
    <span class="vt"><b class="num" data-server-votes="{{ $server->id }}">{{ $fmt($server->votes_month) }}</b> {{ trans_choice('ui.server.votes', $server->votes_month) }}</span>
    <span class="acts">
      <a class="btn sm go" href="{{ route('servers.vote', $server) }}">{{ __('ui.dayz.vote') }}</a>
      <button class="btn sm copy" type="button" data-ip="{{ $server->connectCommand() }}">{{ __('ui.dayz.ip') }}</button>
    </span>
  </div>
</article>
