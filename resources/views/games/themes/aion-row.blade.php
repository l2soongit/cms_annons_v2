@php
    use App\Support\WowFactions;

    // Рядок як у L2: герб, назва з теґами, шкала онлайну, голоси; під теґами — баланс рас
    $crests = [['#f3c969', '#7a5a14'], ['#c9b8ff', '#3b2a7a'], ['#93c5fd', '#1e3a8a'], ['#fca5a5', '#7f1d1d'], ['#86efac', '#14532d'], ['#a5f3fc', '#164e63']];
    [$c1, $c2] = $crests[crc32($server->name) % count($crests)];
    $logo = $server->media->firstWhere('type', 'logo');
    $segments = $server->is_online ? max(1, (int) round($server->fillRatio() * 12)) : 0;
    $version = $server->fieldOption('version');
    $rates = $server->fieldOption('rates');
    $type = $server->fieldOption('type');
    // Частка Елійців (колонка alliance) або null, якщо сервер не передає баланс рас
    $ely = WowFactions::share($server);
    $rankClass = $vip ? 'vipstar' : ($rank <= 3 ? 'm'.$rank : '');
    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
@endphp
<a @class(['srv', 'vip' => $vip, 'hl' => ! $vip && $server->isHighlighted()]) href="{{ route('servers.show', $server) }}" data-name="{{ $server->id }}" @if ($ely !== null) data-ely="{{ $ely }}" @endif>
  <span class="rank {{ $rankClass }}">{{ $vip ? '★' : $rank }}</span>
  <span class="crest" style="--c1:{{ $c1 }};--c2:{{ $c2 }}" aria-hidden="true">@if ($logo)<img src="{{ \App\Support\HomePageData::url($logo->path) }}" alt="" width="56" height="56" loading="lazy">@else{{ mb_substr($server->name, 0, 1) }}@endif</span>
  <div class="sname">
    <div class="top"><i class="dot {{ $server->is_online ? '' : 'off' }}" role="img" aria-label="{{ $server->is_online ? __('ui.server.online') : __('ui.server.offline') }}"></i><b>{{ $server->name }}</b>
      @if ($vip)<span class="badge vip">VIP</span>@endif
      @if ($server->created_at?->gt(now()->subDays(7)))<span class="badge new">{{ __('ui.game.badge_new') }}</span>@endif
      @if ($server->verified_at)<svg class="ver" width="16" height="16" viewBox="0 0 20 20" fill="currentColor" role="img" aria-label="{{ __('ui.server.verified') }}"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.86-9.86a.75.75 0 0 0-1.22-.88l-3.24 4.5-1.6-1.6a.75.75 0 1 0-1.06 1.06l2.22 2.22a.75.75 0 0 0 1.14-.09l3.76-5.21Z" clip-rule="evenodd"/></svg>@endif
    </div>
    <div class="tags">
      @if ($version)<span class="tag chron">{{ $version['label'] }}</span>@endif
      @if ($rates)<span class="tag rate">{{ $rates['label'] }}</span>@endif
      @if ($type)<span class="tag">{{ $type['label'] }}</span>@endif
    </div>
    @if ($ely !== null)
      <div class="race" title="{{ __('ui.aion.ely') }} {{ $ely }}% · {{ __('ui.aion.asmo') }} {{ 100 - $ely }}%">
        <span class="e num">{{ $ely }}%</span><span class="t"><i style="width:{{ $ely }}%"></i><b></b></span><span class="a num">{{ 100 - $ely }}%</span>
        <span class="mine" data-majority="{{ __('ui.aion.majority') }}" data-minority="{{ __('ui.aion.minority') }}"></span>
      </div>
    @endif
  </div>
  <div class="online"><div class="meter" aria-hidden="true">@for ($k = 0; $k < 12; $k++)<i class="{{ $k < $segments ? 'on' : '' }}"></i>@endfor</div>
    @if ($server->is_online)<b class="num" data-online>{{ $fmt($server->players) }}</b> / {{ $fmt($server->max_players) }}@else{{ __('ui.server.offline') }}@endif</div>
  <div class="votes"><b class="num" data-server-votes="{{ $server->id }}">{{ $fmt($server->votes_month) }}</b><small>{{ trans_choice('ui.server.votes', $server->votes_month) }}</small></div>
  <span class="btn ghost sm" data-vote="{{ route('servers.vote', $server) }}">{{ __('ui.server.vote') }}</span>
</a>
