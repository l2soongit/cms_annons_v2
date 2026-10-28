@php
    use App\Billing\Money;
    use App\Models\Banner;

    // Місця для календаря: група тарифів = місце (+ гра для місць на сторінці гри)
    $groups = $tariffs->groupBy(fn ($t) => $t->placement.'-'.(Banner::isPerGame($t->placement) ? $t->game_id : ''))
        ->map(function ($group, $key) use ($busy) {
            $first = $group->first();
            [, $w, $h] = Banner::PLACEMENTS[$first->placement];
            $shot = $first->placement === 'game' && $first->game && is_file(public_path("images/ad-places/game-{$first->game->slug}.webp")) ? "game-{$first->game->slug}" : $first->placement;

            return [
                'key' => $key,
                'placement' => $first->placement,
                'game' => Banner::isPerGame($first->placement) ? $first->game_id : null,
                'name' => __("billing.ads.placements.{$first->placement}").(Banner::isPerGame($first->placement) && $first->game ? ' · '.$first->game->shortName() : ''),
                'size' => "{$w}×{$h}",
                'shot' => asset("images/ad-places/{$shot}.webp"),
                'where' => __("billing.ads.where.{$first->placement}"),
                'perDay' => Money::format($group->min(fn ($t) => $t->price / max(1, $t->days))),
                'busy' => $busy[$key] ?? [],
                'tariffs' => $group->map(fn ($t) => ['id' => $t->id, 'days' => (int) $t->days, 'price' => (float) $t->price, 'label' => Money::format($t->price)])->values(),
            ];
        })->values();
    $clashes = collect(array_keys(Banner::PLACEMENTS))->mapWithKeys(fn ($p) => [$p => Banner::clashesWith($p)]);
    $oldTariff = (int) old('tariff_id');
