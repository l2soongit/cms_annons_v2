/*
 * Головна: рух і «життя» сторінки з design/homepage/graj-home.html.
 * Дані — з #home-data (сервер), нові голоси — з /live/feed.
 */
const dataEl = document.getElementById('home-data');
const DATA = dataEl ? JSON.parse(dataEl.textContent) : { arena: {}, t: {}, feed: {}, hints: [] };
const T = DATA.t;
const LOCALE = DATA.locale === 'en' ? 'en-US' : 'uk-UA';

const calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
const $ = (s, root = document) => root.querySelector(s);
const $$ = (s, root = document) => [...root.querySelectorAll(s)];
const fmtN = n => Math.round(n).toLocaleString(LOCALE);
const rnd = (a, b) => a + Math.random() * (b - a);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const tr = (str, vars) => Object.entries(vars).reduce((s, [k, v]) => s.replaceAll(`:${k}`, v), str ?? '');

// Форма слова «голос» з рядка перекладу «голос|голоси|голосів»
const plural = new Intl.PluralRules(LOCALE);
const votesWord = n => {
  const forms = (T.votes ?? '').split('|');
  if (forms.length === 3) return forms[{ one: 0, few: 1 }[plural.select(n)] ?? 2];
  return forms[n === 1 ? 0 : forms.length - 1];
};

