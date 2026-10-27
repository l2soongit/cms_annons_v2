@props(['server', 'rank', 'vip' => false, 'compact' => false])

@php
    $segments = (int) round($server->fillRatio() * 10);
    $tags = $server->fieldValues
        ->map(fn ($v) => collect($v->field?->options)->firstWhere('value', $v->value)['label'] ?? $v->value)
        ->filter()
        ->implode(' ');
@endphp

<li @class([
    'grid items-center gap-x-4 gap-y-2 border-b border-seam py-4 last:border-b-0',
    'grid-cols-[3rem_1fr_auto]' => $compact,
    'grid-cols-[3rem_1fr_auto] sm:grid-cols-[4rem_1fr_11rem_7rem_auto]' => ! $compact,
    'bg-amber/5 -mx-4 px-4 sm:-mx-5 sm:px-5' => $vip,
])>
    @if ($vip)
        <span class="flex justify-end text-amber">
            <svg class="size-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4l-5.9 3.1 1.2-6.5L2.5 9.4l6.6-.9z"/></svg>
        </span>
    @else
        <span @class([
            'rank-numeral text-right',
            'text-2xl' => $compact,
            'text-3xl sm:text-4xl' => ! $compact,
            'text-amber' => $rank <= 3,
            'text-moss' => $rank > 3,
        ])>{{ $rank }}</span>
    @endif

    <div class="min-w-0">
        <div class="flex items-center gap-2">
            <span @class(['size-2 shrink-0 rounded-full', 'bg-signal' => $server->is_online, 'bg-fade' => ! $server->is_online])
                  role="img" aria-label="{{ $server->is_online ? __('ui.server.online') : __('ui.server.offline') }}"></span>
            <a href="{{ route('servers.show', $server) }}" class="truncate font-medium hover:text-amber">{{ $server->name }}</a>
            @if ($server->verified_at)
                <svg class="size-4 shrink-0 text-signal" viewBox="0 0 20 20" fill="currentColor" role="img" aria-label="{{ __('ui.server.verified') }}"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.86-9.86a.75.75 0 0 0-1.22-.88l-3.24 4.5-1.6-1.6a.75.75 0 1 0-1.06 1.06l2.22 2.22a.75.75 0 0 0 1.14-.09l3.76-5.21Z" clip-rule="evenodd"/></svg>
            @endif
            @if ($vip)
                <span class="rounded bg-amber px-1.5 py-0.5 text-xs font-semibold text-amber-ink">VIP</span>
            @endif
        </div>
        <p class="mt-0.5 truncate text-sm text-moss">{{ $tags ?: $server->address() }}</p>
    </div>

    @unless ($compact)
        <div class="hidden sm:block" title="{{ __('ui.server.players', ['n' => $server->players, 'max' => $server->max_players]) }}">
            <div class="load-meter" aria-hidden="true">
                @for ($i = 0; $i < 10; $i++)
                    <span @if ($i < $segments) data-on @endif></span>
                @endfor
            </div>
            <p class="mt-1.5 text-xs text-moss tabular-nums">
                @if ($server->is_online)
                    {{ number_format($server->players, 0, ',', ' ') }} / {{ number_format($server->max_players, 0, ',', ' ') }}
                @else
                    {{ __('ui.server.offline') }}
                @endif
            </p>
        </div>

        <div class="hidden text-right sm:block">
            <p class="font-display text-lg font-medium tabular-nums">{{ number_format($server->votes_month, 0, ',', ' ') }}</p>
            <p class="text-xs text-moss">{{ trans_choice('ui.server.votes', $server->votes_month) }}</p>
        </div>
    @endunless

    @if ($compact)
        <p class="text-right text-sm tabular-nums text-moss">{{ number_format($server->votes_month, 0, ',', ' ') }}</p>
    @else
        <p class="text-right text-sm tabular-nums text-moss sm:hidden">{{ number_format($server->votes_month, 0, ',', ' ') }}</p>
        <a href="{{ route('servers.show', $server) }}#vote"
           class="hidden rounded-md border border-seam px-3 py-2 text-sm font-medium hover:border-amber hover:text-amber sm:inline-block">{{ __('ui.server.vote') }}</a>
    @endif
</li>
