{{-- Повідомлення про cookies (ТЗ 4, GDPR). Сайт ставить лише необхідні cookies (вхід, захист форм, мова), тому згода
     на аналітику не потрібна — достатньо повідомити й дати посилання на політику. Стилі вбудовані: однаково в усіх макетах.
     Закриття запам'ятовується в cookie (сервер більше не виводить блок) і в localStorage — на випадок, якщо cookie заблоковано --}}
@unless (request()->cookie('cookie_notice'))
<div id="cookie-notice" role="region" aria-label="Cookies" hidden
     style="position:fixed;left:16px;right:16px;bottom:16px;z-index:2147483000;max-width:560px;margin:0 auto;display:flex;gap:12px;align-items:center;flex-wrap:wrap;padding:12px 16px;border-radius:12px;background:#111827f2;color:#f3f4f6;font:14px/1.5 system-ui,sans-serif;box-shadow:0 12px 40px -12px #000;pointer-events:auto">
    <span style="flex:1;min-width:220px">{{ __('ui.cookies.text') }} <a href="{{ route('pages.show', 'privacy') }}" style="color:#fbbf24;text-decoration:underline">{{ __('ui.cookies.more') }}</a></span>
    <button type="button" id="cookie-ok" onclick="window.closeCookieNotice()"
            style="position:relative;padding:10px 16px;border:0;border-radius:8px;background:#fbbf24;color:#1c1003;font-weight:700;cursor:pointer;touch-action:manipulation;pointer-events:auto">{{ __('ui.cookies.ok') }}</button>
</div>
<script>
    window.closeCookieNotice = () => {
        document.cookie = 'cookie_notice=1; max-age=31536000; path=/; SameSite=Lax';
        try { localStorage.setItem('cookie-notice', '1'); } catch (e) {}
        document.getElementById('cookie-notice')?.remove();
    };
    (() => {
        let seen = false;
        try { seen = localStorage.getItem('cookie-notice') === '1'; } catch (e) {}
        const box = document.getElementById('cookie-notice');
        if (seen) { box.remove(); return; }
        box.hidden = false;
    })();
</script>
@endunless
