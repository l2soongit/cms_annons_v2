{{-- Аналітика: перегляди рахує сервер; тут — лише дії (переходи з сайту, копіювання IP, кнопка голосу). Без cookies. --}}
@if (config('graj.analytics.enabled'))
@php($ctx = request()->route('server'))
<script>
(() => {
    const url = @json(route('analytics.event', [], false));
    const ctx = @json($ctx instanceof \App\Models\Server ? ['s' => $ctx->id, 'web' => $ctx->website ? parse_url($ctx->website, PHP_URL_HOST) : null] : null);
    const send = (n, s, l) => {
        const data = new FormData();
        data.append('n', n);
        if (s) data.append('s', s);
        if (l) data.append('l', l);
        try { navigator.sendBeacon ? navigator.sendBeacon(url, data) : fetch(url, { method: 'POST', body: data, keepalive: true }); } catch (e) {}
    };
    const host = (h) => h.replace(/^www\./, '');
    document.addEventListener('click', (e) => {
        const el = e.target.closest('[data-track], [data-copy], a[href]');
        if (!el) return;
        const s = el.dataset.trackServer || ctx?.s;
        if (el.dataset.track) return send(el.dataset.track, s, el.href);
        if (el.matches('[data-copy]')) return send('copy_ip', s);
        let link;
        try { link = new URL(el.href, location.href); } catch (err) { return; }
        const vote = link.host === location.host && link.pathname.match(/\/server\/([^/]+)\/vote\/?$/);
        if (vote) return send('vote_click', ctx && location.pathname.includes('/server/' + vote[1]) ? ctx.s : vote[1]);
        if (link.host === location.host || !/^https?:$/.test(link.protocol)) return;
        const h = host(link.hostname);
        if (/(^|\.)(discord\.gg|discord\.com|discordapp\.com)$/.test(h)) return send('discord', s);
        if (/(^|\.)(youtube\.com|youtu\.be)$/.test(h)) return send('youtube', s);
        if (ctx?.web && h === host(ctx.web)) return send('website', s);
        send('outbound', s, link.href);
    }, { capture: true });
})();
</script>
@endif
