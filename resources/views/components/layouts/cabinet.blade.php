{{--
    Макет кабінету за демо design/cabinet: бічне меню, верхня панель із заголовком, станом сервера, балансом і сповіщеннями.
    Палітра й шрифти демо (zinc + amber→orange, Unbounded/Manrope) задаються в resources/css/cabinet.css —
    там же кольори сайту перевизначено, тож сторінки кабінету на Tailwind отримують ту саму палітру. Тема лише темна.
--}}
@props(['title' => null, 'heading' => null, 'subtitle' => null, 'server' => null])
@php
    $user = auth()->user();
    // «Сервер» і «Просування» в меню ведуть до сервера сторінки (або вибраного на огляді), інакше — до першого свого
    $navServer = $server ?? request()->route('server');
    if (! $navServer instanceof \App\Models\Server) {
        $mine = \App\Models\Server::where('user_id', $user->id)->orWhereHas('coOwners', fn ($q) => $q->whereKey($user->id));
        $navServer = (clone $mine)->whereKey((int) request()->query('server'))->first() ?? $mine->orderByRaw("status = 'published' desc")->latest()->first();
    }
    $unreadNotes = $user->unreadNotifications()->count();
    $unreadMessages = app(\App\Messaging\Messenger::class)->unreadCount($user);
    $icon = fn (string $d) => '<svg viewBox="0 0 24 24" aria-hidden="true">'.$d.'</svg>';
    $items = [
        ['cabinet', __('cabinet.nav.overview'), '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>', null, null],
    ];
    if ($navServer) {
        $items[] = ['cabinet.servers.show', __('cabinet.nav.server'), '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>', route('cabinet.servers.show', $navServer), null];
        $items[] = ['', __('cabinet.nav.promo'), '<path d="M12 3l2.6 5.4 5.9.8-4.3 4.1 1 5.8L12 16.4 6.8 19.1l1-5.8L3.5 9.2l5.9-.8z"/>', route('cabinet.servers.show', $navServer).'#s-promo', null];
    }
    $items = array_merge($items, [
        ['cabinet.ads', __('billing.ads.nav'), '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M8 15h4"/>', null, null],
        ['cabinet.balance', __('billing.nav'), '<path d="M4 7h14a2 2 0 012 2v9a2 2 0 01-2 2H5a1 1 0 01-1-1V7zM4 7l11-3v3M16 13.5h.01"/>', null, null],
        ['cabinet.messages*', __('messages.nav'), '<path d="M4 5h16v11H8l-4 4z"/>', route('cabinet.messages'), $unreadMessages],
        ['cabinet.support*', __('messages.support.nav'), '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 114 2c-.8.5-1.5 1-1.5 2M12 17h.01"/>', route('cabinet.support'), null],
        ['cabinet.inbox', __('notifications.inbox.nav'), '<path d="M18 8a6 6 0 10-12 0c0 7-3 8-3 8h18s-3-1-3-8M10.3 20a2 2 0 003.4 0"/>', null, $unreadNotes],
    ]);
    $account = [
        ['cabinet.security', __('cabinet.nav.security'), '<path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/>'],
        ['cabinet.notifications', __('cabinet.nav.notify_settings'), '<path d="M4 6h16M4 12h10M4 18h7"/><circle cx="18" cy="16" r="3"/>'],
        ['cabinet.profile', __('profile.nav.profile'), '<path d="M12 12a4 4 0 100-8 4 4 0 000 8zM4 21a8 8 0 0116 0"/>'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="cab">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? (($heading ? $heading.' · ' : '').__('cabinet.title').' | graj.club') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@500;700;800&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/cabinet.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body>
<a href="#main" class="c-skip">{{ __('ui.skip') }}</a>
<div class="c-app">
    <aside class="c-side" id="c-side" aria-label="{{ __('cabinet.title') }}">
        <a class="c-logo" href="{{ route('home') }}"><i aria-hidden="true">g</i>graj.club</a>
        @foreach ($items as [$route, $label, $path, $url, $badge])
            @php($active = $route !== '' && request()->routeIs($route))
            <a href="{{ $url ?? route($route) }}" @if ($active) aria-current="page" @endif>{!! $icon($path) !!}<span>{{ $label }}</span>@if ($badge)<span class="c-badge">{{ $badge > 99 ? '99+' : $badge }}</span>@endif</a>
        @endforeach
        <div class="c-sep">{{ __('cabinet.nav.account') }}</div>
        @foreach ($account as [$route, $label, $path])
            <a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif>{!! $icon($path) !!}<span>{{ $label }}</span></a>
        @endforeach
        @if ($user->isStaff())
            <a href="/admin">{!! $icon('<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>') !!}<span>{{ __('profile.nav.admin') }}</span></a>
        @endif
        <div class="c-me">
            <span class="c-ava" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <div class="c-me-text"><b>{{ $user->name }}</b><small>{{ $user->email }}</small></div>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="c-icon-btn" aria-label="{{ __('auth.ui.logout') }}" title="{{ __('auth.ui.logout') }}">{!! $icon('<path d="M15 4h3a2 2 0 012 2v12a2 2 0 01-2 2h-3M10 17l5-5-5-5M15 12H3"/>') !!}</button>
            </form>
        </div>
    </aside>
    <div class="c-scrim" data-cab-close hidden></div>

    <main id="main" class="c-main">
        <div class="c-top">
            <button type="button" class="c-menu-btn" data-cab-menu aria-controls="c-side" aria-expanded="false" aria-label="{{ __('cabinet.nav.menu') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <div class="c-top-text">
                @if ($heading)<h1>{{ $heading }}</h1>@endif
                @if ($subtitle)<p class="c-sub">{{ $subtitle }}</p>@endif
            </div>
            <div class="c-tools">
                @if ($navServer && $navServer->status === \App\Enums\ServerStatus::Published)
                    <a class="c-chip c-hide-md" href="{{ route('cabinet.servers.show', $navServer) }}">
                        <span @class(['c-dot', 'is-off' => ! $navServer->is_online])></span>{{ $navServer->is_online ? __('cabinet.nav.online', ['n' => number_format((int) $navServer->players, 0, ',', ' ')]) : __('cabinet.nav.offline') }}
                    </a>
                @endif
                <a class="c-chip" href="{{ route('cabinet.balance') }}" aria-label="{{ __('billing.balance') }}"><span class="c-num">{{ \App\Billing\Money::format($user->balance) }}</span></a>
                <x-locale-switcher class="c-hide-sm" />
                <x-notification-bell class="c-bell" />
                <a class="c-chip c-hide-sm" href="{{ route('home') }}">{{ __('cabinet.nav.site') }} ↗</a>
            </div>
        </div>

        {{ $slot }}
    </main>
</div>
<script>
    // Мобільне меню кабінету
    (() => {
        const side = document.getElementById('c-side'), btn = document.querySelector('[data-cab-menu]'), scrim = document.querySelector('[data-cab-close]');
        const set = (open) => { side.classList.toggle('is-open', open); scrim.hidden = !open; btn.setAttribute('aria-expanded', String(open)); };
        btn?.addEventListener('click', () => set(!side.classList.contains('is-open')));
        scrim?.addEventListener('click', () => set(false));
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') set(false); });
    })();
</script>
{{ $scripts ?? '' }}
</body>
</html>
