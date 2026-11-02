/*
 * Сторінка World of Warcraft (design/game/wow-b.html): спільна логіка (фільтри, список, таймери — game-page)
 * + вибір фракції в банері (перефарбовує сторінку й показує в кожному реалмі «твою» частку; запам'ятовується),
 * відлік до фіналу сезону, копіювання set realmlist і живі голоси.
 */
import { $, $$, esc, setToastRenderer } from './game-page';

const dataEl = document.getElementById('game-data');
const DATA = dataEl ? JSON.parse(dataEl.textContent) : {};
const T = DATA.t ?? {};
const tr = (str, vars) => Object.entries(vars).reduce((s, [k, v]) => s.replaceAll(`:${k}`, v), str ?? '');
const store = { get: k => { try { return localStorage.getItem(k); } catch { return null; } }, set: (k, v) => { try { v ? localStorage.setItem(k, v) : localStorage.removeItem(k); } catch {} } };

// ── Фракція: перемикачі в кутах банера ──
let faction = store.get('wow-faction') || '';

// «Твоя фракція: 47% — у меншості» в кожному реалмі, що надсилає баланс
const paintShares = () => $$('.srv[data-alli]').forEach(row => {
  const mine = $('.mine', row);
  if (!mine) return;
  if (!faction) { mine.textContent = ''; return; }
  const share = faction === 'alli' ? Number(row.dataset.alli) : 100 - Number(row.dataset.alli);
  mine.textContent = `${share >= 50 ? '▲' : '▼'} ${share}% — ${share >= 50 ? mine.dataset.majority : mine.dataset.minority}`;
});

const applyFaction = () => {
  document.body.dataset.faction = faction;
  $$('#whero .fpick').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.f === faction)));
  paintShares();
};
$$('#whero .fpick').forEach(b => b.addEventListener('click', () => {
  faction = faction === b.dataset.f ? '' : b.dataset.f;
  store.set('wow-faction', faction);
  applyFaction();
}));
applyFaction();
// Список оновлюється без перезавантаження (фільтри, «Показати ще») — частки треба намалювати знову
const results = $('#results');
if (results) new MutationObserver(paintShares).observe(results, { childList: true, subtree: true });

// ── Відлік до фіналу сезону ──
(() => {
  const clock = $('#clock');
  if (!clock) return;
  const end = new Date(clock.dataset.end).getTime();
  const units = Object.fromEntries($$('b[data-u]', clock).map(b => [b.dataset.u, b]));
  const tick = () => {
    const s = Math.max(0, Math.floor((end - Date.now()) / 1000));
    const v = { d: Math.floor(s / 86400), h: Math.floor(s % 86400 / 3600), m: Math.floor(s % 3600 / 60), s: s % 60 };
    Object.entries(units).forEach(([u, el]) => { const t = String(v[u]).padStart(2, '0'); if (el.textContent !== t) el.textContent = t; });
  };
  tick(); setInterval(tick, 1000);
})();

// ── Копіювання set realmlist ──
document.addEventListener('click', e => {
  const btn = e.target.closest('.copy[data-ip]');
  if (!btn) return;
  const text = btn.dataset.ip, label = btn.textContent;
  (navigator.clipboard?.writeText(text) ?? Promise.reject()).catch(() => {
    // Без HTTPS clipboard недоступний — виділяємо рядок, щоб скопіювати вручну
    const code = btn.previousElementSibling;
    if (code) { const r = document.createRange(); r.selectNodeContents(code); getSelection().removeAllRanges(); getSelection().addRange(r); }
  });
  btn.textContent = T.copied; btn.classList.add('done');
  setTimeout(() => { btn.textContent = label; btn.classList.remove('done'); }, 1600);
});

// ── Голоси наживо ──
setToastRenderer(v => {
  const box = $('#toasts');
  if (!box) return;
  const el = document.createElement('div');
  el.className = 'toast';
  el.innerHTML = `${esc(tr(T.voted, { server: v.server }))}<br><small>${esc(v.nick || T.anon)}</small>`;
  box.append(el);
  while (box.children.length > 3) box.firstElementChild.remove();
  setTimeout(() => { el.classList.add('out'); setTimeout(() => el.remove(), 500); }, 4000);
});
