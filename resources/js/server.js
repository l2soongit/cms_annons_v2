/*
 * Сторінки сервера й голосування (design/server): сцена банера в стилі гри, графіки, скриншоти, відгуки,
 * липка панель голосу; на сторінці голосу — відбиток пристрою, святкування й відлік до наступного голосу.
 */
const dataEl = document.getElementById('server-data');
const DATA = dataEl ? JSON.parse(dataEl.textContent) : {};
const T = DATA.t ?? {};
const LOCALE = { en: 'en-US', ru: 'ru-RU', pl: 'pl-PL' }[DATA.locale] ?? 'uk-UA';

const calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
// Телефони й планшети: сцени нерухомі, без постійної пульсації (легкий режим у server.css)
const lite = matchMedia('(max-width: 768px), (hover: none) and (pointer: coarse)').matches;
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const fmt = n => Math.round(n).toLocaleString(LOCALE);
const rnd = (a, b) => a + Math.random() * (b - a);
const tr = (str, vars) => Object.entries(vars).reduce((s, [k, v]) => s.replaceAll(`:${k}`, v), str ?? '');

// ── Сцени банера в стилі гри (коли власник не завантажив банер) ──
const SCENES = {
  l2: () => `<svg class="rune" viewBox="0 0 200 200" fill="none" stroke="#e8b04a" stroke-width="1.5"><circle cx="100" cy="100" r="96"/><circle cx="100" cy="100" r="78" stroke-dasharray="4 6"/><circle cx="100" cy="100" r="46"/><path d="M100 4 L100 196 M4 100 L196 100 M32 32 L168 168 M168 32 L32 168"/><path d="M100 54 L140 124 L60 124 Z M100 146 L60 76 L140 76 Z"/></svg><svg class="castle" viewBox="0 0 400 130" preserveAspectRatio="none"><path fill="#120903" d="M0 130 V92 h18 v-12 h8 v12 h10 v-12 h8 v12 h16 V58 h10 v-10 h6 v10 h10 v-10 h6 v10 h10 V96 h40 V40 h12 V20 l12 -18 l12 18 V40 h12 V96 h40 V60 h10 v-10 h6 v10 h10 v-10 h6 v10 h10 v34 h16 v-12 h8 v12 h10 v-12 h8 v12 h18 v38 Z"/><rect x="194" y="70" width="12" height="20" rx="6" fill="#e8b04a" opacity=".8"/></svg>`,
  gta: () => `<span class="sun"></span><svg class="city" viewBox="0 0 400 140" preserveAspectRatio="none"><path fill="#14031f" d="M0 140 V80 h20 V50 h18 v30 h12 V30 h22 v50 h10 V60 h16 v-20 h14 v40 h12 V20 h8 V8 h6 v12 h8 v60 h14 V44 h20 v36 h10 V64 h18 v-26 h16 v42 h14 V24 h24 v56 h10 V52 h20 v28 h14 V36 h18 v44 h16 v60 Z"/><g fill="#ff4fa3" opacity=".7">${Array.from({ length: 24 }, () => `<rect x="${Math.round(rnd(10, 390))}" y="${Math.round(rnd(40, 120))}" width="3" height="4"/>`).join('')}</g></svg>`,
  mc: () => '<span class="cloud" style="top:14%;width:54px"></span><span class="cloud" style="top:28%;width:36px;animation-delay:-18s"></span><div class="cube-wrap"><div class="cube"><i></i><i></i><i></i><i></i><i></i><i></i></div></div>',
  dayz: () => `<svg class="trees" viewBox="0 0 400 200" preserveAspectRatio="none"><g fill="#232b1b" opacity=".8">${[20, 70, 130, 190, 250, 310, 370].map(x => `<path d="M${x} 200 L${x - 18} 200 L${x} 70 L${x + 18} 200 Z"/>`).join('')}</g><g fill="#141a10">${[0, 45, 100, 160, 215, 280, 340, 395].map(x => `<path d="M${x} 200 L${x - 26} 200 L${x} 40 L${x + 26} 200 Z"/>`).join('')}</g></svg><span class="fog"></span>`,
  rust: () => '<span class="flame"></span>',
  cs: () => '<span class="radar"></span>',
};
$$('[data-scene]').forEach(el => { el.innerHTML = (SCENES[el.dataset.scene] ?? (() => ''))(); });

