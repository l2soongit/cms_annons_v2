<x-layouts.app :title="$server->name.' — '.$server->game->name">
    <nav class="text-sm text-moss" aria-label="{{ __('ui.breadcrumbs') }}">
        <a href="{{ route('games.show', $server->game) }}" class="hover:text-bone">{{ $server->game->name }}</a>
        <span aria-hidden="true">/</span>
        <span class="text-bone">{{ $server->name }}</span>
    </nav>

    <div class="mt-6 grid gap-10 lg:grid-cols-[1fr_20rem]">
        <div>
            <div class="flex items-start gap-5">
                <span @class(['rank-numeral text-6xl', 'text-amber' => ($server->rank ?? 99) <= 3, 'text-moss' => ($server->rank ?? 99) > 3])>{{ $server->rank ?? '—' }}</span>
                <div>
                    <h1 class="font-display text-3xl font-bold tracking-tight">{{ $server->name }}</h1>
                    <p class="mt-1 text-moss">
                        {{ $server->fieldValues->map(fn ($v) => collect($v->field?->options)->firstWhere('value', $v->value)['label'] ?? $v->value)->implode(', ') }}
                    </p>
                </div>
            </div>

            <div class="mt-8 max-w-prose leading-relaxed">{{ $server->description }}</div>
        </div>

        <aside class="space-y-6 lg:border-l lg:border-seam lg:pl-8">
            <div>
                <p class="text-sm text-moss">{{ __('ui.server.address') }}</p>
                <p class="mt-1 font-medium">{{ $server->address() }}</p>
            </div>
            <div>
                <p class="text-sm text-moss">{{ __('ui.server.status') }}</p>
                <p @class(['mt-1 font-medium', 'text-signal' => $server->is_online, 'text-fade' => ! $server->is_online])>
                    {{ $server->is_online ? __('ui.server.players', ['n' => $server->players, 'max' => $server->max_players]) : __('ui.server.offline') }}
                </p>
            </div>
            <div>
                <p class="text-sm text-moss">{{ __('ui.server.uptime') }}</p>
                <p class="mt-1 font-medium">{{ $server->uptime }}%</p>
            </div>
            <div id="vote">
                <p class="font-display text-2xl font-medium tabular-nums">{{ number_format($server->votes_month, 0, ',', ' ') }}</p>
                <p class="text-sm text-moss">{{ trans_choice('ui.server.votes', $server->votes_month) }}</p>
                <button type="button" disabled class="mt-3 w-full rounded-md bg-amber px-4 py-3 font-medium text-amber-ink disabled:opacity-60" title="{{ __('ui.server.vote_soon') }}">{{ __('ui.server.vote') }}</button>
                <p class="mt-2 text-xs text-moss">{{ __('ui.server.vote_soon') }}</p>
            </div>
        </aside>
    </div>
</x-layouts.app>
