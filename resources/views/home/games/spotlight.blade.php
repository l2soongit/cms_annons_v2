{{-- «Обери свою гру» · Прожектор: одна гра на весь блок у її кольорах, стрічка ігор унизу з прогресом автоперемикання --}}
@use('App\Support\GamesSlider', 'GS')
@include('home.games._parts')
<div class="sp" data-sp style="{{ GS::vars($games[0]) }}">
  <div class="sp-bg" aria-hidden="true"></div>
  <div class="sp-slides">
    @foreach ($games as $i => $g)
      <div class="sp-slide" id="sp-{{ $i }}" role="tabpanel" style="{{ GS::vars($g) }}" @if ($i) hidden @endif>
        <span class="sp-art" aria-hidden="true">{{ $g['short'] }}</span>
        <div class="sp-body">
          {{ GS::logo($g, 'gs-logo sp-logo') }}
          @if ($g['eyebrow'])<div><span class="gs-tag">{{ $g['eyebrow'] }}</span></div>@endif
          <h3>{{ $g['name'] }}</h3>
          @if ($g['text'])<p>{{ $g['text'] }}</p>@endif
          {{ GS::stats($g) }}
          @if ($g['leader'])<p class="sp-leader">{{ __('ui.home.gs.leader_month') }} <b>{{ $g['leader'] }}</b></p>@endif
          <div><a class="gs-go sp-cta" href="{{ $g['url'] }}">{{ __('ui.home.gs.see') }} →</a></div>
        </div>
      </div>
    @endforeach
  </div>
  <div class="sp-thumbs" role="tablist" aria-label="{{ __('ui.home.games_title') }}">
    @foreach ($games as $i => $g)
      <button class="sp-thumb gs-focus" type="button" role="tab" aria-controls="sp-{{ $i }}" aria-selected="{{ $i ? 'false' : 'true' }}" style="{{ GS::vars($g) }}" data-i="{{ $i }}">{{ GS::logo($g, 'sp-ico') }}{{ $g['short'] }}<i class="sp-bar"></i></button>
    @endforeach
  </div>
</div>
<style>
  .sp { position:relative; border-radius:28px; overflow:hidden; border:1px solid var(--line); min-height:460px; display:grid; grid-template-rows:1fr auto; }
  .sp-bg { position:absolute; inset:0; background:radial-gradient(60% 90% at 78% 40%, color-mix(in srgb,var(--c1) 35%,transparent), transparent 70%), radial-gradient(50% 60% at 95% 100%, var(--c2), transparent 70%), #0b0b0f; transition:background 1s; }
  .sp-bg::after { content:""; position:absolute; inset:0; background:linear-gradient(90deg,#09090bf2 30%,#09090b66 70%,#09090b22), linear-gradient(0deg,#09090b 0,transparent 40%); }
  .sp-slides { position:relative; display:grid; }
  .sp-slide { grid-area:1/1; position:relative; }
  .sp-art { position:absolute; right:3%; top:46%; transform:translateY(-58%); font:800 clamp(110px,17vw,220px)/1 var(--display); letter-spacing:-.06em; color:transparent; -webkit-text-stroke:2px color-mix(in srgb,var(--c1) 55%,transparent); opacity:.35; pointer-events:none; }
  .sp-body { position:relative; display:grid; gap:12px; justify-items:start; padding:48px 48px 28px; max-width:600px; }
  .sp-body > * { animation:spIn .7s cubic-bezier(.2,.8,.2,1) both; }
  .sp-body > :nth-child(2) { animation-delay:.05s } .sp-body > :nth-child(3) { animation-delay:.1s } .sp-body > :nth-child(4) { animation-delay:.15s } .sp-body > :nth-child(5) { animation-delay:.2s } .sp-body > :nth-child(6) { animation-delay:.25s }
  @keyframes spIn { from { opacity:0; transform:translateY(14px); } }
  .sp-logo { width:72px; height:72px; border-radius:18px; }
  .sp-body h3 { margin:6px 0 0; font:800 clamp(30px,5vw,52px)/1.02 var(--display); letter-spacing:-.03em; }
  .sp-body p { margin:0; color:var(--muted); }
  .sp-body .gs-stats { font-size:14px; gap:6px 22px; }
  .sp-leader b { color:var(--c1); }
  .sp-cta { margin-top:10px; padding:13px 20px; border-radius:14px; }
  .sp-thumbs { position:relative; display:flex; gap:8px; padding:16px; overflow-x:auto; scrollbar-width:none; background:#09090bb3; backdrop-filter:blur(10px); border-top:1px solid #ffffff12; }
  .sp-thumb { flex:none; position:relative; display:flex; align-items:center; gap:10px; padding:8px 14px 8px 8px; border-radius:14px; border:1px solid transparent; background:none; color:var(--muted); font:700 13px var(--font); cursor:pointer; overflow:hidden; }
  .sp-ico { width:34px; height:34px; border-radius:9px; object-fit:cover; font-size:12px; }
  .sp-thumb:hover { color:var(--text); }
  .sp-thumb[aria-selected=true] { color:var(--text); border-color:color-mix(in srgb,var(--c1) 50%,transparent); background:color-mix(in srgb,var(--c1) 12%,transparent); }
  .sp-bar { position:absolute; left:0; bottom:0; height:2px; width:0; background:var(--c1); }
  .sp-thumb[aria-selected=true] .sp-bar { animation:spFill 6s linear forwards; }
  .sp.is-paused .sp-bar { animation-play-state:paused; }
  @keyframes spFill { to { width:100%; } }
  @media (max-width:640px) { .sp-body { padding:32px 22px 18px; } }
</style>
<script>
(() => {
  const root = document.querySelector('[data-sp]');
  if (!root) return;
  const slides = [...root.querySelectorAll('.sp-slide')], tabs = [...root.querySelectorAll('.sp-thumb')];
  const T = 6000, reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  let cur = 0, timer, left = T, started = 0;
  const show = (i) => {
    cur = i;
    root.style.cssText = slides[i].getAttribute('style');
    slides.forEach((s, j) => { s.hidden = j !== i; });
    tabs.forEach((t, j) => {
      t.setAttribute('aria-selected', j === i ? 'true' : 'false');
      if (j === i) { const bar = t.querySelector('.sp-bar'); bar.style.animation = 'none'; void bar.offsetWidth; bar.style.animation = ''; t.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
    });
    left = T; schedule();
  };
  const schedule = () => { clearTimeout(timer); if (reduce || root.classList.contains('is-paused')) return; started = Date.now(); timer = setTimeout(() => show((cur + 1) % slides.length), left); };
  const pause = () => { if (root.classList.contains('is-paused')) return; clearTimeout(timer); left -= Date.now() - started; root.classList.add('is-paused'); };
  const resume = () => { root.classList.remove('is-paused'); schedule(); };
  tabs.forEach((t, i) => t.addEventListener('click', () => show(i)));
  root.querySelector('.sp-thumbs').addEventListener('keydown', (e) => {
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
    e.preventDefault(); const i = (cur + (e.key === 'ArrowLeft' ? -1 : 1) + tabs.length) % tabs.length; show(i); tabs[i].focus();
  });
  root.addEventListener('mouseenter', pause); root.addEventListener('mouseleave', resume);
  root.addEventListener('focusin', pause); root.addEventListener('focusout', (e) => { if (!root.contains(e.relatedTarget)) resume(); });
  show(0);
})();
</script>