// Назва сервера з'являється по літерах шрифтом гри
(() => {
  const h1 = $('#sname');
  if (!h1 || calm) return;
  const text = h1.textContent;
  h1.setAttribute('aria-label', text);
  h1.innerHTML = [...text].map((c, i) => c === ' ' ? ' ' : `<span class="ch" style="--i:${i}" aria-hidden="true">${c.replace(/[<>&]/g, '')}</span>`).join('');
})();

// Лічильники
const countUp = (el, to, ms = 1400, from = Number(el.dataset.now || 0)) => {
  if (calm) { el.textContent = fmt(to); return; }
  const t0 = performance.now();
  const step = t => { const p = Math.min(1, (t - t0) / ms); el.textContent = fmt(from + (to - from) * (1 - Math.pow(1 - p, 4))); if (p < 1) requestAnimationFrame(step); };
  requestAnimationFrame(step); el.dataset.now = to;
};
setTimeout(() => $$('[data-count]').forEach(el => countUp(el, Number(el.dataset.count))), 300);

// Копіювання адреси / команди підключення
document.addEventListener('click', async e => {
  const b = e.target.closest('[data-copy]');
  if (!b) return;
  try { await navigator.clipboard.writeText(b.dataset.copy); } catch (err) {}
  const text = b.textContent;
  b.classList.add('ok'); b.textContent = T.copied ?? '✓';
  setTimeout(() => { b.classList.remove('ok'); b.textContent = text; }, 1600);
});

// Опис: згорнути довгий, кнопка «Показати повністю»
(() => {
  const desc = $('#desc'), more = $('#desc-more');
  if (!desc || !more) return;
  if (desc.scrollHeight <= 260) { more.remove(); return; }
  desc.classList.add('clamp');
  more.hidden = false;
  more.addEventListener('click', () => { const closed = desc.classList.toggle('clamp'); more.textContent = closed ? T.more : T.less; });
})();

