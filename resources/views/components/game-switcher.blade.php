{{--
    Ігри в шапці: логотип + коротка назва (L2, GTA, WoW…). Скільки вміщається — стільки видно,
    решта автоматично переходить у меню «Ще ▾» (відкривається наведенням або кліком). Поточна гра видима завжди.
    Без скрипта — смуга з прокруткою. Без <nav>: теми стилізують «.bar nav a», тут власний вигляд.
--}}
@props(['current' => null, 'mode' => 'bar', 'label' => null, 'href' => null])
@php
    $current ??= request()->route('game');
    $games = \App\Support\GameNav::items();
    $currentSlug = $current instanceof \App\Models\Game ? $current->slug : null;
    $num = fn (int $n) => number_format($n, 0, ',', ' ');
    $badge = fn (array $g) => $g['icon'] ? '<img src="'.e($g['icon']).'" alt="" loading="lazy">' : e(mb_substr($g['short'], 0, 1));
@endphp
@if ($mode === 'menu')
{{-- Головна: пункт меню «Ігри», список випадає при наведенні --}}
<span class="gn gn-solo" data-gn data-mode="menu">
    <span class="gn-more is-solo" data-gn-more>
        <a class="gn-more-btn" href="{{ $href ?? '#games' }}" aria-expanded="false" aria-haspopup="true">{{ $label ?? __('ui.switcher.label') }}
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></a>
        <span class="gn-menu">
            <span class="gn-grid">
                @foreach ($games as $g)
                    <a href="{{ $g['url'] }}" style="--c1:{{ $g['c1'] }};--c2:{{ $g['c2'] }}">
                        <span @class(['gn-badge', 'has-img' => $g['icon']]) aria-hidden="true">{!! $badge($g) !!}</span>
                        <span class="gn-txt"><b>{{ $g['name'] }}</b><small>{{ trans_choice('ui.switcher.servers', $g['servers'], ['n' => $num($g['servers'])]) }}@if ($g['online']) · <em>{{ __('ui.switcher.online', ['n' => $num($g['online'])]) }}</em>@endif</small></span>
                    </a>
                @endforeach
            </span>
        </span>
    </span>
