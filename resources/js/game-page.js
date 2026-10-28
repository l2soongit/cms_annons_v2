/*
 * Спільне для сторінок ігор з власним дизайном (games/themes/*): лічильники, фільтри без перезавантаження,
 * «Показати ще», таймери відкриття, фінал сезону, живі голоси, поява під час прокрутки.
 * Фільтри — звичайні посилання; скрипт підвантажує результат і анімує перестановку (FLIP і для рядків, і для сітки карток).
 */
const dataEl = document.getElementById('game-data');
const DATA = dataEl ? JSON.parse(dataEl.textContent) : { t: {}, feed: {} };
const T = DATA.t;
const LOCALE = DATA.locale === 'en' ? 'en-US' : 'uk-UA';

const calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const fmt = n => Math.round(n).toLocaleString(LOCALE);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const tr = (str, vars) => Object.entries(vars).reduce((s, [k, v]) => s.replaceAll(`:${k}`, v), str ?? '');

// Лічильники в банері
const countUp = (el, to, ms) => {
  if (!el) return;
  if (calm) { el.textContent = fmt(to); el.dataset.now = to; return; }
  const from = Number(el.dataset.now || 0), t0 = performance.now();
  const step = t => { const p = Math.min(1, (t - t0) / ms); el.textContent = fmt(from + (to - from) * (1 - Math.pow(1 - p, 4))); if (p < 1) requestAnimationFrame(step); };
  requestAnimationFrame(step); el.dataset.now = to;
};
const bump = el => { if (!el || calm) return; el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump'); };
setTimeout(() => $$('[data-count]').forEach(el => countUp(el, Number(el.dataset.count), 1600)), 400);

// ── Список: фільтри, сортування, пошук і «Показати ще» без перезавантаження ──
const results = $('#results');

// Рядки й картки «переїжджають» на нові місця, нові з'являються каскадом
const flip = (before) => {
  if (calm) return;
  $$('#list .srv').forEach((el, i) => {
    const was = before.get(el.dataset.name);
    const now = el.getBoundingClientRect();
    if (was === undefined) el.animate([{ opacity: 0, transform: 'translateY(14px)' }, { opacity: 1, transform: 'none' }], { duration: 450, delay: Math.min(i, 12) * 35, easing: 'cubic-bezier(.2,.8,.2,1)', fill: 'backwards' });
    else if (was.top !== now.top || was.left !== now.left) el.animate([{ transform: `translate(${was.left - now.left}px, ${was.top - now.top}px)` }, { transform: 'none' }], { duration: 550, easing: 'cubic-bezier(.2,.8,.2,1)' });
  });
};

let pending;
const load = async (url, { push = true } = {}) => {
  pending?.abort();
  pending = new AbortController();
  const before = new Map($$('#list .srv').map(el => [el.dataset.name, el.getBoundingClientRect()]));
  $('#list')?.classList.add('loading');
  try {
    const res = await fetch(url, { signal: pending.signal, headers: { Accept: 'text/html' } });
    if (!res.ok) throw new Error(res.status);
    const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
    // Фільтри мають нові посилання й лічильники; поле пошуку не чіпаємо, щоб не збити введення
    $$('.frow').forEach((row, i) => { const next = $$('.frow', doc)[i]; if (next) row.replaceWith(next); });
    const sorts = $('.sorts', doc); if (sorts) $('.sorts')?.replaceWith(sorts);
    // Інші блоки фільтрів теми (перемикач версії, меню закупівлі CS) позначені data-swap
    $$('[data-swap]').forEach(el => { const next = $(`[data-swap="${el.dataset.swap}"]`, doc); if (next) el.replaceWith(next); });
    $$('#search-form input[type=hidden]').forEach(i => i.remove());
    $$('#search-form input[type=hidden]', doc).forEach(i => $('#search-form').append(i));
    // Фільтри з ЧПУ — у шляху форми, тож адреса пошуку змінюється разом із фільтрами
    const nextForm = $('#search-form', doc);
    if (nextForm && $('#search-form')) $('#search-form').setAttribute('action', nextForm.getAttribute('action'));
    results.innerHTML = $('#results', doc)?.innerHTML ?? '';
    if (push) history.pushState({}, '', res.redirected ? res.url : url);
    flip(before);
  } catch (e) {
    if (e.name !== 'AbortError') location.href = url;
  }
};

document.addEventListener('click', e => {
  // «Голосувати» всередині рядка-посилання (L2) веде на сторінку голосування
  const vote = e.target.closest('[data-vote]');
  if (vote) { e.preventDefault(); location.href = vote.dataset.vote; return; }

  const link = e.target.closest('[data-filter-link]');
  if (link && !e.metaKey && !e.ctrlKey && !e.shiftKey) { e.preventDefault(); load(link.href); return; }

  const more = e.target.closest('#more');
  if (more) {
    e.preventDefault();
    more.textContent = '…';
    fetch(more.href, { headers: { 'X-Partial': 'list' } }).then(r => r.text()).then(html => {
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const rows = $$('#list .srv', doc);
      rows.forEach((row, i) => {
        $('#list').append(row);
        if (!calm) row.animate([{ opacity: 0, transform: 'translateY(14px)' }, { opacity: 1, transform: 'none' }], { duration: 450, delay: i * 30, easing: 'cubic-bezier(.2,.8,.2,1)', fill: 'backwards' });
      });
      const next = $('.more', doc);
      next ? $('.more').replaceWith(next) : $('.more')?.remove();
    }).catch(() => { location.href = more.href; });
  }
});

// Пошук за назвою: результат оновлюється, поки людина друкує
(() => {
  const form = $('#search-form');
  if (!form) return;
  let timer;
  const go = () => {
    const params = new URLSearchParams(new FormData(form));
    if (!params.get('q')) params.delete('q');
    load(`${form.action}${params.toString() ? '?' + params : ''}`);
  };
  form.addEventListener('submit', e => { e.preventDefault(); clearTimeout(timer); go(); });
  $('#q', form).addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(go, 350); });
})();
addEventListener('popstate', () => load(location.href, { push: false }));

