{{-- Результати списку: віддаються і всередині сторінки, і окремо для оновлення без перезавантаження --}}
@if ($vip->isNotEmpty())
  <div class="vip-head" id="vip-head">★ {{ __('ui.game.vip_head') }}</div>
  <div class="list" id="vip">
    @foreach ($vip as $server)
      @include('games.themes.l2-row', ['server' => $server, 'rank' => 0, 'vip' => true])
    @endforeach
  </div>
@endif

<div class="result-line">
  <span>{!! str_replace(':count', '<b class="num" id="count">'.number_format($servers->total(), 0, ',', "\u{00A0}").'</b>', e(trans_choice('ui.game.found', $servers->total(), ['n' => ':count']))) !!}</span>
  @if ($active || $search !== '')
    <a class="reset" id="reset" href="{{ route('games.show', $game) }}" data-filter-link>{{ __('ui.game.reset') }}</a>
  @endif
</div>

<div class="list" id="list" data-page="{{ $servers->currentPage() }}">
  @forelse ($servers as $server)
    @include('games.themes.l2-row', ['server' => $server, 'rank' => $server->rank ?? $servers->firstItem() + $loop->index, 'vip' => false])
  @empty
    <div class="empty"><b>{{ __('ui.game.empty') }}</b>{{ __('ui.game.empty_hint') }}</div>
  @endforelse
</div>

@if ($servers->hasMorePages())
  <div class="more"><a class="btn btn-ghost" id="more" href="{{ $servers->nextPageUrl() }}">{{ __('ui.game.more') }}</a></div>
@endif
