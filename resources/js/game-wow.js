/*
 * Сторінка гри в стилі World of Warcraft (design/game/wow.html): спільна логіка + вибір фракції в банері
 * (перефарбовує сторінку й показує в кожному реалмі «твою» частку; запам'ятовується), відлік до фіналу сезону,
 * копіювання set realmlist і голоси-сувої. Анімації банера стоять, коли його не видно.
 */
import { $, $$, calm, esc, setToastRenderer } from './game-page';

const dataEl = document.getElementById('game-data');
const DATA = dataEl ? JSON.parse(dataEl.textContent) : {};
const T = DATA.t ?? {};
const tr = (str, vars) => Object.entries(vars).reduce((s, [k, v]) => s.replaceAll(`:${k}`, v), str ?? '');
const store = { get: k => { try { return localStorage.getItem(k); } catch { return null; } }, set: (k, v) => { try { v ? localStorage.setItem(k, v) : localStorage.removeItem(k); } catch {} } };

// ── Фракція: клік по стороні банера ──
const hero = $('#whero');
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
  hero?.classList.toggle('picked', !!faction);
  $$('.side').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.f === faction)));
  paintShares();
};
$$('.side').forEach(b => b.addEventListener('click', () => {
  faction = faction === b.dataset.f ? '' : b.dataset.f;
  store.set('wow-faction', faction);
  applyFaction();
}));
applyFaction();
// Список оновлюється без перезавантаження (фільтри, «Показати ще») — частки треба намалювати знову
const results = $('#results');
if (results) new MutationObserver(paintShares).observe(results, { childList: true, subtree: true });

// ── Відлік до фіналу сезону: окремі цифри днів, годин, хвилин, секунд ──
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

// ── Голоси наживо: сувої ──
setToastRenderer(v => {
  const box = $('#toasts');
  if (!box) return;
  const el = document.createElement('div');
  el.className = 'toast';
  el.innerHTML = `<svg aria-hidden="true"><use href="#crest-neutral"/></svg><span>${esc(tr(T.voted, { server: v.server }))}<br><small>${esc(v.nick || T.anon)}</small></span>`;
  box.append(el);
  while (box.children.length > 3) box.firstElementChild.remove();
  setTimeout(() => { el.classList.add('out'); setTimeout(() => el.remove(), 500); }, 4000);
});

// ── Банер поза екраном або вкладка прихована — анімації на паузі ──
if (hero && !calm && 'IntersectionObserver' in window) {
  let visible = true;
  const sync = () => hero.classList.toggle('is-paused', !visible || document.hidden);
  new IntersectionObserver(([e]) => { visible = e.isIntersecting; sync(); }).observe(hero);
  document.addEventListener('visibilitychange', sync);
}
