{{-- Список реалмів: віддається і всередині сторінки, і окремо для оновлення без перезавантаження --}}
<div id="list" data-page="{{ $servers->currentPage() }}">
  @if ($vip->isNotEmpty())
    @foreach ($vip as $server)
      @include('games.themes.wow-row', ['server' => $server, 'rank' => 0, 'vip' => true])
    @endforeach
    <div class="vip-sep" aria-hidden="true"></div>
  @endif
  @forelse ($servers as $server)
    @include('games.themes.wow-row', ['server' => $server, 'rank' => $server->rank ?? $servers->firstItem() + $loop->index, 'vip' => false])
  @empty
    <div class="empty"><b>{{ $game->pageText('wow.empty') }}</b>{{ $game->pageText('wow.empty_hint') }}</div>
  @endforelse
</div>

@if ($servers->hasMorePages())
  <div class="more"><a class="btn sm ghost" id="more" href="{{ $servers->nextPageUrl() }}">{{ __('ui.game.more') }}</a></div>
@endif

@if ($active || $search !== '')
  <div class="list-foot"><a class="reset" id="reset" href="{{ route('games.show', $game) }}" data-filter-link>{{ __('ui.wow.reset') }}</a></div>
@endif
