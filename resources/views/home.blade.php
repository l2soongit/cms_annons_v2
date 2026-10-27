<x-layouts.app>
    <div class="max-w-2xl">
        <h1 class="font-display text-3xl font-bold tracking-tight sm:text-4xl">{{ __('ui.home.title') }}</h1>
        <p class="mt-3 text-moss">{{ __('ui.home.lead') }}</p>
    </div>

    <div class="mt-10 grid gap-x-10 gap-y-12 lg:grid-cols-2">
        @foreach ($games as $game)
            <section aria-labelledby="game-{{ $game->slug }}">
                <div class="flex items-baseline justify-between gap-4 border-b border-bone/80 pb-2">
                    <h2 id="game-{{ $game->slug }}" class="font-display text-lg font-medium">
                        <a href="{{ route('games.show', $game) }}" class="hover:text-amber">{{ $game->name }}</a>
                    </h2>
                    <a href="{{ route('games.show', $game) }}" class="text-sm text-moss hover:text-bone">{{ __('ui.home.full_ranking', ['n' => $game->servers_count]) }}</a>
                </div>
                <ol>
                    @foreach ($game->top as $server)
                        <x-server-row :server="$server" :rank="$server->rank ?? $loop->iteration" compact />
                    @endforeach
                </ol>
            </section>
        @endforeach
    </div>

    @if ($comingSoon->isNotEmpty())
        <section class="mt-16" aria-labelledby="coming-soon">
            <h2 id="coming-soon" class="font-display text-lg font-medium">{{ __('ui.home.coming_soon') }}</h2>
            <ul class="mt-4 grid gap-x-10 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($comingSoon as $server)
                    <li class="flex items-baseline justify-between gap-4 border-b border-seam py-3">
                        <div class="min-w-0">
                            <a href="{{ route('servers.show', $server) }}" class="block truncate font-medium hover:text-amber">{{ $server->name }}</a>
                            <span class="text-sm text-moss">{{ $server->game->name }}</span>
                        </div>
                        <time datetime="{{ $server->opens_at->toDateString() }}" class="shrink-0 text-sm tabular-nums text-amber">{{ $server->opens_at->translatedFormat('j M') }}</time>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>