// ── Скоро відкриття: стара цифра «їде» вгору, нова виїжджає знизу ──
const setDigit = (tile, text) => {
  const cur = tile.querySelector('.val:not(.out)');
  if (cur.textContent === text) return;
  if (calm) { cur.textContent = text; return; }
  cur.classList.remove('in'); cur.classList.add('out');
  setTimeout(() => cur.remove(), 450);
  const next = document.createElement('span');
  next.className = 'val in'; next.textContent = text;
  tile.append(next);
};
const tick = () => $$('.cd[data-opens]').forEach(cd => {
  const s = Math.max(0, (new Date(cd.dataset.opens).getTime() - Date.now()) / 1000);
  const v = { d: Math.floor(s / 86400), h: Math.floor(s % 86400 / 3600), m: Math.floor(s % 3600 / 60), s: Math.floor(s % 60) };
  $$('.tile', cd).forEach(t => setDigit(t, String(v[t.dataset.u]).padStart(2, '0')));
  cd.setAttribute('aria-label', tr(T.untilStart, v));
});
tick(); setInterval(tick, 1000);

// Фінал сезону — кінець місяця
(() => {
  const el = $('#season');
  if (!el) return;
  const end = new Date(DATA.seasonEnd).getTime();
  const d = DATA.locale === 'en' ? 'd' : 'д';
  const p = n => String(n).padStart(2, '0');
  const tickSeason = () => { const s = Math.max(0, Math.floor((end - Date.now()) / 1000)); el.textContent = `${Math.floor(s / 86400)}${d} ${p(Math.floor(s % 86400 / 3600))}:${p(Math.floor(s % 3600 / 60))}:${p(s % 60)}`; };
  tickSeason(); setInterval(tickSeason, 1000);
})();

// ── Живі події гри: нові голоси, онлайн ──
(() => {
  const feed = DATA.feed ?? {};
  if (!feed.url) return;
  const box = $('#toasts');
  let after = feed.after ?? 0;
  // Тема може показувати голоси по-своєму (CS — стрічкою вбивств)
  const queue = [];
  let showing = false;
  const toast = v => {
    if (customToast) { customToast(v); return; }
    const t = document.createElement('div');
    t.className = 'toast';
    const letter = (v.nick || v.server || '?').trim().charAt(0).toUpperCase();
    t.innerHTML = `<span class="av">${esc(letter)}</span><div><b>${esc(tr(T.toastVote, { server: v.server }))}</b><br><small>${esc(v.nick ? tr(T.toastFrom, { nick: v.nick }) : T.toastAnon)}</small></div>`;
    box.append(t);
    setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 400); }, 4000);
  };
  const drain = () => { if (!queue.length) { showing = false; return; } showing = true; toast(queue.shift()); setTimeout(drain, 2500); };

  const poll = async () => {
    if (document.hidden) return;
    try {
      const res = await fetch(`${feed.url}?game=${encodeURIComponent(DATA.game)}&after=${after}`, { headers: { Accept: 'application/json' } });
      if (!res.ok) return;
      const json = await res.json();
      after = json.last ?? after;
      if (json.votes?.length) {
        const votesEl = $('#g-votes');
        if (votesEl) { countUp(votesEl, Number(votesEl.dataset.now || votesEl.dataset.count) + json.votes.length, 700); bump(votesEl); }
        if (feed.enabled && !calm && (box || customToast)) { queue.push(...json.votes); if (!showing) drain(); }
      }
      Object.entries(json.servers ?? {}).forEach(([id, n]) => $$(`[data-server-votes="${id}"]`).forEach(el => { if (el.textContent !== fmt(n)) { el.textContent = fmt(n); bump(el); } }));
      const onlineEl = $('#g-online');
      if (onlineEl && json.online !== undefined && Number(onlineEl.dataset.now) !== json.online) { countUp(onlineEl, json.online, 700); bump(onlineEl); }
    } catch (e) {}
  };
  setTimeout(poll, 3000);
  setInterval(poll, 20000);
})();

// Поява під час прокрутки; смуги популярності заповнюються при появі
(() => {
  const els = $$('[data-reveal]');
  els.forEach((el, i) => el.style.setProperty('--d', i));
  const fill = root => $$('.track i', root).forEach(i => { i.style.width = i.dataset.w + '%'; });
  if (calm || !('IntersectionObserver' in window)) { els.forEach(el => { el.classList.add('in'); fill(el); }); return; }
  const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); fill(e.target); io.unobserve(e.target); } }), { threshold: .15 });
  els.forEach(el => io.observe(el));
})();

addEventListener('scroll', () => $('header')?.classList.toggle('scrolled', scrollY > 24), { passive: true });

let customToast = null;
/** Свій вигляд сповіщення про голос: fn({ nick, server, url, theme }) */
const setToastRenderer = fn => { customToast = fn; };

export { $, $$, calm, esc, setToastRenderer, load };
