{{-- Відлік до наступного голосу: плитки годин, хвилин і секунд без двокрапок --}}
@if ($nextVoteAt)
  <div class="next">
    <p>{{ __('serverpage.next') }}</p>
    <div class="vcd" id="vcd" role="timer" data-until="{{ $nextVoteAt->toIso8601String() }}" aria-label="{{ __('vote.next_at', ['time' => $nextVoteAt->locale(app()->getLocale())->diffForHumans()]) }}">
      @foreach (['h', 'm', 's'] as $i => $u)
        <div><div class="tile" data-u="{{ $u }}"><span class="val">00</span></div><span class="lbl">{{ $units[$i] }}</span></div>
      @endforeach
    </div>
  </div>
@endif
