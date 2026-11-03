{{-- Номери сторінок: перша, остання й сусідні; на телефоні лишаються стрілки й поточна --}}
@if ($p->hasPages())
  @php $cur = $p->currentPage(); $lastPage = $p->lastPage(); $frag = $fragment ?? null; $url = fn ($n) => $p->url($n).($frag ? '#'.$frag : ''); @endphp
  <nav class="f-pages" aria-label="{{ __('community.forum.pages') }}">
    @if ($cur > 1)<a href="{{ $url($cur - 1) }}" rel="prev" aria-label="←">←</a>@endif
    @foreach (range(1, $lastPage) as $n)
      @if ($n === 1 || $n === $lastPage || abs($n - $cur) <= 1)
        @if ($n === $cur)<span aria-current="page">{{ $n }}</span>@else<a href="{{ $url($n) }}">{{ $n }}</a>@endif
      @elseif ($n === 2 || $n === $lastPage - 1)
        <span class="gap">…</span>
      @endif
    @endforeach
    @if ($cur < $lastPage)<a href="{{ $url($cur + 1) }}" rel="next" aria-label="→">→</a>@endif
  </nav>
@endif