// 0. Сцени ігор: розмітка анімованих світів (CSS-класи .scene-*)
const SCENES = {
  l2: () => `<div class="scene scene-l2">
      <svg class="rune" viewBox="0 0 200 200" fill="none" stroke="#e8b04a" stroke-width="1.5" aria-hidden="true">
        <circle cx="100" cy="100" r="96"/><circle cx="100" cy="100" r="78" stroke-dasharray="4 6"/><circle cx="100" cy="100" r="46"/>
        <path d="M100 4 L100 196 M4 100 L196 100 M32 32 L168 168 M168 32 L32 168"/>
        <path d="M100 54 L140 124 L60 124 Z M100 146 L60 76 L140 76 Z"/>
      </svg>
      <svg class="castle" viewBox="0 0 400 130" preserveAspectRatio="none" aria-hidden="true"><path fill="#120903" d="M0 130 V92 h18 v-12 h8 v12 h10 v-12 h8 v12 h16 V58 h10 v-10 h6 v10 h10 v-10 h6 v10 h10 V96 h40 V40 h12 V20 l12 -18 l12 18 V40 h12 V96 h40 V60 h10 v-10 h6 v10 h10 v-10 h6 v10 h10 v34 h16 v-12 h8 v12 h10 v-12 h8 v12 h18 v38 Z"/><rect x="194" y="70" width="12" height="20" rx="6" fill="#e8b04a" opacity=".8"/></svg>
      ${Array.from({ length: 10 }, () => `<i class="mote" style="left:${rnd(5, 95)}%;bottom:${rnd(10, 40)}%;animation-delay:-${rnd(0, 6)}s;animation-duration:${rnd(4, 8)}s"></i>`).join('')}
    </div>`,
  gta: () => `<div class="scene scene-gta">
      <span class="sun"></span>
      <svg class="city" viewBox="0 0 400 140" preserveAspectRatio="none" aria-hidden="true"><path fill="#14031f" d="M0 140 V80 h20 V50 h18 v30 h12 V30 h22 v50 h10 V60 h16 v-20 h14 v40 h12 V20 h8 V8 h6 v12 h8 v60 h14 V44 h20 v36 h10 V64 h18 v-26 h16 v42 h14 V24 h24 v56 h10 V52 h20 v28 h14 V36 h18 v44 h16 v60 Z"/>
        <g fill="#ff4fa3" opacity=".7">${Array.from({ length: 24 }, () => `<rect x="${Math.round(rnd(10, 390))}" y="${Math.round(rnd(40, 120))}" width="3" height="4"/>`).join('')}</g></svg>
      <svg class="palm" viewBox="0 0 100 200" aria-hidden="true"><path d="M52 200 C50 150 46 100 54 60" stroke="#14031f" stroke-width="7" fill="none"/><g fill="#14031f"><path d="M54 60 C30 40 8 48 0 64 C20 52 38 54 54 62Z"/><path d="M54 60 C76 38 96 44 100 60 C82 50 68 52 54 62Z"/><path d="M54 60 C40 30 44 10 58 0 C52 22 56 40 56 60Z"/><path d="M54 60 C70 70 80 92 78 110 C70 90 62 76 52 64Z"/><path d="M54 60 C34 70 24 92 26 110 C34 90 44 76 56 64Z"/></g></svg>
    </div>`,
  mc: () => `<div class="scene scene-mc">
      <span class="cloud" style="top:14%;width:54px;animation-delay:-4s"></span>
      <span class="cloud" style="top:28%;width:36px;animation-delay:-15s;animation-duration:34s"></span>
      <div class="cube-wrap"><div class="cube"><i></i><i></i><i></i><i></i><i></i><i></i></div></div>
    </div>`,
  dayz: () => `<div class="scene scene-dayz">
      <span class="fog f1"></span>
      <svg class="trees" viewBox="0 0 400 200" preserveAspectRatio="none" aria-hidden="true">
        <g fill="#232b1b" opacity=".8">${[20, 70, 130, 190, 250, 310, 370].map(x => `<path d="M${x} 200 L${x - 18} 200 L${x} 70 L${x + 18} 200 Z M${x} 200 L${x - 24} 200 L${x} 110 L${x + 24} 200Z"/>`).join('')}</g>
        <g fill="#141a10">${[0, 45, 100, 160, 215, 280, 340, 395].map(x => `<path d="M${x} 200 L${x - 26} 200 L${x} 40 L${x + 26} 200 Z"/>`).join('')}</g>
      </svg>
      <span class="fog f2"></span>
    </div>`,
  rust: () => `<div class="scene scene-rust">
      <span class="glowfire"></span>
      <div class="fire"><span class="flame"></span><span class="flame f2"></span></div>
    </div>`,
  cs: () => `<div class="scene scene-cs">
      <span class="scan"></span><span class="radar"></span>
      <svg class="cross" viewBox="0 0 40 40" stroke="#4fc3f7" stroke-width="2" aria-hidden="true"><path d="M20 0v14M20 26v14M0 20h14M26 20h14"/><circle cx="20" cy="20" r="1.5" fill="#4fc3f7"/></svg>
      <i class="blip" style="left:58%;top:30%"></i><i class="blip" style="left:41%;top:46%;animation-delay:-1.6s"></i>
    </div>`,
  default: () => '<div class="scene scene-default"></div>',
};
const scene = (key, image) => image
  ? `<div class="scene scene-img" style="background-image:url('${esc(encodeURI(image))}')"></div>`
  : (SCENES[key] ?? SCENES.default)();

// Галерея світів: сервер ставить порожні .scene, сюди підставляємо анімовану розмітку
$$('[data-scene]').forEach(el => { el.outerHTML = scene(el.dataset.scene); });

// 1. Заголовок з'являється по словах
(() => {
  const h1 = $('#h1');
  if (!h1) return;
  let i = 0;
  [...h1.childNodes].forEach(node => {
    if (node.nodeType === Node.TEXT_NODE) {
      const frag = document.createDocumentFragment();
      node.textContent.split(/(\s+)/).forEach(part => {
        if (!part.trim()) { frag.append(part); return; }
        const w = document.createElement('span');
        w.className = 'w'; w.style.setProperty('--i', i++); w.textContent = part;
        frag.append(w);
      });
      node.replaceWith(frag);
    } else if (node.nodeType === Node.ELEMENT_NODE) {
      node.classList.add('w'); node.style.setProperty('--i', i++);
    }
  });
})();

