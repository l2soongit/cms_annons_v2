{{-- Заголовок, вкладки й блок новин (головна + три менші) — однаковий для стрічки й розділів --}}
@php use App\Support\HomePageData; @endphp
<section class="cm-intro">
  <div>
    <h1>{!! __('community.heading', ['accent' => '<span class="grad">'.e(__('community.accent')).'</span>']) !!}</h1>
    <p>{{ __('community.lead') }}</p>
  </div>
  <nav class="cm-tabs" aria-label="{{ __('community.tabs.aria') }}">
    <a href="{{ route('community') }}" @if ($tab === 'feed' || $tab === 'category') aria-current="page" @endif>{{ __('community.tabs.feed') }}</a>
    <a href="{{ route('community.forum') }}" @if ($tab === 'forum') aria-current="page" @endif>{{ __('community.tabs.forum') }}</a>
    <a href="{{ route('community.news') }}" @if ($tab === 'news') aria-current="page" @endif>{{ __('community.tabs.news') }}</a>
    @auth<a href="{{ route('community.mine') }}" @if ($tab === 'mine') aria-current="page" @endif>{{ __('community.tabs.mine') }}</a>@endauth
  </nav>
</section>

@if ($featured)
  <section class="cm-newsgrid" aria-label="{{ __('community.tabs.news') }}">
    @php $cover = $featured->cover ? HomePageData::url($featured->cover) : HomePageData::url($featured->category->game?->cover); @endphp
    <a class="cm-lead" href="{{ route('community.topic', $featured) }}">
      @if ($cover)<img src="{{ $cover }}" alt="" loading="lazy">@endif
      <div class="cm-chips">@include('community.partials.type', ['topic' => $featured])@if ($featured->category->game)<span class="cm-chip">{{ $featured->category->game->shortName() }}</span>@endif</div>
      <h2>{{ $featured->title }}</h2>
      @if ($featured->excerpt)<p>{{ $featured->excerpt }}</p>@endif
      <div class="cm-foot">@include('community.partials.ava', ['user' => $featured->user, 'size' => 's'])<b>{{ $featured->user->nickname ?: $featured->user->name }}</b><span>{{ $featured->published_at?->diffForHumans() }}</span><span>💬 {{ $featured->posts_count }}</span><span>👁 {{ number_format($featured->views, 0, ',', ' ') }}</span></div>
    </a>
    <div class="cm-side-news">
      @foreach ($sideNews as $item)
        @php $img = $item->cover ? HomePageData::url($item->cover) : HomePageData::url($item->category->game?->cover); @endphp
        <a class="cm-mini" href="{{ route('community.topic', $item) }}">
          @if ($img)<img src="{{ $img }}" alt="" loading="lazy">@else<span class="cm-noimg" aria-hidden="true">📣</span>@endif
          <div>@include('community.partials.type', ['topic' => $item, 'withServer' => true])<h3>{{ $item->title }}</h3><div class="cm-meta">{{ $item->published_at?->diffForHumans() }} · 💬 {{ $item->posts_count }}</div></div>
        </a>
      @endforeach
      <a class="cm-more-news" href="{{ route('community.news') }}">{{ __('community.all_news') }} <span aria-hidden="true">→</span></a>
    </div>
  </section>
@endif
