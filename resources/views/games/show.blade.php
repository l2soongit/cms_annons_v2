<x-layouts.app :title="__('ui.game.meta_title', ['game' => trim($game->name.' '.($seoFilters ?? ''))])">
    <x-slot:head><x-json-ld :data="\App\Support\StructuredData::game($game, $seoFilters ?? '')" /></x-slot:head>
    <x-ad-banner placement="game" :game="$game" gap="0 auto 32px" />
    <div class="flex flex-wrap items-end justify-between gap-6">
        <div>
            <h1 class="font-display text-3xl font-bold tracking-tight sm:text-4xl">{{ __('ui.game.title', ['game' => $game->name]) }}</h1>
            <p class="mt-2 text-moss">{{ __('ui.game.lead', ['n' => $servers->total(), 'month' => now()->translatedFormat('F')]) }}</p>
        </div>

        <form method="get" class="flex flex-wrap items-end gap-3" aria-label="{{ __('ui.game.filters') }}">
            @foreach ($game->fields->where('is_filterable', true) as $field)
                <label class="text-sm">
                    <span class="mb-1 block text-moss">{{ $field->label }}</span>
                    <select name="{{ $field->key }}" class="rounded-md border border-seam bg-slab px-3 py-2 text-bone">
                        <option value="">{{ __('ui.game.any') }}</option>
                        @foreach ($field->options as $option)
                            <option value="{{ $option['value'] }}" @selected(request($field->key) === $option['value'])>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
            <input type="hidden" name="sort" value="{{ $sort }}">
            <button class="rounded-md bg-slab-2 px-4 py-2 text-sm font-medium hover:text-amber">{{ __('ui.game.apply') }}</button>
        </form>
    </div>

    <nav class="mt-8 flex flex-wrap gap-1 text-sm" aria-label="{{ __('ui.game.sort') }}">
        @foreach (\App\Http\Controllers\GameController::SORTS as $option)
            <a href="{{ request()->fullUrlWithQuery(['sort' => $option, 'page' => null]) }}" @if ($sort === $option) aria-current="true" @endif
               @class(['rounded-md px-3 py-1.5', 'bg-slab-2 text-bone' => $sort === $option, 'text-moss hover:text-bone' => $sort !== $option])>{{ __("ui.game.sort_{$option}") }}</a>
        @endforeach
    </nav>

    <div class="mt-4 hidden grid-cols-[4rem_1fr_11rem_7rem_auto] gap-x-4 border-b border-bone/80 pb-2 text-xs text-moss sm:grid">
        <span class="text-right">{{ __('ui.game.col_rank') }}</span>
        <span>{{ __('ui.game.col_server') }}</span>
        <span>{{ __('ui.game.col_online') }}</span>
        <span class="text-right">{{ __('ui.game.col_votes') }}</span>
        <span class="w-[6.5rem]"></span>
    </div>

    @if ($vip->isNotEmpty() && $servers->onFirstPage() && $sort === 'votes')
        <ol class="border-b-2 border-amber/40" aria-label="VIP">
            @foreach ($vip as $server)
                <x-server-row :server="$server" :rank="$server->rank" vip />
            @endforeach
        </ol>
    @endif

    @if ($servers->isEmpty())
        <div class="py-16 text-center">
            <p class="text-lg">{{ __('ui.game.empty') }}</p>
            <a href="{{ route('games.show', $game) }}" class="mt-2 inline-block text-amber hover:underline">{{ __('ui.game.reset') }}</a>
        </div>
    @else
        <ol>
            @foreach ($servers as $server)
                <x-server-row :server="$server" :rank="$server->rank ?? $servers->firstItem() + $loop->index" />
            @endforeach
        </ol>
        <div class="mt-6">{{ $servers->links() }}</div>
    @endif

    <div class="mt-16 grid gap-x-10 gap-y-12 lg:grid-cols-2">
        @if ($comingSoon->isNotEmpty())
            <section aria-labelledby="game-soon">
                <h2 id="game-soon" class="border-b border-bone/80 pb-2 font-display text-lg font-medium">{{ __('ui.home.coming_soon') }}</h2>
                <ul>
                    @foreach ($comingSoon as $soon)
                        <li class="flex items-baseline justify-between gap-4 border-b border-seam py-3">
                            <a href="{{ route('servers.show', $soon) }}" class="truncate font-medium hover:text-amber">{{ $soon->name }}</a>
                            <time datetime="{{ $soon->opens_at->toDateString() }}" class="shrink-0 text-sm tabular-nums text-amber">{{ $soon->opens_at->translatedFormat('j M') }}</time>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($winners->isNotEmpty())
            <section aria-labelledby="game-winners">
                <h2 id="game-winners" class="border-b border-bone/80 pb-2 font-display text-lg font-medium">{{ __('ui.game.winners') }}</h2>
                <dl>
                    @foreach ($winners as $month => $places)
                        <div class="grid grid-cols-[7rem_1fr] gap-4 border-b border-seam py-3">
                            <dt class="text-sm text-moss">{{ \Illuminate\Support\Carbon::parse($month)->translatedFormat('F Y') }}</dt>
                            <dd>
                                <ol class="space-y-1">
                                    @foreach ($places as $winner)
                                        <li class="flex items-baseline gap-3">
                                            <span class="rank-numeral w-4 text-amber">{{ $winner->place }}</span>
                                            @if ($winner->server)
                                                <a href="{{ route('servers.show', $winner->server) }}" class="hover:text-amber">{{ $winner->server_name }}</a>
                                            @else
                                                <span>{{ $winner->server_name }}</span>
                                            @endif
                                            <span class="ml-auto text-sm tabular-nums text-moss">{{ number_format($winner->votes, 0, ',', ' ') }}</span>
                                        </li>
                                    @endforeach
                                </ol>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif
    </div>
</x-layouts.app>
