<x-layouts.app :title="__('ui.search.title').' | graj.club'">
    <h1 class="font-display text-3xl font-bold tracking-tight">{{ __('ui.search.title') }}</h1>

    <form method="get" action="{{ route('search') }}" class="mt-6 flex max-w-xl gap-2" role="search">
        <label for="search-page-q" class="sr-only">{{ __('ui.search.label') }}</label>
        <input id="search-page-q" name="q" type="search" value="{{ $q }}" minlength="2" maxlength="64" autofocus
               placeholder="{{ __('ui.search.placeholder') }}"
               class="w-full rounded-md border border-seam bg-slab px-3 py-2.5 text-bone placeholder:text-moss/70 focus:border-amber focus:outline-none">
        <x-button>{{ __('ui.search.submit') }}</x-button>
    </form>

    @if (mb_strlen($q) >= 2)
        @if ($servers->isEmpty())
            <p class="mt-10 text-moss">{{ __('ui.search.empty', ['q' => $q]) }}</p>
        @else
            <p class="mt-8 text-sm text-moss">{{ trans_choice('ui.search.found', $servers->count(), ['n' => $servers->count()]) }}</p>
            <ol class="mt-2 border-t border-bone/80">
                @foreach ($servers as $server)
                    <li class="grid grid-cols-[1fr_auto] items-center gap-4 border-b border-seam py-4">
                        <div class="min-w-0">
                            <a href="{{ route('servers.show', $server) }}" class="font-medium hover:text-amber">{{ $server->name }}</a>
                            <p class="flex flex-wrap gap-x-3 text-sm text-moss"><span>{{ $server->game->name }}</span><span>{{ $server->address() }}</span></p>
                        </div>
                        <span @class(['rank-numeral text-2xl', 'text-amber' => ($server->rank ?? 99) <= 3, 'text-moss' => ($server->rank ?? 99) > 3])>{{ $server->rank ?? '—' }}</span>
                    </li>
                @endforeach
            </ol>
        @endif
    @endif
</x-layouts.app>
