/*
 * Сторінка гри в стилі DayZ (design/game/dayz.html): спільна логіка + нічний ліс із паралаксом і ліхтариком,
 * дощ, сигнальна ракета й погода; ігровий час серверів іде з прискоренням; IP копіюється; голоси приходять «по рації».
 * Усе, що рухається в банері, зупиняється, коли банер поза екраном або вкладка прихована.
 */
import { $, $$, calm, esc, setToastRenderer } from './game-page';

const rnd = (a, b) => a + Math.random() * (b - a);
const dataEl = document.getElementById('game-data');
const DATA = dataEl ? JSON.parse(dataEl.textContent) : {};
const T = DATA.t ?? {};
// Детермінований генератор: однаковий ліс після кожного перезавантаження
const seeded = seed => () => (seed = (seed * 1103515245 + 12345) >>> 0) / 4294967296;

const hero = $('#zhero');
let heroVisible = true;
const active = () => heroVisible && !document.hidden;

if (hero) {
  // ── Ліс: три шари ялин, ближчі — темніші й вищі ──
  const forest = (svg, w, h, count, minH, maxH, color, seed) => {
    if (!svg) return;
    const r = seeded(seed);
    let d = '';
    for (let i = 0; i < count; i++) {
      const x = (i / count) * w + r() * (w / count), th = minH + r() * (maxH - minH), tw = th * (.22 + r() * .1), top = h - th;
      // Ялина — три яруси гілок і стовбур
      d += `M${x} ${top}`;
      for (let k = 1; k <= 3; k++) { const y = top + th * k * .27, s = tw * (.45 + k * .2); d += `L${x + s} ${y}L${x + s * .45} ${y}`; }
      d += `L${x + tw * .12} ${h}L${x - tw * .12} ${h}`;
      for (let k = 3; k >= 1; k--) { const y = top + th * k * .27, s = tw * (.45 + k * .2); d += `L${x - s * .45} ${y}L${x - s} ${y}`; }
      d += 'Z';
    }
    svg.innerHTML = `<path d="${d}" fill="${color}"/><rect y="${h - 6}" width="${w}" height="6" fill="${color}"/>`;
  };
  forest($('#f-far'), 1200, 300, 70, 90, 190, '#1f2819', 3);
  forest($('#f-mid'), 1200, 260, 46, 110, 220, '#141a10', 11);
  forest($('#f-near'), 1200, 200, 26, 120, 200, '#090c07', 29);

  // Банер поза екраном — анімації CSS на паузі, цикли JS не крутяться
  const sync = () => hero.classList.toggle('is-paused', !active());
  if ('IntersectionObserver' in window) new IntersectionObserver(([e]) => { const was = heroVisible; heroVisible = e.isIntersecting; sync(); if (heroVisible && !was) wake(); }).observe(hero);
  document.addEventListener('visibilitychange', () => { sync(); if (active()) wake(); });

  // ── Паралакс і ліхтарик за курсором; без курсора ліхтарик сам «оглядає» ліс ──
  const dark = $('#dark');
  const layers = [[$('#f-far'), 6], [$('#f-mid'), 14], [$('#f-near'), 26]];
  let manual = 0, wandering = false;
  const aim = (x, y) => {
    dark.style.setProperty('--fx', `${x * 100}%`); dark.style.setProperty('--fy', `${y * 100}%`);
    if (!calm) layers.forEach(([el, k]) => { el.style.transform = `translateX(${(.5 - x) * k}px)`; });
  };
  hero.addEventListener('pointermove', e => {
    const r = hero.getBoundingClientRect();
    manual = performance.now();
    aim((e.clientX - r.left) / r.width, (e.clientY - r.top) / r.height);
  });
  const wander = t => {
    if (!active()) { wandering = false; return; }
    if (t - manual > 2500) aim(.68 + Math.sin(t / 2600) * .2, .58 + Math.sin(t / 1700) * .12);
    requestAnimationFrame(wander);
  };

  // ── Дощ: косі краплі на canvas ──
  const canvas = $('#rain'), ctx = canvas?.getContext('2d');
  let drops = [], raining = false;
  const resize = () => {
    if (!canvas) return;
    canvas.width = canvas.clientWidth * devicePixelRatio; canvas.height = canvas.clientHeight * devicePixelRatio;
    drops = Array.from({ length: Math.round(canvas.clientWidth / 6) }, () => ({ x: rnd(0, canvas.width), y: rnd(0, canvas.height), v: rnd(9, 15), l: rnd(8, 18) }));
  };
  const rain = () => {
    if (!active()) { raining = false; return; }
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = '#c9d4c033'; ctx.lineWidth = devicePixelRatio;
    ctx.beginPath();
    drops.forEach(d => {
      ctx.moveTo(d.x, d.y); ctx.lineTo(d.x - d.l * .35, d.y + d.l * devicePixelRatio);
      d.y += d.v * devicePixelRatio; d.x -= d.v * .35;
      if (d.y > canvas.height) { d.y = -20; d.x = rnd(0, canvas.width + 200); }
    });
    ctx.stroke();
    requestAnimationFrame(rain);
  };

  // Запуск циклів, коли банер знову видно
  function wake() {
    if (calm) return;
    if (!wandering) { wandering = true; requestAnimationFrame(wander); }
    if (ctx && !raining) { raining = true; requestAnimationFrame(rain); }
  }
  if (!calm) { resize(); addEventListener('resize', resize, { passive: true }); wake(); }

  // ── Сигнальна ракета: злітає над лісом і підсвічує небо червоним ──
  const flare = $('#flare'), sky = $('#sky');
  const launch = () => {
    if (!active()) return;
    const x = rnd(.45, .85);
    flare.style.left = `${x * 100}%`; flare.style.bottom = '38%';
    flare.style.setProperty('--dx', `${rnd(-60, 60)}px`);
    sky.style.setProperty('--flx', `${x * 100}%`);
    flare.classList.remove('go'); void flare.offsetWidth; flare.classList.add('go');
    setTimeout(() => sky.style.setProperty('--flash', '1'), 1200);
    setTimeout(() => sky.style.setProperty('--flash', '0'), 4600);
  };
  // Погода в куті банера — рядки з перекладів або з адмінки
  const weather = DATA.weather ?? [];
  let w = 0;
  if (!calm) {
    setTimeout(launch, 2500); setInterval(launch, 16000);
    if (weather.length > 1) setInterval(() => { if (active()) $('#weather').textContent = weather[++w % weather.length]; }, 9000);
  }
}

