/*
 * Сторінка гри в стилі GTA (design/game/gta.html): спільна логіка + вікна міста й копіювання адреси підключення.
 */
import { $ } from './game-page';

const rnd = (a, b) => a + Math.random() * (b - a);

// Вікна хмарочосів: частина блимає, як у живому місті
const windows = $('#windows');
if (windows) windows.innerHTML = Array.from({ length: 70 }, () => `<rect class="${Math.random() < .25 ? 'win' : ''}" x="${Math.round(rnd(4, 590))}" y="${Math.round(rnd(40, 190))}" width="3" height="4" opacity="${rnd(.35, .95).toFixed(2)}" style="animation-delay:-${rnd(0, 3).toFixed(1)}s"/>`).join('');

// «Підключитися» копіює команду входу (FiveM — для консолі F8) або адресу
document.addEventListener('click', async e => {
  const b = e.target.closest('.connect');
  if (!b || b.disabled) return;
  try { await navigator.clipboard.writeText(b.dataset.connect); } catch (err) {}
  const html = b.innerHTML;
  b.classList.add('copied'); b.textContent = b.dataset.copied;
  setTimeout(() => { b.classList.remove('copied'); b.innerHTML = html; }, 1800);
});
