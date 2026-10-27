<x-layouts.app :title="__('cabinet.title').' | graj.club'">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="font-display text-3xl font-bold tracking-tight">{{ __('cabinet.my_servers') }}</h1>
        <x-button :href="route('cabinet.servers.create')">{{ __('ui.nav.add_server') }}</x-button>
    </div>

    <x-flash class="mt-6" />

    @if ($servers->isEmpty())
        <div class="mt-10 max-w-lg">
            <p class="text-lg">{{ __('cabinet.empty_title') }}</p>
            <p class="mt-2 text-moss">{{ __('cabinet.empty_lead') }}</p>
            <x-button class="mt-6" :href="route('cabinet.servers.create')">{{ __('ui.nav.add_server') }}</x-button>
        </div>
    @else
        <ul class="mt-8 border-t border-bone/80">
            @foreach ($servers as $server)
                <li class="grid grid-cols-[1fr_auto] items-center gap-x-6 gap-y-1 border-b border-seam py-4 sm:grid-cols-[1fr_8rem_6rem_6rem_auto]">
                    <div class="min-w-0">
                        <a href="{{ route('cabinet.servers.show', $server) }}" class="font-medium hover:text-amber">{{ $server->name }}</a>
                        <p class="flex flex-wrap gap-x-3 text-sm text-moss"><span>{{ $server->game->name }}</span><span>{{ $server->address() }}</span></p>
                    </div>
                    <div class="hidden sm:block"><x-status-badge :status="$server->status" /></div>
                    <p class="hidden text-sm tabular-nums sm:block">{{ $server->rank ? '#'.$server->rank : '—' }}</p>
                    <p class="hidden text-sm tabular-nums text-moss sm:block">{{ $server->votes_month }} {{ trans_choice('ui.server.votes', $server->votes_month) }}</p>
                    <a href="{{ route('cabinet.servers.edit', $server) }}" class="text-sm text-moss hover:text-bone">{{ __('cabinet.edit') }}</a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