// ── Ігровий час: з моніторингу, далі йде з прискоренням сервера від моменту перевірки ──
const pad = n => String(n).padStart(2, '0');
const clocks = () => $$('[data-time]').forEach(el => {
  const [h, m] = el.dataset.time.split(':').map(Number);
  const passed = el.dataset.at ? (Date.now() - Number(el.dataset.at)) / 60000 * Number(el.dataset.accel || 1) : 0;
  const total = (Math.floor(h * 60 + m + passed) % 1440 + 1440) % 1440;
  const hh = Math.floor(total / 60);
  el.querySelector('[data-clock]').textContent = `${pad(hh)}:${pad(total % 60)}`;
  el.classList.toggle('day', hh >= 6 && hh < 20);
  el.classList.toggle('night', !(hh >= 6 && hh < 20));
});
clocks(); setInterval(clocks, 5000);

// ── Копіювання адреси ──
document.addEventListener('click', async e => {
  const copy = e.target.closest('.copy');
  if (!copy) return;
  try { await navigator.clipboard.writeText(copy.dataset.ip); } catch (err) {}
  const text = copy.textContent;
  copy.classList.add('done'); copy.textContent = T.copied;
  setTimeout(() => { copy.classList.remove('done'); copy.textContent = text; }, 1600);
});

// ── Голоси приходять по рації ──
const comms = $('#comms');
if (comms) setToastRenderer(v => {
  const m = document.createElement('div');
  m.className = 'msg';
  m.innerHTML = `<span class="ch">${esc(T.channel)} ${1 + Math.floor(rnd(0, 4))}</span><span><b>${esc(v.nick || T.anon)}</b> ${esc(T.voted)} ${esc(v.server)}</span>`;
  comms.append(m);
  while (comms.children.length > 3) comms.firstElementChild.remove();
  setTimeout(() => { m.classList.add('out'); setTimeout(() => m.remove(), 500); }, 6000);
});

// Журнал: рядки з'являються каскадом при першому показі
if (!calm) $$('#list .srv').forEach((el, i) => el.animate([{ opacity: 0, transform: 'translateY(10px)' }, { opacity: 1, transform: 'none' }], { duration: 320, delay: 200 + Math.min(i, 12) * 35, easing: 'cubic-bezier(.2,.8,.2,1)', fill: 'backwards' }));