// ── Графіки онлайну й голосів ──
(() => {
  const charts = DATA.charts;
  if (!charts || !$('#plot-online')) return;
  let period = 'week';
  const gc = getComputedStyle(document.body).getPropertyValue('--gc').trim();
  const axis = pts => {
    if (!pts.length) return '';
    const idx = [0, .25, .5, .75, 1].map(f => Math.round(f * (pts.length - 1)));
    return [...new Set(idx)].map(i => `<span>${pts[i].label}</span>`).join('');
  };
  const render = () => {
    const pts = charts[period] ?? [];
    const box = $('#plot-online'), vbox = $('#plot-votes');
    if (pts.length < 2) {
      box.innerHTML = `<p class="empty-note">${T.noData}</p>`; vbox.innerHTML = '';
      $('#axis-online').innerHTML = $('#axis-votes').innerHTML = ''; $('#avg-online').textContent = $('#sum-votes').textContent = '';
      return;
    }
    const on = pts.map(p => p.players), W = 600, H = 170, max = Math.max(1, ...on) * 1.15;
    const xy = on.map((v, i) => [i / (on.length - 1) * W, H - v / max * H]);
    const line = xy.map((p, i) => `${i ? 'L' : 'M'}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(' ');
    box.innerHTML = `<svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="none" aria-hidden="true">
      <defs><linearGradient id="gArea" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${gc}" stop-opacity=".35"/><stop offset="1" stop-color="${gc}" stop-opacity="0"/></linearGradient></defs>
      ${[.25, .5, .75].map(f => `<line class="gridl" x1="0" x2="${W}" y1="${H * f}" y2="${H * f}"/>`).join('')}
      <path class="area" d="${line} L${W},${H} L0,${H} Z"/><path class="line ${calm ? '' : 'draw'}" d="${line}" style="--len:3000"/>
      <line class="cursor" x1="0" x2="0" y1="0" y2="${H}" opacity="0"/><circle class="dot" r="5" cx="0" cy="0" opacity="0"/></svg><div class="tip"></div>`;
    $('#axis-online').innerHTML = axis(pts);
    $('#avg-online').textContent = tr(T.avg, { n: fmt(on.reduce((a, b) => a + b, 0) / on.length) });
    const svg = $('svg', box), tip = $('.tip', box);
    box.onpointermove = e => {
      const r = svg.getBoundingClientRect(), k = Math.max(0, Math.min(on.length - 1, Math.round((e.clientX - r.left) / r.width * (on.length - 1))));
      const [x, y] = xy[k];
      $('.cursor', svg).setAttribute('x1', x); $('.cursor', svg).setAttribute('x2', x); $('.cursor', svg).setAttribute('opacity', 1);
      $('.dot', svg).setAttribute('cx', x); $('.dot', svg).setAttribute('cy', y); $('.dot', svg).setAttribute('opacity', 1);
      tip.style.left = `${x / W * 100}%`; tip.style.top = `${y}px`;
      tip.innerHTML = `<b class="num">${fmt(on[k])}</b> ${T.players} · ${pts[k].label}`;
    };
    // Голоси: доба й тиждень приходять по годинах — складаємо в 24 / 7 стовпчиків
    let bars = pts.map(p => ({ v: p.votes, label: p.label }));
    if (period === 'week') bars = Array.from({ length: Math.ceil(bars.length / 24) }, (_, i) => { const s = bars.slice(i * 24, i * 24 + 24); return { v: s.reduce((a, b) => a + b.v, 0), label: s[0].label.split(' ')[0] }; });
    const vmax = Math.max(1, ...bars.map(b => b.v)), bw = W / bars.length;
    vbox.innerHTML = `<svg viewBox="0 0 ${W} 120" preserveAspectRatio="none" aria-hidden="true">${bars.map((b, i) => `<rect class="bar ${calm ? '' : 'grow'} ${i === bars.length - 1 ? 'hi' : ''}" style="--i:${i}" x="${i * bw + bw * .15}" width="${bw * .7}" y="${120 - b.v / vmax * 116}" height="${Math.max(1, b.v / vmax * 116)}" rx="3"><title>${b.label}: ${b.v}</title></rect>`).join('')}</svg>`;
    $('#axis-votes').innerHTML = axis(bars);
    $('#sum-votes').textContent = tr(T.sum, { n: fmt(bars.reduce((a, b) => a + b.v, 0)) });
  };
  $$('#period button').forEach(b => b.addEventListener('click', () => { period = b.dataset.p; $$('#period button').forEach(x => x.setAttribute('aria-pressed', String(x === b))); render(); }));
  render();
})();

// ── Скриншоти: перегляд на весь екран, стрілки й Escape ──
(() => {
  const shots = $$('.shot[data-full]'), lb = $('#lightbox');
  if (!shots.length || !lb) return;
  let idx = 0;
  const open = i => {
    idx = (i + shots.length) % shots.length;
    $('#lb-frame').innerHTML = `<img src="${shots[idx].dataset.full}" alt="${shots[idx].getAttribute('aria-label') ?? ''}">`;
    $('#lb-count').textContent = `${idx + 1} / ${shots.length}`;
    lb.classList.add('open'); $('.close', lb).focus();
  };
  const close = () => { lb.classList.remove('open'); shots[idx].focus(); };
  shots.forEach((s, i) => s.addEventListener('click', () => open(i)));
  $('.prev', lb).onclick = () => open(idx - 1);
  $('.next', lb).onclick = () => open(idx + 1);
  $('.close', lb).onclick = close;
  lb.addEventListener('click', e => { if (e.target === lb) close(); });
  addEventListener('keydown', e => {
    if (!lb.classList.contains('open')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowRight') open(idx + 1);
    if (e.key === 'ArrowLeft') open(idx - 1);
  });
})();

// ── Вибір зірок у формі відгуку (справжні radio) ──
(() => {
  const picker = $('#picker');
  if (!picker) return;
  const labels = $$('label', picker), out = $('#picker-label');
  const current = () => Number($('input:checked', picker)?.value ?? 0);
  const paint = v => labels.forEach((l, i) => l.classList.toggle('on', i < v));
  const say = v => { out.textContent = v ? T.stars[v - 1] : T.pick; };
  labels.forEach((l, i) => {
    l.addEventListener('pointerenter', () => { paint(i + 1); say(i + 1); });
    l.addEventListener('pointerleave', () => { paint(current()); say(current()); });
  });
  picker.addEventListener('change', () => { paint(current()); say(current()); });
  paint(current()); say(current());
})();

// Липка панель голосування, коли картку голосу прокручено
(() => {
  const vbar = $('#vbar'), anchor = $('#vote');
  if (!vbar || !anchor || !('IntersectionObserver' in window)) return;
  new IntersectionObserver(([e]) => { vbar.classList.toggle('show', !e.isIntersecting); vbar.setAttribute('aria-hidden', String(e.isIntersecting)); }, { rootMargin: '-80px 0px 0px 0px' }).observe(anchor);
})();

// Поява під час прокрутки; кільце аптайму, шкали й розподіл оцінок заповнюються при появі
(() => {
  const els = $$('[data-reveal]');
  els.forEach((el, i) => el.style.setProperty('--d', i % 5));
  const fill = root => {
    $$('[data-w]', root).forEach(i => { i.style.width = i.dataset.w + '%'; });
    const ring = $('#ring', root);
    if (ring) ring.style.strokeDashoffset = 226.2 * (1 - Number(ring.dataset.value) / 100);
  };
  if (calm || !('IntersectionObserver' in window)) { els.forEach(el => { el.classList.add('in'); fill(el); }); return; }
  const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); fill(e.target); io.unobserve(e.target); } }), { threshold: .12 });
  els.forEach(el => io.observe(el));
  // Шкала онлайну в банері видна одразу
  setTimeout(() => $$('.stats [data-w], .race [data-w]').forEach(i => { i.style.width = i.dataset.w + '%'; }), 200);
})();

// ══ Сторінка голосування ══

// Відбиток пристрою для обмеження голосів: лише налаштування браузера, не ідентифікує людину
(async () => {
  const form = $('[data-fingerprint-form]');
  if (!form) return;
  try {
    const parts = [navigator.userAgent, navigator.language, navigator.hardwareConcurrency, screen.width, screen.height, screen.colorDepth, Intl.DateTimeFormat().resolvedOptions().timeZone].join('|');
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(parts));
    form.elements.fingerprint.value = [...new Uint8Array(digest)].map(b => b.toString(16).padStart(2, '0')).join('');
  } catch (e) {}
  // Під час відправки — «Зараховуємо…», без подвійного кліку
  form.addEventListener('submit', () => {
    const go = $('#go');
    if (!go) return;
    go.disabled = true; go.classList.remove('pulse');
    go.innerHTML = `<span class="spin" aria-hidden="true"></span>${T.sending}`;
  });
})();

// Святкування після голосу: галочка (CSS), «+1», конфеті в кольорах гри; голоси рахують вгору
(() => {
  const card = $('.vcard'), done = $('.done');
  if (!card || !done) return;
  const total = $('#newvotes');
  if (total) countUp(total, Number(total.dataset.to), 900, Number(total.dataset.to) - 1);
  if (calm) return;
  const cs = getComputedStyle(document.body);
  const colors = [cs.getPropertyValue('--gc'), '#ffffff', '#34d399', cs.getPropertyValue('--gc2')];
  const box = document.createElement('div'); box.className = 'confetti';
  box.innerHTML = Array.from({ length: lite ? 18 : 40 }, (_, i) => {
    const a = Math.random() * Math.PI - Math.PI, r = 120 + Math.random() * 180;
    return `<i style="--c:${colors[i % 4]};--x:${Math.cos(a) * r}px;--y:${Math.sin(a) * r + 160}px;--r:${Math.random() * 720 - 360}deg;--d:${Math.random() * .15}s"></i>`;
  }).join('');
  card.append(box); setTimeout(() => box.remove(), 1900);
  const plus = document.createElement('span'); plus.className = 'plus'; plus.textContent = '+1'; card.append(plus); setTimeout(() => plus.remove(), 1500);
})();

// Відлік до наступного голосу: цифра «їде» вгору; коли час минув — сторінка оновлюється з формою
(() => {
  const cd = $('#vcd');
  if (!cd) return;
  const end = new Date(cd.dataset.until).getTime();
  const setDigit = (tile, text) => {
    const cur = tile.querySelector('.val:not(.out)');
    if (!cur || cur.textContent === text) return;
    if (calm) { cur.textContent = text; return; }
    cur.classList.add('out'); setTimeout(() => cur.remove(), 450);
    const next = document.createElement('span'); next.className = 'val in'; next.textContent = text; tile.append(next);
  };
  const tick = () => {
    const s = Math.max(0, (end - Date.now()) / 1000);
    const v = { h: Math.floor(s / 3600), m: Math.floor(s % 3600 / 60), s: Math.floor(s % 60) };
    $$('.tile', cd).forEach(t => setDigit(t, String(v[t.dataset.u]).padStart(2, '0')));
    if (s <= 0) { clearInterval(timer); setTimeout(() => location.reload(), 1200); }
  };
  const timer = setInterval(tick, 1000);
  tick();
})();

// «Поділитися» — посилання на сторінку сервера
$('#share')?.addEventListener('click', async e => {
  const b = e.currentTarget;
  try { await navigator.clipboard.writeText(b.dataset.url); } catch (err) {}
  b.textContent = T.linkCopied;
});
