/*
 * Сторінка гри в стилі Counter-Strike (design/game/cs.html): спільна логіка + радар, приціл,
 * клавіші меню закупівлі, копіювання connect і голоси стрічкою вбивств.
 */
import { $, $$, calm, esc, setToastRenderer } from './game-page';

const rnd = (a, b) => a + Math.random() * (b - a);
const dataEl = document.getElementById('game-data');
const T = dataEl ? JSON.parse(dataEl.textContent).t : {};

// Радар у банері: мітки спалахують, коли їх проходить промінь
const blips = $('#blips');
if (blips) blips.innerHTML = Array.from({ length: 9 }, (_, i) => {
  const a = rnd(0, Math.PI * 2), d = rnd(18, 46);
  const delay = ((a + Math.PI / 2) / (Math.PI * 2) * 3.2 + 3.2) % 3.2;
  return `<i class="blip ${i < 6 ? 'en' : 'ally'}" style="left:${50 + Math.cos(a) * d}%;top:${50 + Math.sin(a) * d}%;animation-delay:${delay.toFixed(2)}s"></i>`;
}).join('');

// Приціл за мишею в банері; клік — «постріл»
const hero = $('#hero');
if (hero && !calm) {
  hero.addEventListener('pointermove', e => {
    const r = hero.getBoundingClientRect();
    hero.style.setProperty('--x', `${e.clientX - r.left}px`);
    hero.style.setProperty('--y', `${e.clientY - r.top}px`);
  });
  hero.addEventListener('pointerdown', () => { hero.classList.add('shot'); setTimeout(() => hero.classList.remove('shot'), 120); });
}

// Меню закупівлі з клавіатури: цифра вмикає пункт, як купівля в грі (крім випадків, коли людина друкує)
addEventListener('keydown', e => {
  if (e.ctrlKey || e.metaKey || e.altKey || /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName)) return;
  const item = $(`.buy a.buy-item[data-key="${e.key}"]`);
  if (!item) return;
  item.classList.remove('press'); void item.offsetWidth; item.classList.add('press');
  item.click();
});

// «Підключитися» копіює команду для консолі CS
document.addEventListener('click', async e => {
  const b = e.target.closest('.connect');
  if (!b || b.disabled) return;
  try { await navigator.clipboard.writeText(b.dataset.connect); } catch (err) {}
  const text = b.textContent;
  b.classList.add('copied'); b.textContent = T.copied;
  setTimeout(() => { b.classList.remove('copied'); b.textContent = text; }, 1800);
});

// Голоси — стрічка вбивств праворуч угорі: «нік ⊕ сервер»
const feed = $('#killfeed');
const icon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="12" cy="12" r="7"/><path d="M12 2v5M12 17v5M2 12h5M17 12h5"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/></svg>';
if (feed) setToastRenderer(v => {
  const k = document.createElement('div');
  k.className = 'kill';
  k.innerHTML = `<span class="a">${esc(v.nick || T.anon)}</span>${icon}<span class="b">${esc(v.server)}</span>`;
  feed.append(k);
  while (feed.children.length > 4) feed.firstElementChild.remove();
  setTimeout(() => { k.classList.add('out'); setTimeout(() => k.remove(), 500); }, 5000);
});

// Таблиця рахунку: рядки з'являються зліва при першому показі
if (!calm) $$('#list .row').forEach((el, i) => el.animate([{ opacity: 0, transform: 'translateX(-18px)' }, { opacity: 1, transform: 'none' }], { duration: 380, delay: 300 + i * 30, easing: 'cubic-bezier(.2,.8,.2,1)', fill: 'backwards' }));
