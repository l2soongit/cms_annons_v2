{{-- Головна форуму (демо design/community/community-v2-d.html): новини, блоки розділів, бічна колонка, рекламні місця --}}
@php use App\Support\HomePageData; @endphp
<x-layouts.home :title="__('community.forum.title').' — '.__('community.meta_title').' | graj.club'" :description="__('community.forum.lead')" :assets="['resources/css/home.css', 'resources/css/community.css', 'resources/css/forum.css', 'resources/js/community.js']">
@include('community.partials.skin')
<div class="wrap cm f">
  @include('community.partials.sub')
  <nav class="f-crumbs" aria-label="{{ __('ui.breadcrumbs') }}"><a href="{{ route('home') }}">graj.club</a> › <span>{{ __('community.forum.title') }}</span></nav>
  <div class="f-title">
    <div><h1>{{ __('community.forum.title') }}</h1><p>{{ __('community.forum.lead') }}</p></div>
    <div class="f-acts"><a class="f-btn hot" href="{{ auth()->check() ? route('community.create') : route('login') }}">+ {{ __('community.forum.create') }}</a></div>
  </div>

  @if (session('status'))<div class="cm-flash" role="status" style="margin:0 0 16px">{{ session('status') }}</div>@endif

  <x-ad-banner placement="forum_top" gap="0 auto 18px" />

  <div class="f-layout">
    <div>
      @if ($news->isNotEmpty())
        <section class="f-feat" aria-label="{{ __('community.tabs.news') }}">
          @foreach ($news as $item)
            @php $img = $item->cover ? HomePageData::url($item->cover) : HomePageData::url($item->category->game?->cover); @endphp
            <a @class(['f-fc', 'big' => $loop->first]) href="{{ route('community.topic', $item) }}">
              @if ($img)<img src="{{ $img }}" alt="" loading="lazy">@endif
              @include('community.partials.pfx', ['topic' => $item])
              <h3>{{ $item->title }}</h3>
              <span class="meta">{{ $item->user->nickname ?: $item->user->name }} · {{ $item->published_at?->diffForHumans(short: true) }} · 💬 {{ $item->posts_count }}</span>
            </a>
          @endforeach
        </section>
      @endif

      @foreach (['site', 'games', 'other'] as $group)
        @continue(! isset($groups[$group]))
        @if ($group === 'other')<x-ad-banner placement="forum_list" gap="0 auto 18px" />@endif
        <section class="f-blk">
          <div class="f-bh"><h2>{{ __('community.groups.'.$group) }}</h2>@if ($group === 'games')<small>{{ trans_choice('community.forum.games_count', $groups[$group]->count(), ['n' => $groups[$group]->count()]) }}</small>@endif</div>
          @foreach ($groups[$group] as $cat)
            @include('community.partials.node', ['cat' => $cat, 'info' => $nodes[$cat->id] ?? []])
          @endforeach
        </section>
      @endforeach
    </div>

    @include('community.partials.side')
  </div>
</div>
@include('community.partials.mobilebar')
</x-layouts.home>
