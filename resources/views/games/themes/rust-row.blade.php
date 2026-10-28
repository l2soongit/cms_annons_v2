@php
    use App\Support\RustLook;
    use App\Support\RustWipes;

    $group = $server->fieldOption('group');
    $rate = $server->fieldOption('rate');
    $wipe = $server->fieldOption('wipe');
    $size = $server->fieldOption('map_size');
    $online = $server->is_online;
    $ping = $server->ping_ms;
    $level = ! $online || $ping === null ? '' : ($ping < 30 ? 'p4' : ($ping < 50 ? 'p3' : ($ping < 90 ? 'p2' : 'p1')));
    // Цикл вайпу: від останнього вайпу (моніторинг) до наступного (частота з поля «Вайп» або форс-вайп)
    $last = $server->last_wiped_at;
    $next = RustWipes::next($server);
    $span = $last && $next ? max(1, $last->diffInSeconds($next)) : null;
    $done = $span ? max(0, min(100, round($last->diffInSeconds(now()) / $span * 100))) : null;
    $fresh = $last && $last->gt(now()->subDay());
    // Повні одиниці («1 тиждень»): короткі (1т, 1д) неоднозначні між мовами
    $short = fn ($date) => $date->locale(app()->getLocale())->diffForHumans(now(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 1]);
    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
    $connect = 'client.connect '.$server->address();
@endphp
<article @class(['srv', 'vip' => $vip, 'hl' => ! $vip && $server->isHighlighted()]) data-name="{{ $server->id }}">
  <span @class(['rank', 'r'.$rank => ! $vip && $rank <= 3])>{{ $vip ? '★' : $rank }}</span>
  <div>
    <div class="name"><a href="{{ route('servers.show', $server) }}">{{ $server->name }}</a>
      @if ($vip)<span class="badge vip">{{ __('ui.rust.sponsor') }}</span>@endif
      @if ($fresh)<span class="badge fresh">{{ __('ui.rust.fresh_badge') }}</span>@endif
      @if ($server->created_at?->gt(now()->subDays(7)))<span class="badge new">{{ __('ui.game.badge_new') }}</span>@endif
    </div>
    <div class="tags">
      @if ($group)<span class="tg" title="{{ __('ui.rust.group_hint') }}">{!! RustLook::people(min(3, RustLook::groupSize($group))) !!}{{ $group['label'] }}</span>@endif
      @if ($rate)<span class="tg"><i style="--res:{{ RustLook::rateColor($rate['value']) }}"></i>{{ $rate['label'] }}</span>@endif
      @if ($wipe)<span class="tg">{{ $wipe['label'] }}</span>@endif
      @if ($size)<span class="tg">{{ RustLook::size($size['label']) }}</span>@endif
      @if ($server->modded !== null)<span class="tg">{{ $server->modded ? __('ui.rust.oxide') : __('ui.rust.vanilla_tag') }}</span>@endif
    </div>
    <div class="ip">{{ $connect }}</div>
  </div>
  <div class="cycle">
    @if ($last)
      <div class="row"><span>{!! str_replace(':time', '<b>'.e($short($last)).'</b>', e(__('ui.rust.wiped'))) !!}</span>
        @if ($next)<span @class(['soon' => $next->lt(now()->addDay())])>{!! str_replace(':time', '<b>'.e($short($next)).'</b>', e(__('ui.rust.next'))) !!}</span>@endif</div>
      <div class="track" title="{{ $done !== null ? __('ui.rust.cycle', ['n' => $done]) : '' }}"><i style="width:{{ $done ?? 0 }}%"></i></div>
    @else
      <div class="row"><span>{{ __('ui.rust.wipe_unknown') }}</span>@if ($next)<span @class(['soon' => $next->lt(now()->addDay())])>{!! str_replace(':time', '<b>'.e($short($next)).'</b>', e(__('ui.rust.next'))) !!}</span>@endif</div>
      <div class="track is-unknown"><i style="width:0"></i></div>
    @endif
    <small>{{ $next ? __('ui.rust.next_at', ['date' => $next->translatedFormat('D, j M, H:i')]) : __('ui.rust.next_unknown') }}</small>
  </div>
  <div class="right">
    <span class="players">
      @if ($online)
        <span><b class="num" data-online>{{ $fmt($server->players) }}</b>/{{ $fmt($server->max_players) }}</span>
        @if ($server->queue)<span class="q">{{ __('ui.rust.queue', ['n' => $server->queue]) }}</span>@endif
      @else
        <span class="off">{{ __('ui.rust.offline') }}</span>
      @endif
      <span class="ping {{ $level }}" title="{{ $online && $ping !== null ? __('ui.rust.ms', ['n' => $ping]) : __('ui.rust.no_ping') }}" role="img" aria-label="{{ $online && $ping !== null ? __('ui.rust.ms', ['n' => $ping]) : __('ui.rust.no_ping') }}"><i></i><i></i><i></i><i></i></span>
    </span>
    <span class="vt"><b class="num" data-server-votes="{{ $server->id }}">{{ $fmt($server->votes_month) }}</b> {{ trans_choice('ui.server.votes', $server->votes_month) }}</span>
    <span class="acts">
      <a class="btn sm go" href="{{ route('servers.vote', $server) }}">{{ __('ui.rust.vote') }}</a>
      <button class="btn sm copy" type="button" data-ip="{{ $connect }}">{{ __('ui.rust.connect') }}</button>
    </span>
  </div>
</article>
