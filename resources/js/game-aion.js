/*
 * Сторінка гри Aion (design/game/aion-a.html): спільна логіка + вибір раси в банері
 * (перефарбовує сторінку й показує в кожному сервері «твою» частку; запам'ятовується), відлік до фіналу сезону,
 * живі голоси. Фільтри, список і таймери — спільні (game-page).
 */
import { $, $$, esc, setToastRenderer } from './game-page';

const dataEl = document.getElementById('game-data');
const DATA = dataEl ? JSON.parse(dataEl.textContent) : {};
const T = DATA.t ?? {};
const tr = (str, vars) => Object.entries(vars).reduce((s, [k, v]) => s.replaceAll(`:${k}`, v), str ?? '');
const store = { get: k => { try { return localStorage.getItem(k); } catch { return null; } }, set: (k, v) => { try { v ? localStorage.setItem(k, v) : localStorage.removeItem(k); } catch {} } };

// ── Раса: клік по стороні банера ──
const hero = $('#ahero');
let race = store.get('aion-race') || '';

// «Твоя раса: 46% — у меншості» в кожному сервері, що надсилає баланс
const paintShares = () => $$('.srv[data-ely]').forEach(row => {
  const mine = $('.mine', row);
  if (!mine) return;
  if (!race) { mine.textContent = ''; return; }
  const share = race === 'ely' ? Number(row.dataset.ely) : 100 - Number(row.dataset.ely);
  mine.textContent = `${share >= 50 ? '▲' : '▼'} ${share}% — ${share >= 50 ? mine.dataset.majority : mine.dataset.minority}`;
});

const applyRace = () => {
  document.body.dataset.faction = race;
  hero?.classList.toggle('picked', !!race);
  $$('#ahero .rpick').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.f === race)));
  paintShares();
};
$$('#ahero .rpick').forEach(b => b.addEventListener('click', () => {
  race = race === b.dataset.f ? '' : b.dataset.f;
  store.set('aion-race', race);
  applyRace();
}));
applyRace();
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
