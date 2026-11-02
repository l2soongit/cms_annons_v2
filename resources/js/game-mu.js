/*
 * Сторінка MU Online (design/game/mu-b.html): спільна логіка (фільтри, список, таймери, живі голоси — game-page)
 * + відлік до фіналу сезону в банері.
 */
import { $, $$ } from './game-page';

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
