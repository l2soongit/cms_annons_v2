@php
    use App\Support\WowFactions;

    // Рядок як у L2: герб, назва й теґи, шкала онлайну з балансом фракцій, голоси; другим рядком — set realmlist
    $crests = [['#3f80e8'], ['#e0453a'], ['#ffb547'], ['#8f7bff'], ['#4fd18b'], ['#2fb4d8']];
    $c = $crests[crc32($server->name) % count($crests)][0];
    $logo = $server->media->firstWhere('type', 'logo');
    $segments = $server->is_online ? max(1, (int) round($server->fillRatio() * 12)) : 0;
    $version = $server->fieldOption('expansion');
    $rates = $server->fieldOption('rates');
    $type = $server->fieldOption('type');
    $donate = $server->fieldOption('donate');
    // Донат: перший варіант поля — «без донату», останній — «впливає на гру» (порядок задає адмін)
    $donateField = $donate ? $server->fieldValues->first(fn ($v) => $v->field?->key === 'donate')?->field : null;
    $donateValues = $donateField ? array_column($donateField->options, 'value') : [];
    $donateLevel = $donate ? match (array_search($donate['value'], $donateValues, true)) { 0 => 'free', count($donateValues) - 1 => 'p2w', default => '' } : '';
    $alli = WowFactions::share($server);
    $realmlist = WowFactions::realmlist($server);
    $rankClass = $vip ? 'vipstar' : ($rank <= 3 ? 'm'.$rank : '');
    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
@endphp
<article @class(['srv', 'vip' => $vip, 'hl' => ! $vip && $server->isHighlighted()]) data-name="{{ $server->id }}" @if ($alli !== null) data-alli="{{ $alli }}" @endif>
  <span class="rank {{ $rankClass }}">{{ $vip ? '★' : $rank }}</span>
  <span class="crest" style="--c:{{ $c }}" aria-hidden="true">@if ($logo)<img src="{{ \App\Support\HomePageData::url($logo->path) }}" alt="" width="52" height="52" loading="lazy">@else{{ mb_substr($server->name, 0, 1) }}@endif</span>
  <div class="sname">
    <div class="top"><i class="dot {{ $server->is_online ? '' : 'off' }}" role="img" aria-label="{{ $server->is_online ? __('ui.wow.online') : __('ui.wow.offline') }}"></i><a href="{{ route('servers.show', $server) }}"><b>{{ $server->name }}</b></a>
      @if ($vip)<span class="badge vip">VIP</span>@endif
      @if ($server->created_at?->gt(now()->subDays(7)))<span class="badge new">{{ __('ui.game.badge_new') }}</span>@endif
    </div>
    <div class="tags">
      @if ($version)<span class="tag ver">{{ $version['label'] }}</span>@endif
      @if ($rates)<span class="tag rate" style="--rc:{{ WowFactions::rateColor($rates['label']) }}">{{ $rates['label'] }}</span>@endif
      @if ($type)<span class="tag">{{ $type['label'] }}</span>@endif
      @if ($donate)<span class="tag {{ $donateLevel }}">{{ $donate['label'] }}</span>@endif
    </div>
  </div>
  <div class="online"><div class="meter" aria-hidden="true">@for ($k = 0; $k < 12; $k++)<i class="{{ $k < $segments ? 'on' : '' }}"></i>@endfor</div>
    @if ($server->is_online)<b class="num" data-online>{{ $fmt($server->players) }}</b> / {{ $fmt($server->max_players) }}@else{{ __('ui.wow.offline') }}@endif
    @if ($alli !== null)
      <div class="fac" title="{{ __('ui.wow.alli') }} {{ $alli }}% · {{ __('ui.wow.horde') }} {{ 100 - $alli }}%"><span class="a num">{{ $alli }}%</span><span class="t"><i style="width:{{ $alli }}%"></i><b></b></span><span class="h num">{{ 100 - $alli }}%</span>
        <span class="mine" data-majority="{{ __('ui.wow.majority') }}" data-minority="{{ __('ui.wow.minority') }}"></span></div>
    @else
      <div class="fac none" title="{{ __('ui.wow.balance_unknown_hint') }}">{{ __('ui.wow.balance_unknown') }}</div>
    @endif
  </div>
  <div class="votes"><b class="num" data-server-votes="{{ $server->id }}">{{ $fmt($server->votes_month) }}</b><small>{{ trans_choice('ui.server.votes', $server->votes_month) }}</small></div>
  <a class="btn ghost sm" href="{{ route('servers.vote', $server) }}">{{ __('ui.wow.vote') }}</a>
  <div class="realmlist"><code>{{ $realmlist }}</code><button type="button" class="copy" data-ip="{{ $realmlist }}">{{ __('ui.wow.copy') }}</button></div>
</article>
