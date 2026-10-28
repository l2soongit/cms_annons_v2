@php
    use App\Support\HomePageData;
    use App\Support\McPixels;
    use App\Support\Motd;

    $values = $server->fieldValues->keyBy(fn ($v) => $v->field?->key);
    $label = fn (string $key) => ($v = $values->get($key)) ? (collect($v->field?->options)->firstWhere('value', $v->value)['label'] ?? $v->value) : null;
    // Колір видання (Java, Bedrock…) — з варіанта поля в адмінці
    $edition = $server->fieldOption('edition');
    $tags = array_filter([$label('version'), $label('mode')]);
    $logo = $server->media->firstWhere('type', 'logo');
    $online = $server->is_online;
    $ping = $server->ping_ms;
    $level = ! $online ? 'off' : ($ping === null ? 'p3' : ($ping < 30 ? 'p5' : ($ping < 60 ? 'p4' : ($ping < 100 ? 'p3' : ($ping < 200 ? 'p2' : 'p1')))));
    $motd = Motd::html($server->motd);
    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
@endphp
<div @class(['entry', 'srv', 'vip' => $vip]) data-name="{{ $server->id }}">
  <span @class(['rank', 'r'.$rank => ! $vip && $rank <= 3])>{{ $vip ? '★' : $rank }}</span>
  @if ($logo)<img class="icon" src="{{ HomePageData::url($logo->path) }}" alt="" width="72" height="72" loading="lazy">@else{!! McPixels::icon($server->name) !!}@endif
  <div class="info">
    <div class="name"><a href="{{ route('servers.show', $server) }}">{{ $server->name }}</a></div>
    @if ($motd !== '')<div class="motd">{!! $motd !!}</div>@endif
    <div class="tags">
      @if ($edition)<span class="tag ed" @if (! empty($edition['color'])) style="--ed:{{ $edition['color'] }}" @endif>{{ $edition['label'] }}</span>@endif
      @foreach ($tags as $tag)<span class="tag">{{ $tag }}</span>@endforeach
      @if ($vip)<span class="tag vipt">VIP</span>@endif
      @if ($server->created_at?->gt(now()->subDays(7)))<span class="tag new">{{ __('ui.game.badge_new') }}</span>@endif
    </div>
  </div>
  <div class="side-stats">
    <span class="players">
      @if ($online)<span><b class="num" data-online>{{ $fmt($server->players) }}</b>/{{ $fmt($server->max_players) }}</span>@else<span class="off">{{ __('ui.mc.offline') }}</span>@endif
      <span class="ping {{ $level }}" title="{{ $online && $ping !== null ? __('ui.mc.ms', ['n' => $ping]) : __('ui.mc.no_ping') }}" role="img" aria-label="{{ $online && $ping !== null ? __('ui.mc.ms', ['n' => $ping]) : __('ui.mc.no_ping') }}"><i></i><i></i><i></i><i></i><i></i></span>
    </span>
    <span class="vt num"><span data-server-votes="{{ $server->id }}">{{ $fmt($server->votes_month) }}</span> {{ trans_choice('ui.server.votes', $server->votes_month) }}</span>
    <span class="acts">
      <a class="btn btn-green btn-sm" href="{{ route('servers.vote', $server) }}">{{ __('ui.mc.vote') }}</a>
      <button class="btn btn-sm copy" type="button" data-ip="{{ $server->connectCommand() }}">{{ __('ui.mc.copy') }}</button>
    </span>
  </div>
</div>
