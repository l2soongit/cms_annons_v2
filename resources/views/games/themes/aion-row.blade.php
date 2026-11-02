@php
    use App\Support\WowFactions;

    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
    $version = $server->fieldOption('version');
    $rates = $server->fieldOption('rates');
    $type = $server->fieldOption('type');
    // Частка Елійців (колонка alliance) або null, якщо сервер не передає баланс рас
    $ely = WowFactions::share($server);
    $address = $server->host.($server->port && $server->port !== $game->default_port ? ':'.$server->port : '');
@endphp
<article @class(['srv', 'vip' => $vip, 'hl' => ! $vip && $server->isHighlighted()]) data-name="{{ $server->id }}" @if ($ely !== null) data-ely="{{ $ely }}" @endif>
  <div @class(['rank num', 'top' => ! $vip && $rank <= 3])>{{ $vip ? '★' : $rank }}</div>
  <div class="main">
    <h3><a href="{{ route('servers.show', $server) }}">{{ $server->name }}</a>
      @if ($vip)<span class="tag vip">VIP</span>@endif
      @if ($server->created_at?->gt(now()->subDays(30)))<span class="tag new">{{ __('ui.game.badge_new') }}</span>@endif
    </h3>
    <div class="meta">
      @if ($version)<span class="v">{{ $version['label'] }}</span>@endif
      @if ($rates)<span>{{ $rates['label'] }}</span>@endif
      @if ($type)<span>{{ $type['label'] }}</span>@endif
      @if ($server->uptime > 0)<span>{{ __('ui.aion.uptime', ['n' => rtrim(rtrim(number_format((float) $server->uptime, 1, ',', ''), '0'), ',')]) }}</span>@endif
    </div>
    @if ($about = \Illuminate\Support\Str::limit(strip_tags((string) $server->description), 140))
      <p class="about">{{ $about }}</p>
    @endif
    <div class="addr"><code>{{ $address }}</code><button type="button" class="copy" data-ip="{{ $address }}">{{ __('ui.aion.copy') }}</button></div>
  </div>
  <div class="race">
    @if ($ely !== null)
      <div class="lbl"><span class="e">{{ __('ui.aion.ely') }} {{ $ely }}%</span><span class="a">{{ __('ui.aion.asmo') }} {{ 100 - $ely }}%</span></div>
      <div class="t"><i style="width:{{ $ely }}%"></i><b></b></div>
      <div class="mine" data-majority="{{ __('ui.aion.majority') }}" data-minority="{{ __('ui.aion.minority') }}"></div>
    @else
      <div class="unknown" title="{{ __('ui.aion.balance_unknown_hint') }}">{{ __('ui.aion.balance_unknown') }}</div>
    @endif
  </div>
  <div class="on">
    @if ($server->is_online)
      <small><i class="dot" aria-hidden="true"></i>{{ __('ui.aion.online') }}</small><b class="num" data-online>{{ $fmt($server->players) }}</b>
    @else
      <small class="off"><i class="dot off" aria-hidden="true"></i>{{ __('ui.aion.offline') }}</small>
    @endif
    <span class="votes"><b class="num" data-server-votes="{{ $server->id }}">{{ $fmt($server->votes_month) }}</b> {{ trans_choice('ui.server.votes', $server->votes_month) }}</span>
    <a class="btn sm" href="{{ route('servers.vote', $server) }}">{{ __('ui.aion.vote') }}</a>
  </div>
</article>
