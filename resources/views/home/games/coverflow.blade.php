{{-- «Обери свою гру» · Coverflow 3D: активна гра по центру, сусідні повернуті в перспективі; стрілки, свайп, клавіші, автогортання --}}
@use('App\Support\GamesSlider', 'GS')
@include('home.games._parts')
<div class="cf" data-cf role="region" aria-roledescription="carousel" aria-label="{{ __('ui.home.games_title') }}">
  <div class="cf-stage">
    @foreach ($games as $i => $g)
      <a class="cf-card gs-focus" href="{{ $g['url'] }}" style="{{ GS::vars($g) }}" data-i="{{ $i }}">
        <span class="cf-big" aria-hidden="true">{{ $g['short'] }}</span>
        {{ GS::logo($g, 'gs-logo cf-logo') }}
        @if ($g['eyebrow'])<span class="gs-tag">{{ $g['eyebrow'] }}</span>@endif
        <h3>{{ $g['name'] }}</h3>
        {{ GS::stats($g) }}
        <span class="gs-go cf-go">{{ __('ui.home.gs.open') }} →</span>
      </a>
    @endforeach
  </div>
  <div class="cf-ctrl">
    <button class="gs-btn" type="button" data-cf-step="-1" aria-label="{{ __('ui.home.slider_prev') }}"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>
    <span class="cf-count" aria-live="polite"></span>
    <button class="gs-btn" type="button" data-cf-step="1" aria-label="{{ __('ui.home.slider_next') }}"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button>
  </div>
</div>
<style>
  .cf-stage { position:relative; height:440px; perspective:1400px; user-select:none; touch-action:pan-y; }
  .cf-card { position:absolute; left:50%; top:20px; width:300px; height:380px; margin-left:-150px; border-radius:24px; overflow:hidden; display:flex; flex-direction:column; justify-content:flex-end; gap:8px; padding:24px;
    background:radial-gradient(120% 70% at 50% 0%,color-mix(in srgb,var(--c1) 55%,transparent),transparent 60%),linear-gradient(170deg,color-mix(in srgb,var(--c2) 80%,#09090b),#0e0e12 75%);
    border:1px solid color-mix(in srgb,var(--c1) 30%,transparent); transition:transform .6s cubic-bezier(.2,.8,.2,1), opacity .6s, filter .6s; }
  .cf-card .cf-logo { position:absolute; top:24px; left:24px; width:64px; height:64px; }
  .cf-card .gs-tag { align-self:flex-start; }
  .cf-card h3 { margin:0; font:800 26px/1.1 var(--display); }
  .cf-big { position:absolute; right:-20px; top:40px; font:800 120px/1 var(--display); color:color-mix(in srgb,var(--c1) 14%,transparent); letter-spacing:-.06em; pointer-events:none; }
  .cf-card.is-active { box-shadow:0 40px 80px -30px var(--c1), 0 0 0 1px var(--c1); }
  .cf-go { margin-top:8px; align-self:flex-start; opacity:0; transform:translateY(8px); transition:.4s .1s; }
  .cf-card.is-active .cf-go { opacity:1; transform:none; }
  .cf-ctrl { display:flex; justify-content:center; align-items:center; gap:18px; margin-top:12px; }
  .cf-count { font:700 14px var(--display); color:var(--muted); min-width:70px; text-align:center; }
  .cf-count b { color:var(--text); }
  @media (max-width:640px) { .cf-stage { height:380px; } .cf-card { width:250px; height:330px; margin-left:-125px; } }
</style>
<script>
(() => {
  const root = document.querySelector('[data-cf]');
  if (!root) return;
  const stage = root.querySelector('.cf-stage'), cards = [...stage.children], count = root.querySelector('.cf-count');
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  let cur = 0, timer;
  const step = () => (innerWidth < 640 ? 150 : 210);
  const render = () => {
    const n = cards.length;
    cards.forEach((c, i) => {
      let d = i - cur; if (d > n / 2) d -= n; if (d < -n / 2) d += n;
      const a = Math.abs(d);
      c.style.transform = `translateX(${d * step()}px) translateZ(${-a * 160}px) rotateY(${d * -38}deg) scale(${a ? .92 : 1})`;
      c.style.opacity = a > 3 ? 0 : 1 - a * .18;
      c.style.zIndex = 10 - a;
      c.style.filter = a ? `brightness(${1 - a * .18})` : 'none';
      c.style.pointerEvents = a > 3 ? 'none' : 'auto';
      c.tabIndex = a ? -1 : 0;
      c.classList.toggle('is-active', a === 0);
      c.setAttribute('aria-hidden', a ? 'true' : 'false');
    });
    count.innerHTML = `<b>${String(cur + 1).padStart(2, '0')}</b> / ${String(n).padStart(2, '0')}`;
  };
  const go = (s) => { cur = (cur + s + cards.length) % cards.length; render(); };
  const auto = () => { clearInterval(timer); if (!reduce) timer = setInterval(() => go(1), 4500); };
  // Клік по боковій картці — вивести її в центр, по центральній — перейти
  cards.forEach((c, i) => c.addEventListener('click', (e) => { if (i !== cur) { e.preventDefault(); cur = i; render(); auto(); } }));
  root.querySelectorAll('[data-cf-step]').forEach((b) => b.addEventListener('click', () => { go(+b.dataset.cfStep); auto(); }));
  // Свайп: після змаху клік по картці не відкриває посилання
  let x0 = null, swiped = false;
  stage.addEventListener('pointerdown', (e) => { x0 = e.clientX; swiped = false; });
  stage.addEventListener('pointerup', (e) => { if (x0 !== null && Math.abs(e.clientX - x0) > 50) { swiped = true; go(e.clientX < x0 ? 1 : -1); auto(); } x0 = null; });
  stage.addEventListener('click', (e) => { if (swiped) { e.preventDefault(); e.stopPropagation(); swiped = false; } }, true);
  root.addEventListener('keydown', (e) => { if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') { e.preventDefault(); go(e.key === 'ArrowLeft' ? -1 : 1); auto(); root.querySelector('.cf-card.is-active').focus(); } });
  root.addEventListener('mouseenter', () => clearInterval(timer));
  root.addEventListener('mouseleave', auto);
  root.addEventListener('focusin', () => clearInterval(timer));
  addEventListener('resize', render);
  render(); auto();
})();
</script>
