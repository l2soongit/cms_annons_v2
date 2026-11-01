{{-- Спільні стилі варіантів «Обери свою гру»; розмітку логотипа й цифр дає App\Support\GamesSlider --}}
@once
<style>
  .gs-logo { width:56px; height:56px; border-radius:14px; object-fit:cover; flex:none; background:#0b0b0f; box-shadow:0 0 0 1px #ffffff26, 0 10px 30px -10px var(--c1); }
  .gs-mono { display:grid; place-items:center; font:800 22px var(--display); color:#0b0b0f; background:linear-gradient(140deg,var(--c1),var(--c2)); }
  .gs-stats { display:flex; flex-wrap:wrap; gap:4px 14px; font-size:13px; color:var(--muted); }
  .gs-stats b { color:var(--text); font-variant-numeric:tabular-nums; }
  .gs-live { display:inline-block; width:7px; height:7px; border-radius:50%; background:#22c55e; margin-right:6px; vertical-align:1px; animation:gsPulse 2s infinite; }
  @keyframes gsPulse { 0% { box-shadow:0 0 0 0 #22c55e88; } 70%, 100% { box-shadow:0 0 0 7px #22c55e00; } }
  .gs-tag { display:inline-block; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--c1); background:color-mix(in srgb,var(--c1) 14%,transparent); border:1px solid color-mix(in srgb,var(--c1) 35%,transparent); }
  .gs-go { display:inline-flex; align-items:center; gap:8px; padding:11px 18px; border-radius:12px; background:var(--c1); color:#0b0b0f; font-weight:800; font-size:14px; transition:filter .2s; }
  .gs-go:hover { filter:brightness(1.1); }
  .gs-btn { width:46px; height:46px; border-radius:50%; border:1px solid var(--line-2); background:#18181bcc; color:var(--text); display:grid; place-items:center; cursor:pointer; transition:background-color .2s, transform .2s; }
  .gs-btn:hover { background:var(--card-2); }
  .gs-btn:active { transform:scale(.94); }
  .gs-btn:focus-visible, .gs-focus:focus-visible { outline:2px solid var(--amber); outline-offset:3px; }
  @media (prefers-reduced-motion:reduce) { [class*="gs-"], [class*="gs-"]::before, [class*="gs-"]::after { animation:none !important; transition:none !important; } }
</style>
@endonce
