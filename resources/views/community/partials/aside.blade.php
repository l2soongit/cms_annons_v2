{{-- Права колонка: власникам, бали й рівень, гаряче, новини сайту, хто на форумі, лічильники --}}
<aside class="cm-aside">
  @if ($isOwner)
    <div class="cm-card cm-owner"><h3>{{ __('community.owner.title') }}</h3><p>{{ __('community.owner.text') }}</p>
      <a class="btn btn-hot btn-sm" href="{{ route('community.create', ['type' => 'server_news']) }}">{{ __('community.owner.button') }}</a></div>
  @elseif (! auth()->check())
    <div class="cm-card cm-owner"><h3>{{ __('community.guest.title') }}</h3><p>{{ __('community.guest.text') }}</p>
      <a class="btn btn-hot btn-sm" href="{{ route('register') }}">{{ __('community.guest.button') }}</a></div>
  @endif

  @if ($level)
    @php $u = auth()->user(); $span = $level['next'] ? max(1, $level['next'] - $level['min']) : 1; $pct = $level['next'] ? min(100, (int) round(($u->bonus_earned - $level['min']) / $span * 100)) : 100; @endphp
    <div class="cm-card"><h3>✨ {{ __('community.bonus.title') }}</h3>
      <div class="cm-level"><span>{{ __('community.levels.'.$level['key']) }}</span><b class="num">{{ number_format($u->bonus_balance, 0, ',', ' ') }} {{ __('community.bonus.points') }}</b></div>
      <div class="cm-progress" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><i style="width: {{ $pct }}%"></i></div>
      <p class="cm-meta">@if ($level['next']){{ __('community.bonus.to_next', ['n' => $level['next'] - $u->bonus_earned]) }}@else{{ __('community.bonus.max_level') }}@endif</p>
      <a class="cm-link" href="{{ route('cabinet.bonus') }}">{{ __('community.bonus.shop') }} →</a>
    </div>
  @endif

  @if ($hot->isNotEmpty())
    <div class="cm-card"><h3>🔥 {{ __('community.hot') }}</h3>
      <div class="cm-hot">
        @foreach ($hot as $item)
          <a href="{{ route('community.topic', $item) }}">{{ $item->title }}<span class="cm-meta">{{ $item->category->game?->shortName() ?? $item->category->name }} · {{ trans_choice('community.replies', $item->posts_count, ['n' => $item->posts_count]) }}</span></a>
        @endforeach
      </div></div>
  @endif

  @if ($sideNews->isNotEmpty() && ($tab ?? '') !== 'news')
    <div class="cm-card"><h3>📣 {{ __('community.site_news') }}</h3>
      <div class="cm-sn">
        @foreach ($sideNews as $item)
          <a href="{{ route('community.topic', $item) }}"><span class="cm-meta">{{ $item->published_at?->translatedFormat('j F') }}</span><b>{{ $item->title }}</b></a>
        @endforeach
      </div></div>
  @endif

  @if ($online->isNotEmpty())
    <div class="cm-card"><h3>{{ __('community.online') }}</h3>
      <div class="cm-online">@foreach ($online as $u)<span title="{{ $u->nickname ?: $u->name }}">@include('community.partials.ava', ['user' => $u, 'size' => 's'])</span>@endforeach</div></div>
  @endif

  <div class="cm-card"><div class="cm-stat">
    <div><b class="num">{{ number_format($stats['topics'], 0, ',', ' ') }}</b><small>{{ __('community.stats.topics') }}</small></div>
    <div><b class="num">{{ number_format($stats['posts'], 0, ',', ' ') }}</b><small>{{ __('community.stats.posts') }}</small></div>
    <div><b class="num">{{ number_format($stats['members'], 0, ',', ' ') }}</b><small>{{ __('community.stats.members') }}</small></div>
  </div></div>
</aside>
