{{-- Тіло таблиці рахунку: віддається і всередині сторінки, і окремо для оновлення без перезавантаження --}}
<div id="list" data-page="{{ $servers->currentPage() }}">
  @if ($vip->isNotEmpty())
    <div class="vip-sep">★ VIP</div>
    @foreach ($vip as $server)
      @include('games.themes.cs-row', ['server' => $server, 'rank' => 0, 'vip' => true])
    @endforeach
    <div class="vip-sep" style="color:var(--muted);background:none;border-color:var(--line)">{{ __('ui.cs.ranking') }}</div>
  @endif
  @forelse ($servers as $server)
    @include('games.themes.cs-row', ['server' => $server, 'rank' => $server->rank ?? $servers->firstItem() + $loop->index, 'vip' => false])
  @empty
    <div class="empty"><b>{{ $game->pageText('cs.empty') }}</b>{{ $game->pageText('cs.empty_hint') }}</div>
  @endforelse
</div>

@if ($servers->hasMorePages())
  <div class="more"><a class="btn btn-line btn-sm" id="more" href="{{ $servers->nextPageUrl() }}">{{ __('ui.game.more') }}</a></div>
@endif

<div class="board-foot">
  <span>{!! str_replace(':count', '<b class="num" id="count">'.number_format($servers->total(), 0, ',', "\u{00A0}").'</b>', e(trans_choice('ui.game.found', $servers->total(), ['n' => ':count']))) !!}</span>
  @if ($active || $search !== '')
    <a class="reset" id="reset" href="{{ route('games.show', $game) }}" data-filter-link>{{ __('ui.game.reset') }}</a>
  @endif
</div>
