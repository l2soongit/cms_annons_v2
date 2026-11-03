<x-layouts.home :title="__('community.tabs.forum').' — '.__('community.nav').' | graj.club'" :description="__('community.lead')" :assets="['resources/css/home.css', 'resources/css/community.css', 'resources/js/community.js']">
<div class="wrap cm">
  @include('community.partials.top')

  <div class="cm-layout">
    @include('community.partials.nav')

    <section aria-labelledby="cm-forum-h">
      <div class="cm-cathead"><h2 class="cm-h2" id="cm-forum-h">{{ __('community.tabs.forum') }}</h2>
        @auth<a class="btn btn-hot btn-sm" href="{{ route('community.create') }}">+ {{ __('community.compose.new_topic') }}</a>@endauth</div>
      <div class="cm-card cm-forum">
        @foreach (['site', 'games', 'other'] as $group)
          @continue(! isset($groups[$group]))
          <div class="cm-grp">{{ __('community.groups.'.$group) }}</div>
          @foreach ($groups[$group] as $cat)
            @php
                $lastTopic = $last[$cat->id] ?? null;
                $gicon = $cat->game ? (\App\Support\HomePageData::url($cat->game->icon) ?? \App\Support\GameNav::builtinLogo($cat->game)) : null;
            @endphp
            <div class="cm-row">
              <div class="ic">@if ($gicon)<img src="{{ $gicon }}" alt="">@else<span aria-hidden="true">{{ $cat->icon ?: '💬' }}</span>@endif</div>
              <div>
                <h3><a href="{{ route('community.category', $cat) }}">{{ $cat->name }}</a>@if ($lastTopic && $lastTopic->last_post_at?->gt(now()->subDay()))<span class="new" title="{{ __('community.fresh') }}"></span>@endif</h3>
                @if ($cat->description)<div class="sub">{{ $cat->description }}</div>@endif
                @if ($cat->children->isNotEmpty())<div class="subs">@foreach ($cat->children->where('is_active', true) as $child)<a href="{{ route('community.category', $child) }}">{{ $child->name }}</a>@endforeach</div>@endif
              </div>
              <div class="n"><b class="num">{{ number_format($cat->topics_count, 0, ',', ' ') }}</b><small>{{ __('community.stats.topics') }}</small></div>
              <div class="n"><b class="num">{{ number_format($cat->posts_count, 0, ',', ' ') }}</b><small>{{ __('community.stats.posts') }}</small></div>
              <div class="last">
                @if ($lastTopic)
                  @include('community.partials.ava', ['user' => $lastTopic->lastPostUser, 'size' => 's'])
                  <div><a href="{{ route('community.topic', $lastTopic) }}">{{ $lastTopic->title }}</a><span class="cm-meta">{{ $lastTopic->lastPostUser?->nickname ?: $lastTopic->lastPostUser?->name }} · {{ $lastTopic->last_post_at?->diffForHumans() }}</span></div>
                @else<span class="cm-meta">{{ __('community.no_topics') }}</span>@endif
              </div>
            </div>
          @endforeach
        @endforeach
      </div>
    </section>

    @include('community.partials.aside')
  </div>
</div>
@include('community.partials.mobilebar')
</x-layouts.home>