// 2. Цифри рахують вгору
const countUp = (el, to, ms = 1600) => {
  if (!el) return;
  if (calm) { el.textContent = fmtN(to); el.dataset.now = to; return; }
  const from = Number(el.dataset.now || 0), t0 = performance.now();
  const step = t => {
    const p = Math.min(1, (t - t0) / ms), e = 1 - Math.pow(1 - p, 4);
    el.textContent = fmtN(from + (to - from) * e);
    if (p < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
  el.dataset.now = to;
};
setTimeout(() => $$('[data-count]').forEach(el => countUp(el, Number(el.dataset.count), 1800)), 500);
const bump = el => { if (!el || calm) return; el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump'); };

// 3. Підказки в пошуку друкуються самі
(() => {
  const input = $('#q');
  const hints = DATA.hints ?? [];
  if (calm || !input || !hints.length) return;
  let h = 0, c = 0, del = false;
  input.classList.add('typing');
  const type = () => {
    if (document.activeElement === input || input.value) { setTimeout(type, 600); return; }
    const word = hints[h];
    c += del ? -1 : 1;
    input.placeholder = (T.hintPrefix ?? '') + word.slice(0, c) + '▍';
    let wait = del ? 28 : 55;
    if (!del && c === word.length) { del = true; wait = 1600; }
    else if (del && c === 0) { del = false; h = (h + 1) % hints.length; wait = 300; }
    setTimeout(type, wait);
  };
  setTimeout(type, 1500);
})();

// 4. Топ місяця: чемпіон, срібло/бронза, погоня 4–6; вкладки ігор, живі голоси й перестановки
const crownSvg = '<svg class="crown" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 7l4.5 4L12 4l4.5 7L21 7l-2 12H5z"/></svg>';
const ARENA = DATA.arena ?? {};
let arenaGame = Object.keys(ARENA)[0];

const renderArena = (animateEntry = true) => {
  const box = $('#arena-body');
  if (!box || !ARENA[arenaGame]) return;
  const list = ARENA[arenaGame].rows;
  // Порядок як у рейтингу сайту — за балами
  list.sort((a, b) => b.score - a.score);
  const [c, s, b] = list;
  if (!c) { box.innerHTML = ''; return; }
  const lead = Math.max(1, c.votes);
  const before = new Map($$('[data-name]', box).map(el => [el.dataset.name, el.getBoundingClientRect().top]));
  const pct = v => Math.max(4, Math.round(v / lead * 100));
  const logo = r => r.logo ? `<img class="slogo" src="${esc(r.logo)}" alt="" width="28" height="28">` : '';
  const trend = r => r.day > 0 ? `<span class="tr up num">+${fmtN(r.day)}</span>` : '<span class="tr flat">—</span>';
  const detail = r => [r.game_short, r.details].filter(Boolean).join(' · ');

  box.innerHTML = `
    <a class="ring champ g-${esc(c.theme)}" href="${esc(c.url)}" data-name="${c.id}" style="--k:0">
      <div class="inner">
        ${scene(c.theme, c.image)}<span class="sweep" aria-hidden="true"></span>
        <span class="rank">${crownSvg} ${esc(T.firstPlace)}</span>
        <div><h3 class="gtitle">${esc(c.name)}</h3><div class="sub"><span>${esc(detail(c))}</span>${c.online ? `<span class="on num">${esc(tr(T.online, { n: fmtN(c.players) }))}</span>` : ''}</div></div>
        <div class="score"><b class="num" data-votes>${fmtN(c.votes)}</b><small>${esc(votesWord(c.votes))} ${esc(T.perMonth)}</small>${c.day ? `<br><span class="trend num">${esc(tr(T.perDay, { n: fmtN(c.day) }))}</span>` : ''}</div>
      </div>
    </a>
    ${s ? `<div class="runners">
      ${[[s, 'silver', 2], [b, 'bronze', 3]].filter(([r]) => r).map(([r, cls, n], k) => `
        <a class="ring runner ${cls} g-${esc(r.theme)}" href="${esc(r.url)}" data-name="${r.id}" style="--k:${k + 1}">
          <div class="inner">
            <div class="top"><span class="medal-badge">${n}</span>${logo(r)}<div style="min-width:0"><b class="n gtitle">${esc(r.name)}</b><span class="gtag">${esc(r.game_short)}</span></div></div>
            <div class="votes"><strong class="num">${fmtN(r.votes)}</strong><span class="num">${esc(tr(T.toLeader, { n: fmtN(c.votes - r.votes) }))}</span></div>
            <div class="gap-bar"><i data-w="${pct(r.votes)}"></i></div>
          </div>
        </a>`).join('')}
    </div>` : ''}
    <div class="chasers">
      ${list.slice(3, 6).map((r, k) => `
        <a class="chaser g-${esc(r.theme)}" href="${esc(r.url)}" data-name="${r.id}" style="--k:${k + 3}">
          <span class="n">${k + 4}</span>
          <div class="who">${logo(r)}<div style="min-width:0"><b>${esc(r.name)}</b><span class="gtag">${esc(r.game_short)}</span></div></div>
          <div class="gap-bar"><i data-w="${pct(r.votes)}"></i></div>
          ${trend(r)}
        </a>`).join('')}
    </div>`;

  // Смуги «до лідера» заповнюються після вставки
  requestAnimationFrame(() => requestAnimationFrame(() => $$('.gap-bar i', box).forEach(i => { i.style.width = i.dataset.w + '%'; })));

  if (!animateEntry) {
    $$('.champ, .runner, .chaser', box).forEach(el => { el.style.animationName = el.classList.contains('chaser') ? 'none' : 'spin'; });
    if (calm) return;
    // FLIP: тим, хто змінив позицію, показуємо переміщення
    $$('[data-name]', box).forEach(el => {
      const was = before.get(el.dataset.name);
      const dy = was === undefined ? 0 : was - el.getBoundingClientRect().top;
      if (dy) {
        el.animate([{ transform: `translateY(${dy}px)` }, { transform: 'none' }], { duration: 700, easing: 'cubic-bezier(.2,.8,.2,1)' });
        if (el.classList.contains('chaser')) { el.classList.add('flash'); setTimeout(() => el.classList.remove('flash'), 1400); }
      }
    });
  }
};

// Перемикання вкладок з плавною зміною вмісту
$$('#arena-tabs button').forEach(btn => btn.addEventListener('click', () => {
  if (btn.dataset.game === arenaGame) return;
  $$('#arena-tabs button').forEach(b => b.setAttribute('aria-pressed', String(b === btn)));
  const body = $('#arena-body');
  body.classList.add('switching');
  setTimeout(() => { arenaGame = btn.dataset.game; renderArena(true); body.classList.remove('switching'); }, calm ? 0 : 250);
}));
renderArena(true);

// Відлік до фіналу сезону — до кінця поточного місяця
(() => {
  const el = $('#season-left');
  if (!el) return;
  const end = new Date(DATA.seasonEnd).getTime();
  const d = DATA.locale === 'en' ? 'd' : 'д';
  const p = n => String(n).padStart(2, '0');
  const tick = () => {
    const s = Math.max(0, Math.floor((end - Date.now()) / 1000));
    el.textContent = `${Math.floor(s / 86400)}${d} ${p(Math.floor(s % 86400 / 3600))}:${p(Math.floor(s % 3600 / 60))}:${p(s % 60)}`;
  };
  tick(); setInterval(tick, 1000);
})();

// Рейтинг: вкладки ігор перемикають готові групи рядків
$$('#rank-chips button').forEach(btn => btn.addEventListener('click', () => {
  $$('#rank-chips button').forEach(b => b.setAttribute('aria-pressed', String(b === btn)));
  $$('tbody[data-group]').forEach(tb => { tb.hidden = tb.dataset.group !== btn.dataset.group; });
}));

// Відлік до відкриття: щосекунди оновлюємо лише ті цифри, що змінилися, — вони «перевертаються»
(() => {
  const boxes = $$('.count[data-opens]');
  if (!boxes.length) return;
  const [ud, uh, um] = T.units ?? [];
  const tick = () => boxes.forEach(box => {
    const left = Math.max(0, (new Date(box.dataset.opens).getTime() - Date.now()) / 1000);
    const v = { d: Math.floor(left / 86400), h: Math.floor(left % 86400 / 3600), m: Math.floor(left % 3600 / 60), s: Math.floor(left % 60) };
    box.querySelectorAll('b').forEach(b => {
      const text = b.dataset.u === 'd' ? String(v.d) : String(v[b.dataset.u]).padStart(2, '0');
      if (b.textContent !== text) { b.textContent = text; b.classList.remove('flip'); void b.offsetWidth; b.classList.add('flip'); }
    });
    box.setAttribute('aria-label', `${v.d} ${ud} ${v.h} ${uh} ${v.m} ${um}`);
  });
  tick(); setInterval(tick, 1000);
})();

// 5. Живі події: нові голоси, онлайн, цифри топу
(() => {
  const feed = DATA.feed ?? {};
  if (!feed.url) return;
  const box = $('#toasts');
  let after = feed.after ?? 0;
  const queue = [];
  let showing = false;

  const toast = v => {
    const t = document.createElement('div');
    t.className = 'toast';
    const letter = (v.nick || v.server || '?').trim().charAt(0).toUpperCase();
    t.innerHTML = `<span class="av">${esc(letter)}</span><div><b>${esc(tr(T.toastVote, { server: v.server }))}</b><small>${esc(v.nick ? tr(T.toastFrom, { nick: v.nick }) : T.toastAnon)}</small></div>`;
    box.append(t);
    setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 400); }, 4200);
  };
  const drain = () => {
    if (!queue.length) { showing = false; return; }
    showing = true;
    toast(queue.shift());
    setTimeout(drain, 2500);
  };

  // Голоси місяця з відповіді → у дані топу, таблицю і «+N» над карткою
  const applyServers = servers => {
    let gainActive = null;
    Object.values(ARENA).forEach(tab => tab.rows.forEach(r => {
      const now = servers[r.id];
      if (now === undefined || now <= r.votes) return;
      const gain = now - r.votes;
      r.day += gain;
      r.score += gain;
      r.votes = now;
      if (tab === ARENA[arenaGame]) gainActive = gainActive ?? { id: r.id, gain };
    }));
    Object.entries(servers).forEach(([id, n]) => $$(`[data-server-votes="${id}"]`).forEach(td => { td.textContent = fmtN(n); }));
    if (!gainActive) return;

    renderArena(false);
    const card = $(`#arena-body [data-name="${gainActive.id}"]`);
    if (card && !calm) {
      const pop = document.createElement('span');
      pop.className = 'vote-pop';
      pop.textContent = `+${gainActive.gain}`;
      (card.querySelector('.inner') ?? card).append(pop);
      setTimeout(() => pop.remove(), 1300);
    }
    const updated = $('#arena-updated');
    if (updated) updated.textContent = T.updated;
  };

  const poll = async () => {
    if (document.hidden) return;
    try {
      const res = await fetch(`${feed.url}?after=${after}`, { headers: { Accept: 'application/json' } });
      if (!res.ok) return;
      const json = await res.json();
      after = json.last ?? after;
      if (json.votes?.length) {
        const votesEl = $('#votes');
        if (votesEl) { countUp(votesEl, Number(votesEl.dataset.now || votesEl.dataset.count) + json.votes.length, 700); bump(votesEl); }
        if (feed.enabled && !calm && box) { queue.push(...json.votes); if (!showing) drain(); }
      }
      applyServers(json.servers ?? {});
      const onlineEl = $('#online');
      if (onlineEl && json.online !== undefined && Number(onlineEl.dataset.now) !== json.online) { countUp(onlineEl, json.online, 700); bump(onlineEl); }
    } catch (e) {}
  };
  setTimeout(poll, 3000);
  setInterval(poll, 20000);
})();

