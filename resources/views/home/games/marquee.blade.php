{{-- «Обери свою гру» · Живі стрічки: два нескінченні ряди карток пливуть назустріч, наведення зупиняє --}}
@use('App\Support\GamesSlider', 'GS')
@include('home.games._parts')
@php
    $byOnline = collect($games)->sortByDesc('online')->values();
    $half = (int) ceil($byOnline->count() / 2);
    // Другий ряд — інший порядок, щоб ряди не дублювали одне одного
    $rows = [$byOnline->all(), $byOnline->slice($half)->concat($byOnline->take($half))->reverse()->values()->all()];
@endphp
<div class="mq" data-mq>
  @foreach ($rows as $r => $row)
    <div @class(['mq-row', 'is-rev' => $r === 1]) style="--d:{{ max(30, count($row) * 6) + $r * 10 }}s">
      @foreach ([0, 1] as $copy)
        @foreach ($row as $g)
          <a class="mq-card gs-focus" href="{{ $g['url'] }}" style="{{ GS::vars($g) }}" @if ($copy || $r) tabindex="-1" aria-hidden="true" @endif>
            {{ GS::logo($g) }}
            <span class="mq-txt"><b>{{ $g['name'] }}</b>{{ GS::stats($g) }}</span>
            <span class="mq-arrow" aria-hidden="true">→</span>
          </a>
        @endforeach
      @endforeach
    </div>
  @endforeach
</div>
<p class="mq-foot">{{ __('ui.home.gs.hover_stop') }}</p>
<style>
  .mq { display:grid; gap:16px; overflow:hidden; margin:0 -16px; padding:6px 0; -webkit-mask:linear-gradient(90deg,transparent,#000 6%,#000 94%,transparent); mask:linear-gradient(90deg,transparent,#000 6%,#000 94%,transparent); }
  .mq-row { display:flex; gap:16px; width:max-content; animation:mqMove var(--d,50s) linear infinite; }
  .mq-row.is-rev { animation-direction:reverse; }
  .mq:hover .mq-row, .mq:focus-within .mq-row { animation-play-state:paused; }
  @keyframes mqMove { to { transform:translateX(calc(-50% - 8px)); } }
  .mq-card { width:300px; flex:none; display:flex; gap:14px; align-items:center; padding:16px; border-radius:20px; border:1px solid color-mix(in srgb,var(--c1) 25%,transparent);
    background:linear-gradient(120deg,color-mix(in srgb,var(--c2) 60%,#111114),#111114 70%); transition:transform .3s, border-color .3s, box-shadow .3s; }
  .mq-card:hover { transform:translateY(-4px) scale(1.02); border-color:var(--c1); box-shadow:0 20px 40px -18px var(--c1); }
  .mq-txt { display:grid; gap:4px; min-width:0; }
  .mq-txt > b { font:800 17px/1.15 var(--display); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .mq-txt .gs-stats { font-size:12px; gap:2px 10px; flex-wrap:nowrap; white-space:nowrap; }
  .mq-arrow { margin-left:auto; color:var(--c1); opacity:0; transform:translateX(-6px); transition:.25s; font-weight:800; }
  .mq-card:hover .mq-arrow { opacity:1; transform:none; }
  .mq-foot { text-align:center; margin:22px 0 0; color:var(--faint); font-size:13px; }
  @media (prefers-reduced-motion:reduce) { .mq { overflow-x:auto; } .mq-row { animation:none; } }
</style>
