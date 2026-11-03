{{-- Ліва колонка: стрічка, розділи, ігри --}}
@php $current = $category ?? null; @endphp
<nav class="cm-navcol" aria-label="{{ __('community.navcol.aria') }}">
  <a class="base" href="{{ route('community') }}" @if ($tab === 'feed') aria-current="page" @endif>🏠 {{ __('community.navcol.all') }}</a>
  <a class="base" href="{{ route('community.forum') }}" @if ($tab === 'forum') aria-current="page" @endif>🗂 {{ __('community.navcol.sections') }}</a>
  <a class="base" href="{{ route('community.news') }}" @if ($tab === 'news') aria-current="page" @endif>📣 {{ __('community.tabs.news') }}</a>
  @auth<a class="base" href="{{ route('community.mine') }}" @if ($tab === 'mine') aria-current="page" @endif>⭐ {{ __('community.tabs.mine') }}</a>@endauth
  @foreach (['games', 'site', 'other'] as $group)
    @php $items = $navCategories->where('group', $group); @endphp
    @if ($items->isNotEmpty())
      <h4>{{ __('community.groups.'.$group) }}</h4>
      @foreach ($items as $item)
        <a href="{{ route('community.category', $item) }}" @if ($current?->id === $item->id) aria-current="page" @endif>
          @php $gicon = $item->game ? (\App\Support\HomePageData::url($item->game->icon) ?? \App\Support\GameNav::builtinLogo($item->game)) : null; @endphp
          @if ($gicon)<img src="{{ $gicon }}" alt="">@else<span class="cm-ico" aria-hidden="true">{{ $item->icon ?: '💬' }}</span>@endif
          {{ $item->game?->shortName() ?? $item->name }}
          @if ($item->topics_count)<span class="cnt">{{ $item->topics_count }}</span>@endif
        </a>
      @endforeach
    @endif
  @endforeach
</nav>
