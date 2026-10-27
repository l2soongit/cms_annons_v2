<x-layouts.app :title="__('ui.game.meta_title', ['game' => $game->name])">
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
            <button class="rounded-md bg-slab-2 px-4 py-2 text-sm font-medium hover:text-amber">{{ __('ui.game.apply') }}</button>
        </form>
    </div>

    <div class="mt-8 hidden grid-cols-[4rem_1fr_11rem_7rem_auto] gap-x-4 border-b border-bone/80 pb-2 text-xs text-moss sm:grid">
        <span class="text-right">{{ __('ui.game.col_rank') }}</span>
        <span>{{ __('ui.game.col_server') }}</span>
        <span>{{ __('ui.game.col_online') }}</span>
        <span class="text-right">{{ __('ui.game.col_votes') }}</span>
        <span class="w-[6.5rem]"></span>
    </div>

    @if ($vip->isNotEmpty() && $servers->onFirstPage())
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
</x-layouts.app>
