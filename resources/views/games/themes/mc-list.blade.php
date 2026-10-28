{{-- Рядки меню «Мережева гра»: віддаються і всередині сторінки, і окремо для оновлення без перезавантаження --}}
<div id="list" data-page="{{ $servers->currentPage() }}">
  @if ($vip->isNotEmpty())
    @foreach ($vip as $server)
      @include('games.themes.mc-row', ['server' => $server, 'rank' => 0, 'vip' => true])
    @endforeach
    <div class="vip-sep" aria-hidden="true"></div>
  @endif
  @forelse ($servers as $server)
    @include('games.themes.mc-row', ['server' => $server, 'rank' => $server->rank ?? $servers->firstItem() + $loop->index, 'vip' => false])
  @empty
    <div class="empty">{{ $game->pageText('mc.empty') }}<br>{{ $game->pageText('mc.empty_hint') }}</div>
  @endforelse
</div>

@if ($servers->hasMorePages())
  <div class="more"><a class="btn btn-sm" id="more" href="{{ $servers->nextPageUrl() }}">{{ __('ui.game.more') }}</a></div>
@endif
