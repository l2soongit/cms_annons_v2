{{-- «Обери свою гру» · Колода карт: верхню змахують убік, під нею видно наступні; поруч — список ігор за онлайном --}}
@use('App\Support\GamesSlider', 'GS')
@include('home.games._parts')
@php($deck = collect($games)->sortByDesc('online')->values()->all())
<div class="dk-wrap" data-dk>
  <div class="dk" aria-live="polite">
    @foreach ($deck as $i => $g)
      <a class="dk-card gs-focus" href="{{ $g['url'] }}" style="{{ GS::vars($g) }}" draggable="false" @if ($i) tabindex="-1" aria-hidden="true" @endif>
        <span class="dk-rank">#{{ $i + 1 }}</span>
        <span class="dk-top">
          {{ GS::logo($g, 'gs-logo dk-logo') }}
          @if ($g['eyebrow'])<span><span class="gs-tag">{{ $g['eyebrow'] }}</span></span>@endif
          <h3>{{ $g['name'] }}</h3>
          @if ($g['text'])<span class="dk-text">{{ $g['text'] }}</span>@endif
        </span>
        <span class="dk-bottom">
          {{ GS::stats($g) }}
          @if ($g['leader'])<span class="dk-leader">{{ __('ui.home.gs.leader_month') }} <b>{{ $g['leader'] }}</b></span>@endif
        </span>
      </a>
    @endforeach
  </div>
  <div class="dk-side">
    <span class="label">{{ __('ui.home.gs.top_online') }}</span>
    <ol class="dk-list">
      @foreach ($deck as $i => $g)
        <li><button type="button" class="gs-focus" style="{{ GS::vars($g) }}" data-i="{{ $i }}" @if (! $i) aria-current="true" @endif>{{ GS::logo($g, 'dk-ico') }}<span class="dk-name">{{ $g['name'] }}</span><span class="dk-on">{{ number_format($g['online'], 0, ',', "\u{00A0}") }}</span></button></li>
      @endforeach
    </ol>
    <div class="dk-ctrl">
      <button class="gs-btn" type="button" data-dk-step="-1" aria-label="{{ __('ui.home.slider_prev') }}"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>
      <button class="gs-btn" type="button" data-dk-step="1" aria-label="{{ __('ui.home.slider_next') }}"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button>
      <span class="dk-hint">{{ __('ui.home.gs.swipe') }}</span>
    </div>
  </div>
