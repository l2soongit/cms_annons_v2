@php
    use App\Support\WowFactions;

    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
    $version = $server->fieldOption('expansion');
    $rates = $server->fieldOption('rates');
    $type = $server->fieldOption('type');
    $content = $server->fieldOption('content');
    $donate = $server->fieldOption('donate');
    // Донат: перший варіант поля — «без донату», останній — «впливає на гру» (порядок задає адмін)
    $donateField = $donate ? $server->fieldValues->first(fn ($v) => $v->field?->key === 'donate')?->field : null;
    $donateValues = $donateField ? array_column($donateField->options, 'value') : [];
    $donateLevel = $donate ? match (array_search($donate['value'], $donateValues, true)) { 0 => 'free', count($donateValues) - 1 => 'p2w', default => '' } : '';
    $alli = WowFactions::share($server);
    $realmlist = WowFactions::realmlist($server);
@endphp
<article @class(['srv', 'vip' => $vip, 'hl' => ! $vip && $server->isHighlighted()]) data-name="{{ $server->id }}" @if ($alli !== null) style="--a:{{ $alli }}%" data-alli="{{ $alli }}" @endif>
  <div @class(['rank num', 'top' => ! $vip && $rank <= 3])>{{ $vip ? '★' : $rank }}</div>
  <div class="main">
    <h3><a href="{{ route('servers.show', $server) }}">{{ $server->name }}</a>
      @if ($vip)<span class="tag vip">VIP</span>@endif
      @if ($server->created_at?->gt(now()->subDays(30)))<span class="tag new">{{ __('ui.game.badge_new') }}</span>@endif
    </h3>
    <div class="meta">
      @if ($version)<span><b>{{ $version['label'] }}</b></span>@endif
      @if ($rates)<span class="rate" style="--rc:{{ WowFactions::rateColor($rates['label']) }}">{{ $rates['label'] }}</span>@endif
      @if ($type)<span>{{ $type['label'] }}</span>@endif
      @if ($content)<span>{{ $content['label'] }}</span>@endif
      @if ($donate)<span class="{{ $donateLevel }}">{{ $donate['label'] }}</span>@endif
      @if ($server->uptime > 0)<span>{{ __('ui.wow.uptime', ['n' => rtrim(rtrim(number_format((float) $server->uptime, 1, ',', ''), '0'), ',')]) }}</span>@endif
    </div>
    @if ($about = \Illuminate\Support\Str::limit(strip_tags((string) $server->description), 140))
      <p class="about">{{ $about }}</p>
    @endif
    <div class="realmlist"><code>{{ $realmlist }}</code><button type="button" class="copy" data-ip="{{ $realmlist }}">{{ __('ui.wow.copy') }}</button></div>
  </div>
  <div class="side2">
    <div class="pop">
      @if ($server->is_online)
        <span><i class="dot" aria-hidden="true"></i>{{ __('ui.wow.online') }}</span><b class="num" data-online>{{ $fmt($server->players) }}</b>
      @else
        <span><i class="dot off" aria-hidden="true"></i>{{ __('ui.wow.offline') }}</span>
      @endif
    </div>
    @if ($alli !== null)
      <div class="bal">
        <div class="lbl"><span class="a">{{ __('ui.wow.alli') }} {{ $alli }}%</span><span class="h">{{ __('ui.wow.horde') }} {{ 100 - $alli }}%</span></div>
        <div class="t"><i style="width:{{ $alli }}%"></i><b></b></div>
        <div class="mine" data-majority="{{ __('ui.wow.majority') }}" data-minority="{{ __('ui.wow.minority') }}"></div>
      </div>
    @else
      <div class="bal unknown" title="{{ __('ui.wow.balance_unknown_hint') }}">{{ __('ui.wow.balance_unknown') }}</div>
    @endif
    <div class="votes"><span><b class="num" data-server-votes="{{ $server->id }}">{{ $fmt($server->votes_month) }}</b> {{ trans_choice('ui.server.votes', $server->votes_month) }}</span>
      <a class="btn sm" href="{{ route('servers.vote', $server) }}">{{ __('ui.wow.vote') }}</a></div>
  </div>
</article>
