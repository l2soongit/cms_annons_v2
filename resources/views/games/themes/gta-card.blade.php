@php
    // Кольори картки стабільні для назви сервера
    $palette = [['#ff4fa3', '#6d28d9'], ['#fb923c', '#9a3412'], ['#22d3ee', '#0e7490'], ['#a3e635', '#3f6212'], ['#c084fc', '#581c87'], ['#fde047', '#a16207'], ['#f472b6', '#831843'], ['#f87171', '#7f1d1d'], ['#e879f9', '#701a75'], ['#38bdf8', '#075985']];
    [$c1, $c2] = $palette[crc32($server->name) % count($palette)];
    $platforms = ['FiveM' => '#f97316', 'RAGE:MP' => '#38bdf8', 'alt:V' => '#22c55e', 'SA-MP' => '#eab308'];
    $labels = $server->fieldValues->sortBy(fn ($v) => $v->field?->sort)->values()
        ->map(fn ($v) => collect($v->field?->options)->firstWhere('value', $v->value)['label'] ?? $v->value);
    $platform = $labels[0] ?? null;
    $mode = $labels[1] ?? null;
    $banner = $server->media->firstWhere('type', 'banner');
    $fill = $server->is_online ? (int) round($server->fillRatio() * 100) : 0;
    // FiveM приймає команду в консолі F8, інші клієнти — адресу
    $connect = $platform === 'FiveM' ? 'connect '.$server->address() : $server->address();
    $lead = ! $vip && $rank === 1;
    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");

    // Силует міста на обкладинці, як у демо
    $d = 'M0 120';
    for ($x = 0; $x < 400;) {
        $w = 18 + ($x * 7 % 23);
        $h = 30 + ($x * 13 % 70);
        $d .= ' V'.(120 - $h).' H'.($x + $w);
        $x += $w;
    }
    $d .= ' V120 Z';
@endphp
<article @class(['srv', 'gcard', 'vip' => $vip, 'lead' => $lead]) data-name="{{ $server->id }}">
  @if ($banner)
    <div class="cover photo" style="background-image:url('{{ \App\Support\HomePageData::url($banner->path) }}')" aria-hidden="true"><span class="cstreak"></span></div>
  @else
    <div class="cover" style="--c1:{{ $c1 }};--c2:{{ $c2 }}" aria-hidden="true">
      <span class="csun"></span>
      <svg viewBox="0 0 400 120" preserveAspectRatio="none"><path d="{{ $d }}" fill="#0b0618"/>@for ($i = 0; $i < 26; $i++)<rect x="{{ ($i * 37) % 396 }}" y="{{ 60 + ($i * 23) % 52 }}" width="3" height="4" fill="{{ $c1 }}" opacity="{{ number_format(0.4 + ($i % 5) / 10, 1) }}" @if ($i % 4 === 0) class="win" style="animation-delay:-{{ $i % 3 }}s" @endif/>@endfor</svg>
      <span class="cstreak"></span>
    </div>
  @endif
  <div class="gbody">
    <div class="ghead">
      @if ($vip)<span class="sticker vipst">VIP</span>@else<span class="sticker r{{ min($rank, 4) }}">#{{ $rank }}</span>@endif
      <div class="gname">
        <h3><a href="{{ route('servers.show', $server) }}">{{ $server->name }}</a></h3>
        <div class="gmeta">
          @if ($platform)<span class="plat" style="--p:{{ $platforms[$platform] ?? 'var(--gc)' }}">{{ $platform }}</span>@endif
          @if ($mode)<span class="mode">{{ $mode }}</span>@endif
          @if ($server->created_at?->gt(now()->subDays(7)))<span class="badge new">{{ __('ui.game.badge_new') }}</span>@endif
          @if ($server->verified_at)<svg class="ver" width="16" height="16" viewBox="0 0 20 20" fill="currentColor" role="img" aria-label="{{ __('ui.server.verified') }}"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.86-9.86a.75.75 0 0 0-1.22-.88l-3.24 4.5-1.6-1.6a.75.75 0 1 0-1.06 1.06l2.22 2.22a.75.75 0 0 0 1.14-.09l3.76-5.21Z" clip-rule="evenodd"/></svg>@endif
        </div>
      </div>
      @if (! $vip && $rank <= 3)
        <span class="wanted" aria-label="{{ __('ui.game.wanted', ['n' => 6 - $rank]) }}">{{ str_repeat('★', 6 - $rank) }}<i>{{ str_repeat('★', $rank - 1) }}</i></span>
      @endif
    </div>
    <div class="gstats2">
      <div class="pop">
        @if ($server->is_online)
          <b class="num" data-online>{{ $fmt($server->players) }}</b><span>{{ __('ui.game.of_players', ['max' => $fmt($server->max_players)]) }}</span>
        @else
          <b class="off">{{ __('ui.server.offline') }}</b>
        @endif
        <div class="nbar" aria-hidden="true"><i style="width:{{ $fill }}%"></i></div>
      </div>
      <div class="gv"><b class="num" data-server-votes="{{ $server->id }}">{{ $fmt($server->votes_month) }}</b><span>{{ trans_choice('ui.server.votes', $server->votes_month) }}</span></div>
    </div>
    <div class="gact">
      <a class="btn btn-gold btn-sm" href="{{ route('servers.vote', $server) }}">{{ __('ui.server.vote') }}</a>
      <button class="btn btn-ghost btn-sm connect" type="button" data-connect="{{ $connect }}" data-copied="{{ $platform === 'FiveM' ? __('ui.game.copied_f8') : __('ui.game.copied') }}" @disabled(! $server->is_online)>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>{{ __('ui.game.connect') }}</button>
      <code class="addr" title="{{ $connect }}">{{ $connect }}</code>
    </div>
  </div>
</article>