// 6. Поява блоків під час прокрутки, з каскадом усередині групи
(() => {
  const groups = ['.head', '.world', '.table-card', '.soon-card', '.steps li', '.stat-card', '.feature', '.plan', '.cta', '.owners > div > h2', '.owners > div > p'];
  groups.forEach(sel => $$(sel).forEach((el, i) => { el.dataset.reveal = ''; el.style.setProperty('--d', i % 6); }));
  const targets = [...$$('[data-reveal]'), ...$$('tbody tr')];
  if (calm || !('IntersectionObserver' in window)) { targets.forEach(el => el.classList.add('in')); return; }
  const io = new IntersectionObserver(entries => entries.forEach(e => {
    if (!e.isIntersecting) return;
    e.target.classList.add('in');
    io.unobserve(e.target);
  }), { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
  targets.forEach(el => io.observe(el));
})();

// 7. Підсвітка під курсором на картках
$$('.feature, .plan, .soon-card, .stat-card, .table-card, .arena, .steps li').forEach(el => {
  el.classList.add('spot');
  el.addEventListener('pointermove', e => {
    const r = el.getBoundingClientRect();
    el.style.setProperty('--mx', `${e.clientX - r.left}px`);
    el.style.setProperty('--my', `${e.clientY - r.top}px`);
  });
});

// 8. 3D-нахил і відблиск на картках ігор
$$('.world').forEach(tile => {
  if (calm) return;
  tile.addEventListener('pointermove', e => {
    const r = tile.getBoundingClientRect();
    const x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height;
    tile.style.transform = `rotateY(${(x - 0.5) * 10}deg) rotateX(${(0.5 - y) * 10}deg) translateZ(0)`;
    tile.style.setProperty('--mx', `${x * 100}%`);
    tile.style.setProperty('--my', `${y * 100}%`);
  });
  tile.addEventListener('pointerleave', () => { tile.style.transform = ''; });
});

// 9. Іскри першого екрана: легкий canvas, пауза у фоновій вкладці та поза екраном
(() => {
  const canvas = $('#embers');
  if (calm || !canvas) return;
  const ctx = canvas.getContext('2d');
  const dpr = Math.min(devicePixelRatio || 1, 2);
  let w, h, parts = [], visible = true;
  const spawn = anywhere => ({
    x: Math.random() * w,
    y: anywhere ? Math.random() * h : h + 10,
    r: 0.6 + Math.random() * 1.8,
    vy: 0.15 + Math.random() * 0.45,
    drift: Math.random() * Math.PI * 2,
    hue: 25 + Math.random() * 25,   // від помаранчевого до бурштинового
    life: 0.4 + Math.random() * 0.6,
  });
  const resize = () => {
    w = canvas.clientWidth; h = canvas.clientHeight;
    canvas.width = w * dpr; canvas.height = h * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    parts = Array.from({ length: Math.round(Math.min(90, w / 16)) }, () => spawn(true));
  };
  const frame = t => {
    if (!visible) return;
    ctx.clearRect(0, 0, w, h);
    ctx.globalCompositeOperation = 'lighter';
    for (const p of parts) {
      p.y -= p.vy;
      p.x += Math.sin(t / 1400 + p.drift) * 0.25;
      if (p.y < -10) Object.assign(p, spawn(false));
      const fade = Math.min(1, p.y / (h * 0.6)) * p.life;
      const flicker = 0.75 + Math.sin(t / 180 + p.drift * 7) * 0.25;
      const g = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.r * 4);
      g.addColorStop(0, `hsla(${p.hue}, 100%, 65%, ${fade * flicker})`);
      g.addColorStop(1, `hsla(${p.hue}, 100%, 50%, 0)`);
      ctx.fillStyle = g;
      ctx.beginPath(); ctx.arc(p.x, p.y, p.r * 4, 0, Math.PI * 2); ctx.fill();
    }
    requestAnimationFrame(frame);
  };
  resize();
  addEventListener('resize', resize);
  new IntersectionObserver(([e]) => { const was = visible; visible = e.isIntersecting && !document.hidden; if (visible && !was) requestAnimationFrame(frame); }).observe(canvas);
  document.addEventListener('visibilitychange', () => { const was = visible; visible = !document.hidden; if (visible && !was) requestAnimationFrame(frame); });
  requestAnimationFrame(frame);
})();

// 10. Шапка стискається під час прокрутки; головна кнопка заклику «дихає»
const header = $('header');
addEventListener('scroll', () => header?.classList.toggle('scrolled', scrollY > 24), { passive: true });
$('.cta .btn-hot')?.classList.add('pulse');
