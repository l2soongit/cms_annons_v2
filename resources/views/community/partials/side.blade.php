{{-- Бічна колонка форуму: бали, власникам / гостям, реклама, хто онлайн, останні повідомлення, статистика --}}
<aside class="f-side">
  @if ($level)
    @php $u = auth()->user(); $span = $level['next'] ? max(1, $level['next'] - $level['min']) : 1; $pct = $level['next'] ? min(100, (int) round(($u->bonus_earned - $level['min']) / $span * 100)) : 100; @endphp
    <section class="f-blk"><div class="f-bh"><h2>✨ {{ __('community.bonus.title') }}</h2></div><div class="f-bb">
      <div class="f-pts"><span>{{ __('community.levels.'.$level['key']) }}</span><b class="num">{{ number_format($u->bonus_balance, 0, ',', ' ') }}</b></div>
      <div class="f-bar" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><i style="width: {{ $pct }}%"></i></div>
      <div class="meta">@if ($level['next']){{ __('community.bonus.to_next', ['n' => $level['next'] - $u->bonus_earned]) }}@else{{ __('community.bonus.max_level') }}@endif · <a class="f-link" href="{{ route('cabinet.bonus') }}">{{ __('community.forum.shop') }} →</a></div>
    </div></section>
  @endif

  @if ($isOwner)
    <section class="f-blk f-owner"><div class="f-bb"><h2 class="cm-h2" style="font-size:16px;margin-bottom:8px">{{ __('community.owner.title') }}</h2><p>{{ __('community.owner.text') }}</p>
      <a class="f-btn hot sm" href="{{ route('community.create', ['type' => 'server_news']) }}">{{ __('community.owner.button') }}</a></div></section>
  @elseif (! auth()->check())
    <section class="f-blk f-owner"><div class="f-bb"><h2 class="cm-h2" style="font-size:16px;margin-bottom:8px">{{ __('community.guest.title') }}</h2><p>{{ __('community.guest.text') }}</p>
      <a class="f-btn hot sm" href="{{ route('register') }}">{{ __('community.guest.button') }}</a></div></section>
  @endif

  <x-ad-banner placement="forum_side" gap="0" :promo="true" promo-key="community.forum.ad_free" />

  @if ($online->isNotEmpty())
    <section class="f-blk"><div class="f-bh"><h2>{{ __('community.forum.online') }}</h2></div><div class="f-bb">
      <div class="f-online">@foreach ($online as $u)<span @class(['staff' => in_array($u->id, $onlineStaff, true)])>{{ $u->nickname ?: $u->name }}@if (! $loop->last),@endif</span>@endforeach</div>
      <div class="f-tot">{{ __('community.forum.online_total', ['n' => $online->count()]) }}</div>
    </div></section>
  @endif

  @if ($latestTopics->isNotEmpty())
    <section class="f-blk"><div class="f-bh"><h2>{{ __('community.forum.latest_posts') }}</h2></div><div class="f-bb"><div class="f-latest">
      @foreach ($latestTopics as $item)
        @php $lu = $item->lastPostUser; @endphp
        <a href="{{ route('community.topic', $item) }}">@include('community.partials.ava', ['user' => $lu, 'size' => 's'])<div><b>{{ $item->title }}</b><span class="meta">{{ $lu?->nickname ?: $lu?->name }} · {{ $item->last_post_at?->diffForHumans(short: true) }} · {{ $item->category->game?->shortName() ?? $item->category->name }}</span></div></a>
      @endforeach
    </div></div></section>
  @endif

  <section class="f-blk"><div class="f-bh"><h2>{{ __('community.forum.stats') }}</h2></div><div class="f-bb"><div class="f-dl">
    <div>{{ __('community.forum.topics') }}<b class="num">{{ number_format($stats['topics'], 0, ',', ' ') }}</b></div>
    <div>{{ __('community.forum.replies') }}<b class="num">{{ number_format($stats['posts'], 0, ',', ' ') }}</b></div>
    <div>{{ __('community.forum.members') }}<b class="num">{{ number_format($stats['members'], 0, ',', ' ') }}</b></div>
    @if ($stats['newest'])<div>{{ __('community.forum.newest') }}<b>{{ $stats['newest']->nickname ?: $stats['newest']->name }}</b></div>@endif
  </div></div></section>
</aside>
