/*
 * Сторінка гри в стилі Rust (design/game/rust.html): спільна логіка + іскри з багаття, підрив C4 по кліку,
 * процедурна мапа з сіткою, відлік до форс-вайпу, перехід із вайп-календаря до сервера, копіювання client.connect
 * і голоси в ігровому чаті. Усе, що рухається в банері, зупиняється, коли банер поза екраном або вкладка прихована.
 */
import { $, $$, calm, lite, esc, setToastRenderer } from './game-page';

const rnd = (a, b) => a + Math.random() * (b - a);
const dataEl = document.getElementById('game-data');
const DATA = dataEl ? JSON.parse(dataEl.textContent) : {};
const T = DATA.t ?? {};
const seeded = seed => () => (seed = (seed * 1103515245 + 12345) >>> 0) / 4294967296;

const hero = $('#rhero');
let heroVisible = true;
const active = () => heroVisible && !document.hidden;

if (hero) {
  // ── Іскри з багаття; клік — підрив C4: спалах, уламки, струс ──
  const canvas = $('#embers'), ctx = canvas.getContext('2d');
  let parts = [], running = false;
  const resize = () => { canvas.width = canvas.clientWidth * devicePixelRatio; canvas.height = canvas.clientHeight * devicePixelRatio; };
  const spawn = (x, y, burst) => ({ x, y, vx: burst ? rnd(-6, 6) : rnd(-.4, .4), vy: burst ? rnd(-9, -2) : rnd(-1.6, -.6), life: burst ? rnd(40, 80) : rnd(80, 160), age: 0, r: rnd(1, burst ? 3 : 2.2), burst });
  const frame = () => {
    if (!active()) { running = false; return; }
    const dpr = devicePixelRatio;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const fx = 77 * dpr, fy = canvas.height - 50 * dpr;
    if (parts.length < 120) for (let i = 0; i < 2; i++) parts.push(spawn(fx + rnd(-14, 14) * dpr, fy, false));
    ctx.globalCompositeOperation = 'lighter';
    parts = parts.filter(p => {
      p.age++; p.x += p.vx * dpr + Math.sin((p.age + p.r * 50) / 14) * .3 * dpr; p.y += p.vy * dpr;
      if (p.burst) p.vy += .25;
      const k = 1 - p.age / p.life;
      if (k <= 0) return false;
      ctx.fillStyle = `rgba(255, ${Math.round(120 + 100 * k)}, 60, ${k})`;
      ctx.beginPath(); ctx.arc(p.x, p.y, p.r * dpr, 0, 7); ctx.fill();
      return true;
    });
    ctx.globalCompositeOperation = 'source-over';
    requestAnimationFrame(frame);
  };
  // На телефоні іскор немає: canvas щокадру — найдорожче на сторінці
  const wake = () => { if (!calm && !lite && !running && active()) { running = true; requestAnimationFrame(frame); } };
  const sync = () => hero.classList.toggle('is-paused', !active());
  if ('IntersectionObserver' in window) new IntersectionObserver(([e]) => { heroVisible = e.isIntersecting; sync(); wake(); }).observe(hero);
  document.addEventListener('visibilitychange', () => { sync(); wake(); });
  if (!calm) { resize(); addEventListener('resize', resize, { passive: true }); wake(); }

  hero.addEventListener('click', e => {
    if (calm || e.target.closest('.mapbox, a, button')) return;
    const r = hero.getBoundingClientRect(), x = e.clientX - r.left, y = e.clientY - r.top;
    const b = document.createElement('span'); b.className = 'boom'; b.style.left = `${x}px`; b.style.top = `${y}px`; hero.append(b); setTimeout(() => b.remove(), 700);
    const f = $('#flash'); f.classList.remove('on'); void f.offsetWidth; f.classList.add('on');
    hero.classList.remove('shake'); void hero.offsetWidth; hero.classList.add('shake');
    for (let i = 0; i < 40; i++) parts.push(spawn(x * devicePixelRatio, y * devicePixelRatio, true));
    wake();
  });

  // ── Процедурна мапа острова з сіткою, як у грі: квадрат під курсором ──
  const c = $('#map');
  if (c) {
    const m = c.getContext('2d'), N = 320, r = seeded(DATA.seed || 1337);
    const grid = Array.from({ length: 17 }, () => Array.from({ length: 17 }, () => r()));
    const smooth = t => t * t * (3 - 2 * t);
    const noise = (x, y, s) => {
      const gx = x / N * s, gy = y / N * s, x0 = Math.floor(gx), y0 = Math.floor(gy), fx = smooth(gx - x0), fy = smooth(gy - y0);
      const g = (i, j) => grid[(y0 + j) % 17][(x0 + i) % 17];
      return (g(0, 0) * (1 - fx) + g(1, 0) * fx) * (1 - fy) + (g(0, 1) * (1 - fx) + g(1, 1) * fx) * fy;
    };
    const img = m.createImageData(N, N);
    for (let y = 0; y < N; y++) for (let x = 0; x < N; x++) {
      const d = Math.hypot(x - N / 2, y - N / 2) / (N / 2);
      const h = noise(x, y, 4) * .6 + noise(x, y, 9) * .3 + noise(x, y, 16) * .1 - d * .75;
      const col = h < .02 ? [32, 58, 74] : h < .06 ? [52, 88, 104] : h < .09 ? [196, 182, 140] : h < .22 ? [94, 112, 62] : h < .3 ? [72, 92, 52] : h < .38 ? [110, 102, 92] : [220, 220, 214];
      img.data.set([...col, 255], (y * N + x) * 4);
    }
    m.putImageData(img, 0, 0);
    // Монументи — назви з гри, однакові всіма мовами
    [[.28, .34, 'Launch'], [.62, .3, 'Airfield'], [.55, .6, 'Outpost'], [.36, .66, 'Dome'], [.72, .7, 'Water']].forEach(([mx, my, t]) => {
      m.fillStyle = '#1a1410cc'; m.fillRect(mx * N - 4, my * N - 4, 8, 8);
      m.strokeStyle = '#e2652f'; m.lineWidth = 1.5; m.strokeRect(mx * N - 4, my * N - 4, 8, 8);
      m.font = '700 9px "Roboto Condensed", sans-serif'; m.fillStyle = '#fff'; m.fillText(t, mx * N + 6, my * N + 3);
    });
    const base = m.getImageData(0, 0, N, N), cells = 16, cw = N / cells;
    const draw = (hx, hy) => {
      m.putImageData(base, 0, 0);
      m.strokeStyle = '#ffffff22'; m.lineWidth = 1;
      for (let i = 1; i < cells; i++) { m.beginPath(); m.moveTo(i * cw, 0); m.lineTo(i * cw, N); m.moveTo(0, i * cw); m.lineTo(N, i * cw); m.stroke(); }
      if (hx !== undefined) { m.fillStyle = '#e2652f44'; m.fillRect(hx * cw, hy * cw, cw, cw); m.strokeStyle = '#e2652f'; m.strokeRect(hx * cw + .5, hy * cw + .5, cw - 1, cw - 1); }
    };
    draw();
    c.addEventListener('pointermove', e => {
      const b = c.getBoundingClientRect(), hx = Math.min(cells - 1, Math.floor((e.clientX - b.left) / b.width * cells)), hy = Math.min(cells - 1, Math.floor((e.clientY - b.top) / b.height * cells));
      draw(hx, hy); $('#gridref').textContent = `${String.fromCharCode(65 + hx)}${hy}`;
    });
    c.addEventListener('pointerleave', () => draw());
  }
}

