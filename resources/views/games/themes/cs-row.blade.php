@php
    use App\Support\CsMaps;

    $values = $server->fieldValues->keyBy(fn ($v) => $v->field?->key);
    $label = fn (string $key) => ($v = $values->get($key)) ? (collect($v->field?->options)->firstWhere('value', $v->value)['label'] ?? $v->value) : null;
    $version = $label('version');
    $mode = $label('mode');
    $tick = $label('tickrate');
    $old = ($values->get('version')?->value) === 'cs-16';
    $online = $server->is_online;
    $segments = $online ? max(1, (int) round($server->fillRatio() * 10)) : 0;
    $full = $online && $server->max_players > 0 && $server->players >= $server->max_players - 1;
    $ping = $server->ping_ms;
    $pingClass = ! $online || $ping === null ? 'off' : ($ping < 40 ? 'good' : ($ping < 90 ? 'mid' : 'bad'));
@endphp
<div @class(['row', 'srv', 'v16' => $old, 'vip' => $vip]) data-name="{{ $server->id }}">
  @if ($vip)
    <span class="rank vipr">VIP</span>
  @elseif ($rank <= 3)
    <span class="chev g{{ $rank }}" aria-label="{{ __('ui.cs.place', ['n' => $rank]) }}">{!! str_repeat('<i></i>', 4 - $rank) !!}<b>#{{ $rank }}</b></span>
  @else
    <span class="rank">{{ $rank }}</span>
  @endif
  <div class="who">
    <div class="nm"><b><a href="{{ route('servers.show', $server) }}">{{ $server->name }}</a></b>@if ($server->created_at?->gt(now()->subDays(7)))<span class="bdg new">{{ __('ui.game.badge_new') }}</span>@endif</div>
    <div class="badges">
      @if ($mode ?? $version)<span class="bdg {{ $old ? 'ver16' : 'ver2' }}">{{ $mode ?? $version }}</span>@endif
      @if ($tick)<span class="bdg tick">{{ $tick }} tick</span>@endif
      @if ($server->vac)<span class="bdg vac"><svg width="9" height="9" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l8 3v6c0 5-3.5 9.5-8 11-4.5-1.5-8-6-8-11V5z"/></svg>VAC</span>@endif
    </div>
  </div>
  <div class="map">
    @if ($server->current_map)
      <span>{{ CsMaps::name($server->current_map) }}<small>{{ $server->current_map }}</small></span>
    @else
      <span class="muted">—</span>
    @endif
  </div>
  <div class="players">
    @if ($online)<b class="num" data-online>{{ $server->players }}</b><small> / {{ $server->max_players }}</small>@else<small>{{ __('ui.server.offline') }}</small>@endif
    <div @class(['pbar', 'full' => $full]) aria-hidden="true">@for ($k = 0; $k < 10; $k++)<i class="{{ $k < $segments ? 'on' : '' }}"></i>@endfor</div>
  </div>
  <span class="ping {{ $pingClass }}" title="{{ __('ui.cs.ping_hint') }}"><span class="bars"><i></i><i></i><i></i><i></i></span><span class="num" data-ping>{{ $pingClass === 'off' ? '—' : __('ui.cs.ms', ['n' => $ping]) }}</span></span>
  <div class="vt num" data-server-votes="{{ $server->id }}">{{ number_format($server->votes_month, 0, ',', "\u{00A0}") }}</div>
  <div class="acts">
    <a class="btn btn-line btn-sm vote" href="{{ route('servers.vote', $server) }}" aria-label="{{ __('ui.server.vote') }}" title="{{ __('ui.server.vote') }}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg></a>
    <button class="btn btn-ct btn-sm connect" type="button" data-connect="connect {{ $server->address() }}" @disabled(! $online)>{{ __('ui.game.connect') }}</button>
  </div>
</div>
