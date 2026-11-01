{{-- «Обери свою гру» · Акордеон: усі ігри вузькими панелями, наведена (або вибрана) розгортається з описом і кнопкою --}}
@use('App\Support\GamesSlider', 'GS')
@include('home.games._parts')
<div class="ac" data-ac>
  @foreach ($games as $i => $g)
    <div @class(['ac-item', 'is-open' => $i === 0]) style="{{ GS::vars($g) }}">
      <button class="ac-head gs-focus" type="button" aria-expanded="{{ $i ? 'false' : 'true' }}" aria-controls="ac-{{ $i }}">
        {{ GS::logo($g, 'gs-logo ac-logo') }}
        <span class="ac-vert">{{ $g['name'] }}</span>
      </button>
      @if ($g['online'])<span class="ac-pill"><i class="gs-live"></i>{{ __('ui.home.online', ['n' => number_format($g['online'], 0, ',', "\u{00A0}")]) }}</span>@endif
      <div class="ac-detail" id="ac-{{ $i }}">
        @if ($g['eyebrow'])<span class="gs-tag">{{ $g['eyebrow'] }}</span>@endif
        <h3>{{ $g['name'] }}</h3>
        @if ($g['text'])<p>{{ $g['text'] }}</p>@endif
        {{ GS::stats($g) }}
        @if ($g['leader'])<p class="ac-leader">{{ __('ui.home.gs.leader_month') }} <b>{{ $g['leader'] }}</b></p>@endif
        <a class="gs-go" href="{{ $g['url'] }}" @if ($i) tabindex="-1" @endif>{{ __('ui.home.gs.choose') }} →</a>
      </div>
    </div>
  @endforeach
</div>
<style>
  .ac { display:flex; gap:10px; height:420px; }
  .ac-item { position:relative; flex:1; min-width:0; border-radius:22px; overflow:hidden; border:1px solid color-mix(in srgb,var(--c1) 25%,transparent);
    background:radial-gradient(140% 60% at 50% 100%,color-mix(in srgb,var(--c1) 45%,transparent),transparent 70%),linear-gradient(180deg,#121216,color-mix(in srgb,var(--c2) 70%,#09090b));
    transition:flex .6s cubic-bezier(.2,.8,.2,1), border-color .3s; }
  .ac-item.is-open { flex:5; border-color:var(--c1); }
  .ac-head { position:absolute; inset:0; width:100%; border:0; padding:0; background:none; color:var(--text); cursor:pointer; border-radius:inherit; }
  .ac-item.is-open .ac-head { inset:0 auto auto 0; width:120px; height:110px; cursor:default; }
  .ac-logo { position:absolute; left:50%; top:22px; transform:translateX(-50%); width:48px; height:48px; transition:.5s cubic-bezier(.2,.8,.2,1); }
  .ac-item.is-open .ac-logo { left:28px; transform:none; width:64px; height:64px; }
  .ac-vert { position:absolute; left:50%; bottom:24px; transform:translateX(-50%) rotate(180deg); writing-mode:vertical-rl; font:800 18px var(--display); white-space:nowrap; transition:opacity .3s; }
  .ac-item.is-open .ac-vert { opacity:0; }
  .ac-pill { position:absolute; top:24px; right:24px; padding:5px 10px; border-radius:999px; font-size:12px; font-weight:700; background:#09090b99; backdrop-filter:blur(6px); opacity:0; transition:opacity .3s .2s; pointer-events:none; }
  .ac-item.is-open .ac-pill { opacity:1; }
  .ac-detail { position:absolute; left:28px; right:28px; bottom:28px; display:grid; gap:10px; justify-items:start; opacity:0; transform:translateY(12px); transition:opacity .3s, transform .4s; pointer-events:none; }
  .ac-item.is-open .ac-detail { opacity:1; transform:none; transition-delay:.25s; pointer-events:auto; }
  .ac-detail h3 { margin:2px 0 0; font:800 34px/1.05 var(--display); letter-spacing:-.02em; }
  .ac-detail p { margin:0; color:#d4d4d8; max-width:440px; }
  .ac-leader b { color:var(--c1); }
  .ac-detail .gs-go { margin-top:6px; }
  @media (max-width:860px) {
    .ac { flex-direction:column; height:auto; }
    .ac-item { flex:none; min-height:64px; transition:min-height .5s; }
    .ac-item.is-open { min-height:380px; }
    .ac-head { height:64px; bottom:auto; }
    .ac-item.is-open .ac-head { width:100%; height:110px; }
    .ac-logo { left:18px; top:8px; transform:none; }
    .ac-vert { writing-mode:horizontal-tb; transform:none; left:84px; bottom:auto; top:20px; }
  }
</style>
<script>
(() => {
  const root = document.querySelector('[data-ac]');
  if (!root) return;
  const items = [...root.children];
  const open = (el) => items.forEach((x) => {
    const on = x === el;
    x.classList.toggle('is-open', on);
    x.querySelector('.ac-head').setAttribute('aria-expanded', on ? 'true' : 'false');
    x.querySelector('.ac-detail .gs-go').tabIndex = on ? 0 : -1;
  });
  items.forEach((el) => {
    el.addEventListener('mouseenter', () => { if (matchMedia('(hover: hover)').matches) open(el); });
    el.querySelector('.ac-head').addEventListener('click', () => open(el));
  });
})();
</script>
