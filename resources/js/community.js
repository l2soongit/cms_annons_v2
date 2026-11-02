/*
 * Спільнота: оцінки ▲▼ і підписки без перезавантаження, цитування, редагування відповіді,
 * кнопки розмітки, фільтри стрічки, поля форми теми за типом, варіанти опитування.
 * Без діалогів браузера: повідомлення — у тостах (#toasts з шапки).
 */
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const csrf = () => $('meta[name="csrf-token"]')?.content ?? $('input[name="_token"]')?.value ?? '';

const toast = text => {
  const box = $('#toasts');
  if (!box || !text) return;
  const el = document.createElement('div');
  el.className = 'toast';
  el.textContent = text;
  box.append(el);
  setTimeout(() => el.remove(), 3500);
};

const post = (url, data) => fetch(url, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
  credentials: 'same-origin',
  body: JSON.stringify(data),
});

const base = document.documentElement.dataset.communityBase || `${location.pathname.split('/community')[0]}/community`;

// Оцінки ▲▼: повторне натискання знімає оцінку
document.addEventListener('click', async e => {
  const btn = e.target.closest('[data-react] button[data-value]');
  if (!btn || btn.disabled) return;
  const box = btn.closest('[data-react]');
  btn.disabled = true;
  try {
    const res = await post(`${base}/react`, { target: box.dataset.react, id: Number(box.dataset.id), value: Number(btn.dataset.value) });
    const json = await res.json().catch(() => ({}));
    if (json.error) toast(json.error);
    if (res.ok) {
      box.dataset.mine = json.mine;
      const n = box.querySelector('b');
      if (n) n.textContent = json.score;
    }
  } finally {
    btn.disabled = false;
  }
});

// Підписки на тему, розділ, сервер
document.addEventListener('click', async e => {
  const btn = e.target.closest('[data-subscribe]');
  if (!btn) return;
  btn.disabled = true;
  try {
    const res = await post(`${base}/subscribe`, { target: btn.dataset.subscribe, id: Number(btn.dataset.id) });
    if (res.ok) {
      const json = await res.json();
      btn.setAttribute('aria-pressed', json.subscribed ? 'true' : 'false');
      btn.textContent = json.subscribed ? btn.dataset.on : btn.dataset.off;
    }
  } finally {
    btn.disabled = false;
  }
});

// Поділитися: системне меню телефона або копіювання посилання
document.addEventListener('click', async e => {
  const btn = e.target.closest('[data-share]');
  if (!btn) return;
  const url = btn.dataset.share;
  if (navigator.share) {
    navigator.share({ title: btn.dataset.title, url }).catch(() => {});
  } else {
    await navigator.clipboard?.writeText(url);
    toast(btn.dataset.done);
  }
});

// Цитата: підставляє id відповіді в форму й переводить фокус у поле
const replyForm = $('#reply');
document.addEventListener('click', e => {
  const q = e.target.closest('[data-quote]');
  if (q && replyForm) {
    replyForm.querySelector('[name="quote_id"]').value = q.dataset.quote;
    const bar = replyForm.querySelector('.cm-quoting');
    bar.hidden = false;
    bar.querySelector('span').textContent = `❝ ${q.dataset.author}`;
    replyForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
    replyForm.querySelector('textarea').focus({ preventScroll: true });
  }
  if (e.target.closest('[data-unquote]') && replyForm) {
    replyForm.querySelector('[name="quote_id"]').value = '';
    replyForm.querySelector('.cm-quoting').hidden = true;
  }
  const ed = e.target.closest('[data-edit]');
  if (ed) {
    const form = $(`#edit-${ed.dataset.edit}`);
    if (form) {
      form.hidden = !form.hidden;
      if (!form.hidden) form.querySelector('textarea').focus();
    }
  }
});

// Кнопки розмітки: обгортають виділення або додають префікс рядкам
$$('[data-toolbar]').forEach(bar => {
  const area = document.getElementById(bar.dataset.toolbar);
  if (!area) return;
  bar.addEventListener('click', e => {
    const b = e.target.closest('button');
    if (!b) return;
    const { selectionStart: s, selectionEnd: en, value } = area;
    const sel = value.slice(s, en);
    let insert = sel;
    if (b.dataset.wrap) insert = `${b.dataset.wrap}${sel || '…'}${b.dataset.wrap}`;
    if (b.dataset.line) insert = (sel || '…').split('\n').map(l => b.dataset.line + l).join('\n');
    if ('link' in b.dataset) insert = `[${sel || '…'}](https://)`;
    area.setRangeText(insert, s, en, 'end');
    area.focus();
  });
});

// Фільтри стрічки: зміна списку одразу відправляє форму
$$('[data-autosubmit]').forEach(el => el.addEventListener('change', () => el.form.submit()));

// Форма теми: типи за розділом, поля сервера й клану лише для своїх типів, рядки опитування
const form = $('.cm-form');
if (form) {
  const types = JSON.parse(form.dataset.types || '{}');
  const cat = $('#f-category', form);
  const type = $('#f-type', form);
  const sync = () => {
    const allowed = types[cat.value] ?? null;
    [...type.options].forEach(o => { o.hidden = o.disabled = allowed !== null && !allowed.includes(o.value); });
    if (type.selectedOptions[0]?.disabled) type.value = [...type.options].find(o => !o.disabled)?.value ?? '';
    $$('[data-show-for]', form).forEach(el => { el.hidden = el.dataset.showFor !== type.value; });
  };
  cat.addEventListener('change', sync);
  type.addEventListener('change', sync);
  sync();

  const add = $('[data-add-option]', form);
  add?.addEventListener('click', () => {
    const box = $('[data-poll-options]', form);
    if (box.children.length >= 8) return;
    const input = document.createElement('input');
    input.name = 'poll_options[]';
    input.maxLength = 100;
    input.placeholder = add.dataset.label.replace(':n', box.children.length + 1);
    input.setAttribute('aria-label', input.placeholder);
    box.append(input);
    input.focus();
  });
}
