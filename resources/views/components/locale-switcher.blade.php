{{-- Перемикач мови: та сама сторінка іншою мовою. Стилі вбудовані — однаково в усіх макетах.
     dropdown — кнопка з поточною мовою й випадним списком (головна), інакше — рядок кодів --}}
@props(['dropdown' => false])
@php($locales = \App\Support\Locales::all())
@if (count($locales) > 1)
    @if ($dropdown)
        @php($code = app()->getLocale())
        <details {{ $attributes->merge(['class' => 'lang-dd']) }} data-lang-dd>
            <summary aria-label="{{ __('ui.language') }}: {{ $locales[$code] ?? $code }}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
                <b>{{ strtoupper($code) }}</b>
                <svg class="lang-chev" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            </summary>
            <div class="lang-menu" role="menu">
                @foreach ($locales as $c => $name)
                    <a role="menuitem" href="{{ \App\Support\Locales::urlFor($c) }}" hreflang="{{ $c }}" lang="{{ $c }}" @if ($c === $code) aria-current="true" @endif>
                        <span>{{ $name }}</span><small>{{ strtoupper($c) }}</small>
                    </a>
                @endforeach
            </div>
        </details>
        @once
        <style>
            .lang-dd{position:relative;font:600 13px/1.2 inherit}
            .lang-dd summary{display:flex;align-items:center;gap:6px;padding:7px 10px;border-radius:10px;border:1px solid rgba(255,255,255,.14);cursor:pointer;list-style:none;user-select:none;transition:background-color .15s,border-color .15s}
            .lang-dd summary::-webkit-details-marker{display:none}
            .lang-dd summary:hover,.lang-dd[open] summary{background:rgba(255,255,255,.07);border-color:rgba(255,255,255,.26)}
            .lang-dd summary:focus-visible{outline:2px solid #fbbf24;outline-offset:2px}
            .lang-dd b{letter-spacing:.04em}
            .lang-chev{opacity:.7;transition:transform .2s}
            .lang-dd[open] .lang-chev{transform:rotate(180deg)}
            .lang-menu{position:absolute;z-index:80;right:0;top:calc(100% + 8px);min-width:180px;padding:6px;border-radius:14px;border:1px solid rgba(255,255,255,.1);background:rgba(16,16,20,.98);color:#f4f4f5;box-shadow:0 20px 50px -14px #000d;animation:lang-in .16s ease-out}
            @keyframes lang-in{from{opacity:0;transform:translateY(-4px)}}
            .lang-dd .lang-menu a{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:9px 10px;border-radius:9px;color:inherit;text-decoration:none;font-weight:600}
            .lang-dd .lang-menu a:hover{background:rgba(255,255,255,.07)}
            .lang-dd .lang-menu a[aria-current]{background:rgba(251,191,36,.12);color:#fbbf24}
            .lang-dd .lang-menu small{opacity:.55;font-size:11px;letter-spacing:.06em}
            @media (prefers-reduced-motion:reduce){.lang-menu{animation:none}}
        </style>
        <script>
            // Закрити по кліку поза меню чи Esc; на комп'ютері відкривається й наведенням
            document.querySelectorAll('[data-lang-dd]').forEach((dd) => {
                let t;
                document.addEventListener('click', (e) => { if (!dd.contains(e.target)) dd.open = false; });
                dd.addEventListener('keydown', (e) => { if (e.key === 'Escape') { dd.open = false; dd.querySelector('summary').focus(); } });
                if (matchMedia('(hover: hover)').matches) {
                    dd.addEventListener('mouseenter', () => { clearTimeout(t); dd.open = true; });
                    dd.addEventListener('mouseleave', () => { t = setTimeout(() => { dd.open = false; }, 200); });
                }
            });
        </script>
        @endonce
    @else
        {{-- Не <nav>: теми ігор стилізують посилання навігації в шапці --}}
        <div role="group" {{ $attributes->merge(['class' => 'locale-switcher']) }} aria-label="{{ __('ui.language') }}" style="display:inline-flex;align-items:center;gap:2px;margin:0;font:600 12px/1 system-ui,sans-serif;letter-spacing:.04em">
            @foreach ($locales as $code => $name)
                @php($current = $code === app()->getLocale())
                <a href="{{ \App\Support\Locales::urlFor($code) }}" hreflang="{{ $code }}" lang="{{ $code }}" title="{{ $name }}"
                   @if ($current) aria-current="true" @endif
                   style="display:inline-block;padding:6px 7px;border-radius:6px;background:none;text-transform:uppercase;color:inherit;opacity:{{ $current ? '1' : '.55' }};{{ $current ? 'box-shadow:inset 0 0 0 1px currentColor' : '' }}">{{ $code }}</a>
            @endforeach
        </div>
    @endif
@endif
