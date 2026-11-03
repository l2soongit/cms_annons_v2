{{-- Розділ форуму на головній: іконка (світиться, якщо є свіжі відповіді), назва, опис, підрозділи, лічильники, остання тема --}}
@php
    $lastTopic = $info['last'] ?? null;
    $fresh = $lastTopic && $lastTopic->last_post_at?->gt(now()->subDay());
    $short = fn (int $n) => $n >= 1000 ? rtrim(rtrim(number_format($n / 1000, 1, ",", ""), "0"), ",")."K" : (string) $n;
    $gicon = ! $cat->parent_id && $cat->game ? (\App\Support\HomePageData::url($cat->game->icon) ?? \App\Support\GameNav::builtinLogo($cat->game)) : null;
@endphp
<div @class(['f-node', 'fresh' => $fresh])>
  <span class="f-nic" aria-hidden="true">@if ($gicon)<img src="{{ $gicon }}" alt="">@else{{ $cat->icon ?: '💬' }}@endif</span>
  <div>
    <h3><a href="{{ route('community.category', $cat) }}">{{ $cat->name }}</a></h3>
    @if ($cat->description)<div class="f-desc">{{ $cat->description }}</div>@endif
    @if ($cat->children->isNotEmpty())
      <div class="f-subs">@foreach ($cat->children as $child)<a href="{{ route('community.category', $child) }}">{{ $child->name }}</a>@endforeach</div>
    @endif
  </div>
  <div class="f-nstats"><span><b class="num">{{ $short($info['topics'] ?? 0) }}</b>{{ __('community.forum.topics') }}</span><span><b class="num">{{ $short($info['posts'] ?? 0) }}</b>{{ __('community.forum.posts') }}</span></div>
  <div class="f-nlast">
    @if ($lastTopic)
      @php $lu = $lastTopic->lastPostUser; @endphp
      @include('community.partials.ava', ['user' => $lu, 'size' => 's'])
      <div><a href="{{ route('community.topic', $lastTopic) }}">{{ $lastTopic->title }}</a><div class="meta">{{ $lastTopic->last_post_at?->diffForHumans() }}@if ($lu) · {{ $lu->nickname ?: $lu->name }}@endif</div></div>
    @else
      <span></span><span class="meta">{{ __('community.no_topics') }}</span>
    @endif
  </div>
</div>