// ── Відлік до форс-вайпу (момент рахує сервер: перший четвер місяця, 19:00 за Лондоном) ──
const forced = $('#forced');
if (forced && DATA.forcedWipe) {
  const end = new Date(DATA.forcedWipe).getTime(), p = n => String(n).padStart(2, '0');
  const d = { uk: 'д', ru: 'д', pl: 'd', en: 'd' }[DATA.locale] ?? 'd';
  const tick = () => { const s = Math.max(0, Math.floor((end - Date.now()) / 1000)); forced.textContent = `${Math.floor(s / 86400)}${d} ${p(Math.floor(s % 86400 / 3600))}:${p(Math.floor(s % 3600 / 60))}:${p(s % 60)}`; };
  tick(); setInterval(tick, 1000);
}

// ── Смуги стану заповнюються після завантаження ──
setTimeout(() => $$('.vital .bar i[data-w]').forEach(i => { i.style.width = i.dataset.w + '%'; }), calm ? 0 : 400);

// ── Вайп-календар: сервер є в списку — прокрутити й підсвітити; немає — перейти на його сторінку ──
$('#cal')?.addEventListener('click', e => {
  const chip = e.target.closest('[data-go]');
  if (!chip || e.metaKey || e.ctrlKey) return;
  const row = $(`#list .srv[data-name="${chip.dataset.go}"]`);
  if (!row) return;
  e.preventDefault();
  row.scrollIntoView({ behavior: calm ? 'auto' : 'smooth', block: 'center' });
  row.classList.remove('flash-row'); void row.offsetWidth; row.classList.add('flash-row');
});

// ── Копіювання client.connect ──
document.addEventListener('click', async e => {
  const copy = e.target.closest('.copy');
  if (!copy) return;
  try { await navigator.clipboard.writeText(copy.dataset.ip); } catch (err) {}
  const text = copy.textContent;
  copy.classList.add('done'); copy.textContent = T.copied;
  setTimeout(() => { copy.classList.remove('done'); copy.textContent = text; }, 1800);
});

// ── Голоси — повідомлення в ігровому чаті ──
const chat = $('#chat');
if (chat) setToastRenderer(v => {
  const m = document.createElement('p');
  m.innerHTML = `<span class="who">${esc(v.nick || T.anon)}</span>: ${esc(T.voted)} <span class="srvn">${esc(v.server)}</span>`;
  chat.append(m);
  while (chat.children.length > 5) chat.firstElementChild.remove();
  setTimeout(() => { m.classList.add('out'); setTimeout(() => m.remove(), 600); }, 7000);
});

// Список: рядки з'являються каскадом при першому показі
if (!calm) $$('#list .srv').forEach((el, i) => el.animate([{ opacity: 0, transform: 'translateX(-12px)' }, { opacity: 1, transform: 'none' }], { duration: 300, delay: 200 + Math.min(i, 12) * 35, easing: 'cubic-bezier(.2,.8,.2,1)', fill: 'backwards' }));