</span>
@else
<div class="gn" role="navigation" aria-label="{{ __('ui.switcher.label') }}" data-gn>
    <ul class="gn-list">
        @foreach ($games as $g)
            <li data-slug="{{ $g['slug'] }}">
                <a class="gn-item" href="{{ $g['url'] }}" title="{{ $g['name'] }}{{ $g['online'] ? ' · '.__('ui.switcher.online', ['n' => $num($g['online'])]) : '' }}"
                   style="--c1:{{ $g['c1'] }};--c2:{{ $g['c2'] }}" @if ($g['slug'] === $currentSlug) aria-current="page" @endif>
                    <span @class(['gn-badge', 'has-img' => $g['icon']]) aria-hidden="true">{!! $badge($g) !!}@if ($g['online'])<i class="gn-on"></i>@endif</span>
                    <span class="gn-short">{{ $g['short'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
    <div class="gn-more" data-gn-more hidden>
        <button type="button" class="gn-more-btn" aria-expanded="false" aria-haspopup="true">
            <span class="gn-stack" data-gn-stack aria-hidden="true"></span>{{ __('ui.switcher.more') }}
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div class="gn-menu">
            <ul>
                @foreach ($games as $g)
                    <li data-slug="{{ $g['slug'] }}">
                        <a href="{{ $g['url'] }}" style="--c1:{{ $g['c1'] }};--c2:{{ $g['c2'] }}" @if ($g['slug'] === $currentSlug) aria-current="page" @endif>
                            <span @class(['gn-badge', 'has-img' => $g['icon']]) aria-hidden="true">{!! $badge($g) !!}</span>
                            <span class="gn-txt"><b>{{ $g['name'] }}</b><small>{{ trans_choice('ui.switcher.servers', $g['servers'], ['n' => $num($g['servers'])]) }}@if ($g['online']) · <em>{{ __('ui.switcher.online', ['n' => $num($g['online'])]) }}</em>@endif</small></span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endif

@once
<style>
.gn{position:relative;display:flex;align-items:center;gap:4px;flex:1 1 0;min-width:0;margin:0 auto 0 0;font-size:14px;line-height:1.2}
.gn ul{list-style:none;margin:0;padding:0}
.gn-list{display:flex;gap:2px;min-width:0;overflow-x:auto;scrollbar-width:none}
.gn-list::-webkit-scrollbar{display:none}
.gn.is-fit .gn-list{overflow:visible}
.gn-list li{flex:none}
.gn li[hidden]{display:none}
.gn .gn-item{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:999px;color:inherit;opacity:.75;text-decoration:none;font-weight:700;white-space:nowrap;border:1px solid transparent;transition:opacity .15s,background-color .15s,border-color .15s}
.gn .gn-item:hover{opacity:1;background:color-mix(in srgb,var(--c1) 12%,transparent)}
.gn .gn-item[aria-current]{opacity:1;background:color-mix(in srgb,var(--c1) 16%,transparent);border-color:color-mix(in srgb,var(--c1) 45%,transparent)}
.gn .gn-item:focus-visible,.gn .gn-more-btn:focus-visible,.gn-menu a:focus-visible{outline:2px solid var(--c1,#fbbf24);outline-offset:2px}
.gn-badge{position:relative;flex:none;display:grid;place-items:center;width:26px;height:26px;border-radius:8px;font:800 12px/1 inherit;color:#0b0b0f;background:radial-gradient(circle at 32% 28%,#ffffffa0,transparent 48%),linear-gradient(140deg,var(--c1),var(--c2));box-shadow:0 0 10px color-mix(in srgb,var(--c1) 30%,transparent);transition:transform .2s,box-shadow .2s}
.gn-badge.has-img{background:#0b0b0f;box-shadow:0 0 0 1px color-mix(in srgb,var(--c1) 40%,transparent),0 0 10px color-mix(in srgb,var(--c1) 25%,transparent)}
.gn-badge img{width:100%;height:100%;border-radius:inherit;object-fit:cover}
.gn .gn-item:hover .gn-badge,.gn .gn-item[aria-current] .gn-badge{transform:scale(1.08);box-shadow:0 0 0 1px var(--c1),0 0 14px color-mix(in srgb,var(--c1) 55%,transparent)}
.gn-on{position:absolute;right:-2px;bottom:-2px;width:8px;height:8px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 2px #121216}
.gn-more{position:relative;flex:none}
.gn-more[hidden]{display:none}
.gn .gn-more-btn{display:flex;align-items:center;gap:6px;padding:5px 10px 5px 6px;border-radius:999px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.04);color:inherit;font:700 14px/1.2 inherit;cursor:pointer;white-space:nowrap}
.gn .gn-more-btn:hover,.gn-more.is-open .gn-more-btn{background:rgba(255,255,255,.09);border-color:rgba(255,255,255,.26)}
.gn-more-btn svg{opacity:.7;transition:transform .2s}
.gn-more.is-open .gn-more-btn svg{transform:rotate(180deg)}
.gn-stack{display:flex;padding-left:7px}
.gn-stack i{width:18px;height:18px;margin-left:-7px;border-radius:6px;background:linear-gradient(140deg,var(--c1),var(--c2)) center/cover;box-shadow:0 0 0 2px #121216}
.gn-menu{position:absolute;z-index:70;top:calc(100% + 10px);right:0;min-width:290px;padding:8px;border-radius:16px;border:1px solid rgba(255,255,255,.1);background:rgba(16,16,20,.98);color:#f4f4f5;box-shadow:0 24px 60px -16px #000d;opacity:0;visibility:hidden;transform:translateY(-6px);transition:opacity .18s,transform .18s,visibility .18s}
.gn-menu::before{content:"";position:absolute;left:0;right:0;top:-12px;height:12px}
.gn-more.is-open .gn-menu{opacity:1;visibility:visible;transform:none}
.gn .gn-menu a{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:10px;color:inherit;text-decoration:none}
.gn .gn-menu a:hover,.gn .gn-menu a[aria-current]{background:color-mix(in srgb,var(--c1) 14%,transparent)}
.gn-menu .gn-badge{width:32px;height:32px;font-size:14px;border-radius:9px}
.gn-txt{display:flex;flex-direction:column;gap:2px;min-width:0}
.gn-txt b{font-weight:700;white-space:nowrap}
.gn-txt small{font-size:12px;color:#a1a1aa;white-space:nowrap}
.gn-txt em{font-style:normal;color:#86efac}
/* Головна: «Ігри» як звичайний пункт меню */
.gn.gn-solo{display:inline-flex;flex:none;margin:0}
.gn .is-solo .gn-more-btn{padding:0;border:0;background:none;font:inherit;color:inherit;gap:5px;border-radius:0}
.gn .is-solo .gn-more-btn:hover,.gn .is-solo.is-open .gn-more-btn{background:none;color:var(--text,#fff)}
.gn .is-solo .gn-menu{left:50%;right:auto;transform:translate(-50%,-6px);width:min(560px,92vw)}
.gn .is-solo.is-open .gn-menu{transform:translate(-50%,0)}
.gn-grid{display:grid;grid-template-columns:1fr 1fr;gap:2px}
/* Мова й вхід у підвалі сторінок гри й сервера */
.foot-tools{display:inline-flex;align-items:center;gap:16px}
.foot-tools a{color:inherit}
@media (max-width:640px){.gn .gn-item{padding:3px 8px 3px 3px;gap:5px}.gn-badge{width:22px;height:22px;font-size:10px}.gn-menu{position:fixed;left:8px;right:8px;top:auto;min-width:0}}
@media (prefers-reduced-motion:reduce){.gn *{transition:none!important}}
</style>
<script>
(() => {
    const root = document.querySelector('[data-gn]');
    if (!root) return;
    const list = root.querySelector('.gn-list'), more = root.querySelector('[data-gn-more]'), btn = more.querySelector('.gn-more-btn');
    const solo = root.dataset.mode === 'menu';
    const hover = () => matchMedia('(hover: hover)').matches;
    const items = solo ? [] : [...list.children], menuItems = [...more.querySelectorAll('.gn-menu li')], stack = root.querySelector('[data-gn-stack]');
    const current = items.find((li) => li.querySelector('[aria-current]'));
    root.classList.add('is-fit');

    const sync = (hide) => {
        if (solo) return;
        items.forEach((li) => { li.hidden = hide.includes(li); });
        const slugs = hide.map((li) => li.dataset.slug);
        menuItems.forEach((li) => { li.hidden = !slugs.includes(li.dataset.slug); });
        more.hidden = hide.length === 0;
        stack.replaceChildren(...hide.slice(0, 3).map((li) => {
            const i = document.createElement('i');
            const img = li.querySelector('img');
            i.style.cssText = li.querySelector('a').getAttribute('style') + (img ? `;background-image:url("${img.src}")` : '');
            return i;
        }));
    };

    // Скільки значків уміщається: решта — у «Ще», поточна гра лишається видимою
    const fit = () => {
        if (solo) return;
        sync([]);
        const room = root.clientWidth;
        if (list.scrollWidth <= room) return;
        more.hidden = false;
        const avail = room - more.offsetWidth - 44; // + місце під значки прихованих ігор на кнопці «Ще»
        const width = (li) => li.offsetWidth + 2;
        let used = current ? width(current) : 0;
        const hide = [];
        items.forEach((li) => {
            if (li === current) return;
            if (!hide.length && used + width(li) <= avail) used += width(li);
            else hide.push(li);
        });
        sync(hide);
    };

    // Меню: наведення на комп'ютері, клік — скрізь
    let timer;
    const set = (open) => { more.classList.toggle('is-open', open); btn.setAttribute('aria-expanded', String(open)); };
    more.addEventListener('mouseenter', () => { clearTimeout(timer); if (hover()) set(true); });
    more.addEventListener('mouseleave', () => { timer = setTimeout(() => set(false), 200); });
    // «Ігри» на головній — посилання на блок ігор: на дотиковому екрані перший дотик відкриває список
    btn.addEventListener('click', (e) => {
        if (solo && (hover() || more.classList.contains('is-open'))) return;
        e.preventDefault();
        set(!more.classList.contains('is-open'));
    });
    document.addEventListener('click', (e) => { if (!more.contains(e.target)) set(false); });
    more.addEventListener('keydown', (e) => { if (e.key === 'Escape') { set(false); btn.focus(); } });
    more.addEventListener('focusout', (e) => { if (!more.contains(e.relatedTarget)) set(false); });

    let last = 0;
    new ResizeObserver(() => { if (root.clientWidth !== last) { last = root.clientWidth; requestAnimationFrame(fit); } }).observe(root);
    addEventListener('load', fit);
})();
</script>
@endonce