@endphp
<x-layouts.cabinet :heading="__('billing.ads.title')" :subtitle="__('billing.ads.subtitle')">
    <x-flash class="mb-6" />

    @if ($servers->isEmpty() || $tariffs->isEmpty())
        <div class="c-card" style="max-width:560px">
            <p>{{ $servers->isEmpty() ? __('billing.ads.no_servers') : __('billing.deposit.unavailable') }}</p>
        </div>
    @else
        <form method="POST" action="{{ route('cabinet.ads.store') }}" enctype="multipart/form-data" class="c-cols-side" data-ads>
            @csrf
            <div class="c-stack">
                <section class="c-card" aria-labelledby="ads-where">
                    <h2 id="ads-where">{{ __('billing.ads.where_title') }}</h2>
                    <a class="c-shot" href="{{ $groups[0]['shot'] }}" target="_blank" rel="noopener" data-shot-link>
                        <img src="{{ $groups[0]['shot'] }}" alt="{{ $groups[0]['where'] }}" data-shot>
                    </a>
                    <p class="c-shot-cap" data-shot-cap>{{ $groups[0]['where'] }}</p>
                </section>

                <section class="c-card" aria-labelledby="ads-banner">
                    <h2 id="ads-banner">{{ __('billing.ads.banner_title') }}</h2>
                    <div class="c-field">
                        <label for="ad-server">{{ __('billing.ads.server') }}</label>
                        <div class="c-inp"><select id="ad-server" name="server_id" required data-server>
                            @foreach ($servers as $server)
                                <option value="{{ $server->id }}" data-game="{{ $server->game_id }}" @selected((int) old('server_id') === $server->id)>{{ $server->name }} · {{ $server->game->name }}</option>
                            @endforeach
                        </select></div>
                    </div>
                    <label class="c-drop" data-drop>
                        <img alt="" hidden data-preview>
                        <b>{{ __('billing.ads.drop') }}</b> <span data-size>{{ $groups[0]['size'] }}</span> px<br>{{ __('billing.ads.drop_hint') }}
                        <input type="file" name="image" accept="image/webp,image/png,image/jpeg,image/gif" required data-file>
                    </label>
                    @error('image')<p class="c-err" style="margin-top:8px">{{ $message }}</p>@enderror
                    <div class="c-field">
                        <span class="c-label">{{ __('billing.ads.link') }}</span>
                        <div class="c-pay" style="margin-top:0">
                            <label><input type="radio" name="link" value="server" @checked(old('link', 'server') === 'server')>{{ __('billing.ads.link_server') }}</label>
                            <label><input type="radio" name="link" value="site" @checked(old('link') === 'site')>{{ __('billing.ads.link_site') }}</label>
                        </div>
                    </div>
                    <div class="c-field">
                        <label for="ad-alt">{{ __('billing.ads.alt') }}</label>
                        <div class="c-inp"><input id="ad-alt" name="alt" maxlength="120" value="{{ old('alt') }}"></div>
                    </div>
                    <div class="c-field">
                        <label for="ad-promo">{{ __('billing.promo.code') }} <span class="c-faint">({{ __('billing.promo.code_optional') }})</span></label>
                        <div class="c-inp"><input id="ad-promo" name="promo_code" maxlength="32" value="{{ old('promo_code') }}" style="text-transform:uppercase"></div>
                    </div>
                </section>

                <section class="c-card" aria-labelledby="ads-mine">
                    <h2 id="ads-mine">{{ __('billing.ads.mine') }}</h2>
                    @forelse ($banners as $banner)
                        <article class="c-ad" style="margin-top:12px">
                            <img src="{{ \App\Support\HomePageData::url($banner->image) }}" alt="" loading="lazy">
                            <div>
                                <b>{{ __("billing.ads.placements.{$banner->placement}") }}@if ($banner->game) · {{ $banner->game->shortName() }}@endif</b>
                                <small class="c-num">{{ $banner->starts_at->format('d.m.Y') }} – {{ $banner->ends_at->copy()->subSecond()->format('d.m.Y') }} · {{ $banner->server?->name }}</small>
                                <small>{{ __('billing.ads.stats', ['views' => number_format($banner->impressions, 0, ',', ' '), 'clicks' => $banner->clicks, 'ctr' => $banner->ctr()]) }}</small>
                                @unless ($banner->is_active)<small class="c-down">{{ __('billing.ads.hidden') }}</small>@endunless
                            </div>
                        </article>
                    @empty
                        <p class="c-muted" style="margin-top:12px;font-size:14px">{{ __('billing.ads.mine_empty') }}</p>
                    @endforelse
                </section>
            </div>

            <section class="c-card" aria-labelledby="ads-place">
                <h2 id="ads-place">{{ __('billing.ads.place_title') }}</h2>
                <div class="c-places" role="group" aria-label="{{ __('billing.ads.tariff') }}">
                    @foreach ($groups as $g)
                        <button type="button" class="c-place-opt" data-place="{{ $g['key'] }}" data-game="{{ $g['game'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
                            <b>{{ $g['name'] }}</b><small>{{ $g['size'] }} px</small><span class="c-pr">{{ __('billing.ads.per_day', ['price' => $g['perDay']]) }}</span>
                        </button>
                    @endforeach
                </div>

                <p class="c-label" style="margin-top:16px">{{ __('billing.ads.duration') }}</p>
                <div class="c-days" role="radiogroup" aria-label="{{ __('billing.ads.duration') }}" data-days>
                    @foreach ($tariffs as $t)
                        {{-- Справжні поля форми; кнопки тривалості нижче лише перемикають їх --}}
                        <input type="radio" name="tariff_id" value="{{ $t->id }}" @checked($oldTariff === $t->id) hidden>
                    @endforeach
                </div>

                <div class="c-cal-head">
                    <button type="button" data-cal-prev aria-label="{{ __('billing.ads.prev_month') }}">‹</button>
                    <span data-cal-title></span>
                    <button type="button" data-cal-next aria-label="{{ __('billing.ads.next_month') }}">›</button>
                </div>
                <div class="c-cal" data-cal></div>
                <p class="c-hint" style="margin-top:8px">{{ __('billing.ads.cal_hint') }}</p>
                <input type="hidden" name="starts_at" value="{{ old('starts_at') }}" data-start>

                <div class="c-warn" hidden data-warn></div>
                @error('tariff_id')<div class="c-warn" role="alert">{{ $message }}</div>@enderror
                @error('starts_at')<div class="c-warn" role="alert">{{ $message }}</div>@enderror

                <div class="c-sum">
                    <div><span class="c-muted">{{ __('billing.ads.period') }}</span><b class="c-num" data-period>—</b></div>
                    <div><span class="c-muted">{{ __('billing.ads.days_n') }}</span><b class="c-num" data-ndays>0</b></div>
                    <div class="c-tot"><span>{{ __('billing.ads.total') }}</span><span class="c-num" data-total>—</span></div>
                </div>
                <button class="c-btn is-go" style="width:100%;margin-top:14px" disabled data-book>{{ __('billing.ads.submit') }}</button>
                <p class="c-hint" style="margin-top:10px">{{ __('billing.promo.lead', ['balance' => Money::format($user->balance)]) }} <a href="{{ route('cabinet.balance') }}" style="color:var(--c-amber)">{{ __('billing.promo.top_up') }}</a></p>
            </section>
        </form>
    @endif

    <x-slot:scripts>
        @if ($servers->isNotEmpty() && $tariffs->isNotEmpty())
            <script type="application/json" id="ads-data">{!! json_encode([
                'groups' => $groups,
                'own' => $own,
                'clashes' => $clashes,
                'names' => collect(array_keys(Banner::PLACEMENTS))->mapWithKeys(fn ($p) => [$p => __("billing.ads.placements.{$p}")]),
                'today' => today()->toDateString(),
                'maxStart' => today()->addDays((int) config('graj.billing.banner_booking_days'))->toDateString(),
                'oldTariff' => $oldTariff,
                'oldStart' => old('starts_at'),
                'locale' => app()->getLocale(),
                't' => ['days' => __('billing.ads.days_short'), 'busy' => __('billing.ads.range_busy'), 'clash' => __('billing.ads.own_clash'), 'pick' => __('billing.ads.pick_date')],
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
            <script>
                // Реклама: вибір місця (знімок сторінки), тривалості й дати в календарі; перевірка зайнятих днів і власних банерів
                (() => {
                    const data = JSON.parse(document.getElementById('ads-data').textContent);
                    const form = document.querySelector('[data-ads]');
                    const $ = (s) => form.querySelector(s), $$ = (s) => [...form.querySelectorAll(s)];
                    const iso = (d) => d.toISOString().slice(0, 10);
                    const parse = (s) => new Date(s + 'T00:00:00Z');
                    const addDays = (d, n) => new Date(d.getTime() + n * 864e5);
                    const fmt = (s) => parse(s).toLocaleDateString(data.locale, { day: '2-digit', month: '2-digit', timeZone: 'UTC' });
                    const money = (n) => n.toLocaleString(data.locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₴';

                    let group = data.groups[0], tariff = null, start = null;
                    let month = parse(data.today); month.setUTCDate(1);
                    const today = parse(data.today), maxStart = parse(data.maxStart);

                    const serverGame = () => +$('[data-server]').selectedOptions[0].dataset.game;
                    const serverId = () => $('[data-server]').value;
                    const range = () => tariff && start ? Array.from({ length: tariff.days }, (_, i) => iso(addDays(parse(start), i))) : [];
                    // Дні, коли власний банер сервера приховав би новий (або навпаки)
                    const clashDays = () => {
                        const list = (data.own[serverId()] || []).filter(b => (data.clashes[group.placement] || []).includes(b.placement));
                        const days = new Map();
                        list.forEach(b => { for (let d = parse(b.from); d <= parse(b.to); d = addDays(d, 1)) days.set(iso(d), b); });
                        return days;
                    };

                    const renderPlaces = () => {
                        const game = serverGame();
                        $$('[data-place]').forEach(b => {
                            const ok = !b.dataset.game || +b.dataset.game === game;
                            b.hidden = !ok;
                            b.setAttribute('aria-pressed', String(b.dataset.place === group.key));
                        });
                        if (!data.groups.find(g => g.key === group.key && (!g.game || g.game === game))) selectGroup(data.groups.find(g => !g.game || g.game === game));
                    };
                    const selectGroup = (g) => {
                        group = g;
                        tariff = group.tariffs.find(t => t.id === data.oldTariff) || group.tariffs[0];
                        $('[data-shot]').src = group.shot; $('[data-shot]').alt = group.where; $('[data-shot-link]').href = group.shot;
                        $('[data-shot-cap]').textContent = group.where; $('[data-size]').textContent = group.size;
                        renderPlaces(); renderDays(); render();
                    };
                    const renderDays = () => {
                        const box = $('[data-days]');
                        box.querySelectorAll('button').forEach(b => b.remove());
                        group.tariffs.forEach(t => {
                            const b = document.createElement('button');
                            b.type = 'button'; b.setAttribute('role', 'radio'); b.setAttribute('aria-checked', String(t === tariff)); b.setAttribute('aria-pressed', String(t === tariff));
                            b.innerHTML = `${t.days} ${data.t.days}<small>${t.label}</small>`;
                            b.addEventListener('click', () => { tariff = t; renderDays(); render(); });
                            box.append(b);
                        });
                    };
                    const render = () => {
                        $$('input[name="tariff_id"]').forEach(r => r.checked = tariff && +r.value === tariff.id);
                        const busy = new Set(group.busy), clash = clashDays(), days = range();
                        const cal = $('[data-cal]');
                        const names = [...Array(7)].map((_, i) => new Date(Date.UTC(2024, 0, 1 + i)).toLocaleDateString(data.locale, { weekday: 'short', timeZone: 'UTC' }));
                        let html = names.map(n => `<span class="c-dw">${n}</span>`).join('');
                        const lead = (month.getUTCDay() + 6) % 7;
                        html += '<span></span>'.repeat(lead);
                        const last = new Date(Date.UTC(month.getUTCFullYear(), month.getUTCMonth() + 1, 0)).getUTCDate();
                        for (let d = 1; d <= last; d++) {
                            const day = new Date(Date.UTC(month.getUTCFullYear(), month.getUTCMonth(), d)), key = iso(day);
                            const out = day < today || day > maxStart, isBusy = busy.has(key);
                            const cls = [isBusy && 'is-busy', key === start && 'is-sel', key !== start && days.includes(key) && (isBusy || clash.has(key) ? 'is-clash' : 'is-in'), !isBusy && clash.has(key) && !days.includes(key) && 'is-clash'].filter(Boolean).join(' ');
                            html += `<button type="button" class="${cls}" data-day="${key}" ${out || isBusy ? 'disabled' : ''}>${d}</button>`;
                        }
                        cal.innerHTML = html;
                        $('[data-cal-title]').textContent = month.toLocaleDateString(data.locale, { month: 'long', year: 'numeric', timeZone: 'UTC' });
                        $('[data-cal-prev]').disabled = month <= new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), 1));
                        $('[data-cal-next]').disabled = month >= new Date(Date.UTC(maxStart.getUTCFullYear(), maxStart.getUTCMonth(), 1));

                        const overlapBusy = days.some(d => busy.has(d));
                        const own = days.map(d => clash.get(d)).find(Boolean);
                        const warn = $('[data-warn]');
                        warn.hidden = !(overlapBusy || own);
                        warn.textContent = overlapBusy ? data.t.busy : own ? data.t.clash.replace(':placement', data.names[own.placement]) : '';
                        $('[data-start]').value = start || '';
                        $('[data-period]').textContent = start && tariff ? `${fmt(days[0])} – ${fmt(days[days.length - 1])}` : '—';
                        $('[data-ndays]').textContent = tariff ? tariff.days : 0;
                        $('[data-total]').textContent = tariff ? money(tariff.price) : '—';
                        $('[data-book]').disabled = !start || !tariff || overlapBusy || !!own;
                        $('[data-book]').title = start ? '' : data.t.pick;
                    };

                    form.addEventListener('click', (e) => {
                        const place = e.target.closest('[data-place]');
                        if (place) { selectGroup(data.groups.find(g => g.key === place.dataset.place)); }
                        const day = e.target.closest('[data-day]');
                        if (day) { start = day.dataset.day; render(); }
                    });
                    $('[data-cal-prev]').addEventListener('click', () => { month.setUTCMonth(month.getUTCMonth() - 1); render(); });
                    $('[data-cal-next]').addEventListener('click', () => { month.setUTCMonth(month.getUTCMonth() + 1); render(); });
                    $('[data-server]').addEventListener('change', () => { renderPlaces(); render(); });
                    $('[data-file]').addEventListener('change', (e) => {
                        const file = e.target.files[0], img = $('[data-preview]');
                        if (!file) { img.hidden = true; return; }
                        img.src = URL.createObjectURL(file); img.hidden = false;
                    });

                    // Після помилки валідації — повертаємо вибране місце, тривалість і дату
                    const fromOld = data.groups.find(g => g.tariffs.some(t => t.id === data.oldTariff));
                    if (data.oldStart) { start = data.oldStart; month = parse(start); month.setUTCDate(1); }
                    selectGroup(fromOld || data.groups[0]);
                })();
            </script>
        @endif
    </x-slot:scripts>
</x-layouts.cabinet>