</div>
<style>
  .dk-wrap { display:grid; grid-template-columns:1fr 1fr; gap:40px; align-items:center; }
  .dk { position:relative; height:460px; touch-action:pan-y; user-select:none; }
  .dk-card { position:absolute; inset:20px 40px; border-radius:26px; overflow:hidden; padding:28px; display:flex; flex-direction:column; justify-content:space-between; gap:16px;
    background:radial-gradient(100% 60% at 80% 0%,color-mix(in srgb,var(--c1) 50%,transparent),transparent 65%),linear-gradient(160deg,color-mix(in srgb,var(--c2) 85%,#09090b),#0d0d10 80%);
    border:1px solid color-mix(in srgb,var(--c1) 40%,transparent); box-shadow:0 30px 60px -30px #000; transition:transform .5s cubic-bezier(.2,.8,.2,1), opacity .5s; will-change:transform; cursor:grab; }
  .dk-card:active { cursor:grabbing; }
  .dk-top, .dk-bottom { display:grid; gap:10px; justify-items:start; }
  .dk-card h3 { margin:0; font:800 34px/1.05 var(--display); letter-spacing:-.02em; }
  .dk-text { color:#d4d4d8; }
  .dk-rank { position:absolute; right:24px; top:22px; font:800 14px var(--display); color:var(--c1); }
  .dk-logo { width:70px; height:70px; border-radius:18px; }
  .dk-leader { padding:10px 14px; border-radius:14px; background:#09090b80; font-size:13px; color:var(--muted); }
  .dk-leader b { color:var(--text); }
  .dk-list { list-style:none; margin:18px 0 0; padding:0; display:grid; gap:4px; }
  .dk-list button { width:100%; display:flex; align-items:center; gap:12px; padding:8px 12px; border-radius:12px; border:1px solid transparent; background:none; color:var(--muted); font:700 14px var(--font); cursor:pointer; text-align:left; }
  .dk-ico { width:28px; height:28px; border-radius:8px; object-fit:cover; font-size:11px; }
  .dk-list button:hover { color:var(--text); background:#ffffff08; }
  .dk-list button[aria-current=true] { color:var(--text); border-color:color-mix(in srgb,var(--c1) 45%,transparent); background:color-mix(in srgb,var(--c1) 10%,transparent); }
  .dk-on { margin-left:auto; font-size:12px; color:var(--faint); font-variant-numeric:tabular-nums; }
  .dk-ctrl { display:flex; gap:12px; margin-top:18px; align-items:center; }
  .dk-hint { color:var(--faint); font-size:13px; }
  @media (max-width:860px) { .dk-wrap { grid-template-columns:1fr; gap:16px; } .dk { height:440px; } .dk-card { inset:16px 8px; } .dk-list { display:none; } }
</style>
<script>
(() => {
  const root = document.querySelector('[data-dk]');
  if (!root) return;
  const deck = root.querySelector('.dk'), cards = [...deck.children], btns = [...root.querySelectorAll('.dk-list button')];
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  let cur = 0;
  const render = (drag = 0) => {
    const n = cards.length;
    cards.forEach((c, i) => {
      const d = (i - cur + n) % n;
      c.style.transition = d === 0 && drag ? 'none' : '';
      if (d === 0) { c.style.transform = `translateX(${drag}px) rotate(${drag / 18}deg)`; c.style.opacity = 1; c.style.zIndex = 20; }
      else if (d < 4) { c.style.transform = `translateY(${d * 14}px) scale(${1 - d * .05}) rotate(${(d % 2 ? 1 : -1) * d * 2.2}deg)`; c.style.opacity = 1 - d * .2; c.style.zIndex = 20 - d; }
      else { c.style.transform = 'translateY(56px) scale(.8)'; c.style.opacity = 0; c.style.zIndex = 0; }
      c.tabIndex = d ? -1 : 0;
      c.setAttribute('aria-hidden', d ? 'true' : 'false');
    });
    btns.forEach((b, i) => b.setAttribute('aria-current', i === cur ? 'true' : 'false'));
  };
  const go = (s) => {
    const top = cards[cur];
    if (s > 0 && !reduce) {
      top.style.transform = 'translateX(-130%) rotate(-18deg)'; top.style.opacity = 0;
      setTimeout(() => { cur = (cur + 1) % cards.length; render(); }, 220);
    } else { cur = (cur + s + cards.length) % cards.length; render(); }
  };
  // Змах мишею чи пальцем; після змаху клік не відкриває посилання
  let x0 = null, moved = false;
  deck.addEventListener('pointerdown', (e) => { x0 = e.clientX; moved = false; deck.setPointerCapture(e.pointerId); });
  deck.addEventListener('pointermove', (e) => { if (x0 === null) return; const dx = e.clientX - x0; if (Math.abs(dx) > 6) moved = true; if (moved) render(dx); });
  deck.addEventListener('pointerup', (e) => { if (x0 === null) return; const dx = e.clientX - x0; x0 = null; if (Math.abs(dx) > 80) go(1); else render(); });
  deck.addEventListener('pointercancel', () => { x0 = null; render(); });
  deck.addEventListener('click', (e) => { if (moved) { e.preventDefault(); moved = false; } }, true);
  root.querySelectorAll('[data-dk-step]').forEach((b) => b.addEventListener('click', () => go(+b.dataset.dkStep)));
  btns.forEach((b, i) => b.addEventListener('click', () => { cur = i; render(); }));
  deck.addEventListener('keydown', (e) => { if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') { e.preventDefault(); go(e.key === 'ArrowLeft' ? -1 : 1); setTimeout(() => cards[cur].focus(), 240); } });
  render();
})();
</script>
