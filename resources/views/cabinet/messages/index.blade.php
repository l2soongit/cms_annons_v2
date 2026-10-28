<x-layouts.app :title="__('messages.title').' | graj.club'">
    <x-cabinet-nav />
    <h1 class="font-display text-3xl font-bold tracking-tight">{{ __('messages.title') }}</h1>

    @if ($conversations->isEmpty())
        <p class="mt-6 text-moss">{{ __('messages.empty') }}</p>
    @else
        <ul class="mt-6 divide-y divide-seam border-y border-seam">
            @foreach ($conversations as $conversation)
                @php
                    $other = $conversation->other($user);
                    $last = $conversation->messages->first();
                    $unread = $conversation->isUnreadFor($user);
                @endphp
                <li>
                    <a href="{{ route('cabinet.messages.show', $conversation) }}" class="flex items-start gap-4 py-4 hover:bg-slab/60">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-slab-2 font-display font-bold">{{ mb_strtoupper(mb_substr($other?->name ?? '?', 0, 1)) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline justify-between gap-3">
                                <span @class(['truncate', 'font-semibold' => $unread])>{{ $other?->name ?? __('messages.deleted_user') }}
                                    @if ($conversation->server)<span class="text-sm font-normal text-moss">· {{ __('messages.about', ['server' => $conversation->server->name]) }}</span>@endif
                                </span>
                                <time class="shrink-0 text-xs text-moss">{{ $conversation->last_message_at?->diffForHumans() }}</time>
                            </span>
                            <span @class(['mt-1 block truncate text-sm', 'text-bone' => $unread, 'text-moss' => ! $unread])>
                                @if ($last?->user_id === $user->id){{ __('messages.you') }}: @endif{{ $last?->body ?: '🖼' }}
                            </span>
                        </span>
                        @if ($unread)<span class="mt-2 size-2.5 shrink-0 rounded-full bg-amber" aria-label="{{ __('profile.notifications.title') }}"></span>@endif
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $conversations->links() }}</div>
    @endif
</x-layouts.app>
