<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('ui.meta.title') }}</title>
    <meta name="description" content="{{ $description ?? __('ui.meta.description') }}">
    <link rel="canonical" href="{{ url()->current() }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600&family=Unbounded:wght@500;700&display=swap" rel="stylesheet">

    <script>
        try { const t = localStorage.getItem('theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-amber focus:px-3 focus:py-2 focus:text-amber-ink">{{ __('ui.skip') }}</a>

    <header class="border-b border-seam">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="font-display text-xl font-bold tracking-tight">graj<span class="text-amber">.</span>club</a>

            <nav aria-label="{{ __('ui.nav.games') }}" class="order-3 -mx-4 w-full overflow-x-auto px-4 [scrollbar-width:none] sm:mx-0 sm:px-0 xl:order-none xl:w-auto">
                <ul class="flex gap-1 whitespace-nowrap text-sm">
                    @foreach ($navGames as $navGame)
                        <li>
                            <a href="{{ route('games.show', $navGame) }}"
                               @class([
                                   'block rounded-md px-3 py-2 transition-colors',
                                   'bg-slab-2 text-bone' => request()->route('game')?->is($navGame),
                                   'text-moss hover:text-bone' => ! request()->route('game')?->is($navGame),
                               ])
                               @if (request()->route('game')?->is($navGame)) aria-current="page" @endif>{{ $navGame->name }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="ml-auto flex items-center gap-2 text-sm">
                <a href="{{ route('search') }}" class="rounded-md px-3 py-2 text-moss hover:text-bone" aria-label="{{ __('ui.search.title') }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </a>
                <button type="button" data-theme-toggle class="rounded-md px-3 py-2 text-moss hover:text-bone" aria-label="{{ __('ui.theme') }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                </button>
                @auth
                    <x-notification-bell class="text-moss hover:text-bone" />
                    <a href="{{ route('cabinet') }}" class="rounded-md px-3 py-2 text-moss hover:text-bone" title="{{ auth()->user()->name }}">{{ __('cabinet.title') }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-md px-3 py-2 text-moss hover:text-bone">{{ __('auth.ui.logout') }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="rounded-md px-3 py-2 text-moss hover:text-bone">{{ __('ui.nav.login') }}</a>
                @endauth
                <a href="{{ route('cabinet.servers.create') }}" class="rounded-md bg-amber px-4 py-2 font-medium text-amber-ink hover:brightness-110">{{ __('ui.nav.add_server') }}</a>
            </div>
        </div>
    </header>

    <main id="main" class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
        @if (request()->routeIs('servers.show', 'games.show', 'search'))
            <x-ad-banner placement="header" gap="0 auto 32px" />
        @endif
        {{ $slot }}
    </main>

    <footer class="border-t border-seam">
        <div class="mx-auto flex max-w-6xl flex-wrap gap-x-6 gap-y-2 px-4 py-6 text-sm text-moss sm:px-6">
            <span>© {{ date('Y') }} graj.club</span>
            <a href="#" class="hover:text-bone">{{ __('ui.footer.rules') }}</a>
            <a href="{{ route('developers') }}" class="hover:text-bone">{{ __('ui.footer.api') }}</a>
            <a href="#" class="hover:text-bone">{{ __('ui.footer.contacts') }}</a>
        </div>
    </footer>
</body>
</html>
