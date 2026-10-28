/*
 * Сторінка гри в стилі Minecraft (design/game/minecraft.html): спільна логіка + піксельний рельєф з деревами,
 * жовтий «сплеш», вибір рядка, копіювання IP і голоси як повідомлення в ігровому чаті.
 */
import { $, $$, calm, esc, setToastRenderer } from './game-page';

const rnd = (a, b) => a + Math.random() * (b - a);
const dataEl = document.getElementById('game-data');
const DATA = dataEl ? JSON.parse(dataEl.textContent) : {};
const T = DATA.t ?? {};

// Рельєф: стовпчики блоків — трава, земля, камінь; кілька руд поблискують
const hero = $('.mhero');
const terrain = $('#terrain');
if (hero && terrain) {
  const cols = 40, ores = ['#4ee6d6', '#ffd84a', '#e5332b', '#55ff55'];
  let h = 5;
  terrain.innerHTML = Array.from({ length: cols }, (_, i) => {
    h = Math.max(3, Math.min(8, h + Math.round(rnd(-1.2, 1.2))));
    return `<div class="col">${Array.from({ length: h }, (_, k) => {
      if (k === 0) return '<i class="blk g"></i>';
      if (k < 3) return '<i class="blk d"></i>';
      return Math.random() < .12 ? `<i class="blk o" style="--ore:${ores[i % 4]};animation-delay:-${rnd(0, 2).toFixed(1)}s"></i>` : '<i class="blk s"></i>';
    }).join('')}</div>`;
  }).join('');

  // Дерева стоять на верхівці свого стовпчика, праворуч від тексту; при зміні ширини переставляються
  const plant = () => {
    const colW = hero.clientWidth / cols;
    const top0 = hero.getBoundingClientRect().top;
    $('#trees').innerHTML = (hero.clientWidth < 640 ? [30] : [22, 27]).map(i => {
      const top = $$('.col', terrain)[i].getBoundingClientRect().top - top0;
      return `<div class="tree" style="--b:${colW}px;left:${i * colW - colW}px;top:${top - colW * 4}px">
        <i class="lf"></i><i class="lf"></i><i class="lf"></i><i class="lf"></i><i class="lf"></i><i class="lf"></i><i></i><i class="tr"></i><i></i><i></i><i class="tr"></i><i></i></div>`;
    }).join('');
  };
  plant();
  addEventListener('resize', plant, { passive: true });
}

// Шапка поза екраном (людина гортає список) або вкладка прихована — її анімації на паузі,
// щоб хмари, куб і руди не навантажували відеокарту, поки їх не видно
let heroVisible = true;
if (hero) {
  const sync = () => hero.classList.toggle('is-paused', !heroVisible || document.hidden);
  if ('IntersectionObserver' in window) new IntersectionObserver(([e]) => { heroVisible = e.isIntersecting; sync(); }).observe(hero);
  document.addEventListener('visibilitychange', sync);
}

// Жовтий «сплеш»-напис, як у меню гри; фрази — з перекладів
const splash = $('#splash');
const lines = DATA.splash ?? [];
if (splash && lines.length > 1 && !calm) {
  let i = 0;
  setInterval(() => { if (heroVisible && !document.hidden) splash.textContent = lines[++i % lines.length]; }, 4000);
}

// Вибір рядка (біла рамка, як у грі) і копіювання адреси
document.addEventListener('click', async e => {
  const copy = e.target.closest('.copy');
  if (copy) {
    try { await navigator.clipboard.writeText(copy.dataset.ip); } catch (err) {}
    const text = copy.textContent;
    copy.classList.add('copied'); copy.textContent = T.copied;
    setTimeout(() => { copy.classList.remove('copied'); copy.textContent = text; }, 1600);
    return;
  }
  const row = e.target.closest('.entry');
  if (row && !e.target.closest('a')) $$('.entry').forEach(x => x.classList.toggle('sel', x === row));
});

// Голоси приходять як повідомлення в ігровому чаті: «<нік> проголосував за сервер»
const chat = $('#chat');
if (chat) setToastRenderer(v => {
  const p = document.createElement('p');
  p.innerHTML = `&lt;<span class="nick">${esc(v.nick || T.anon)}</span>&gt; ${esc(T.voted)} <span class="srv">${esc(v.server)}</span>`;
  chat.append(p);
  while (chat.children.length > 5) chat.firstElementChild.remove();
  setTimeout(() => { p.classList.add('out'); setTimeout(() => p.remove(), 600); }, 7000);
});

// Рядки з'являються покроково при першому показі
if (!calm) $$('#list .entry').forEach((el, i) => el.animate([{ opacity: 0, transform: 'translateX(-12px)' }, { opacity: 1, transform: 'none' }], { duration: 240, delay: 300 + i * 40, easing: 'steps(4)', fill: 'backwards' }));
