<x-layouts.app :title="__('notifications.inbox.title').' | graj.club'">
    <x-cabinet-nav />
    <x-flash class="mb-6" />

    <div class="flex flex-wrap items-baseline justify-between gap-4">
        <h1 class="font-display text-3xl font-bold tracking-tight">{{ __('notifications.inbox.title') }}</h1>
        @if (auth()->user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('cabinet.inbox.read-all') }}">
                @csrf
                <button class="text-sm text-moss hover:text-bone">{{ __('notifications.inbox.mark_all') }}</button>
            </form>
        @endif
    </div>
    <p class="mt-2 text-sm text-moss">{{ __('notifications.inbox.lead') }} <a href="{{ route('cabinet.notifications') }}" class="text-amber hover:underline">{{ __('notifications.inbox.settings') }}</a></p>

    @if ($notifications->isEmpty())
        <p class="mt-8 text-moss">{{ __('notifications.inbox.empty') }}</p>
    @else
        <ul class="mt-6 divide-y divide-seam border-y border-seam">
            @foreach ($notifications as $notification)
                @php($unread = $notification->read_at === null)
                <li>
                    <a href="{{ route('cabinet.inbox.open', $notification->id) }}" class="flex items-start gap-3 py-4 hover:bg-slab/60">
                        <span @class(['mt-2 size-2.5 shrink-0 rounded-full', 'bg-amber' => $unread, 'bg-transparent' => ! $unread]) aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">
                            <span @class(['block', 'font-semibold text-bone' => $unread, 'text-moss' => ! $unread])>{{ $notification->data['text'] ?? __('notifications.inbox.untitled') }}</span>
                            @if (! empty($notification->data['reason']))<span class="mt-0.5 block text-sm text-moss">{{ $notification->data['reason'] }}</span>@endif
                        </span>
                        <time class="shrink-0 text-xs text-moss" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $notifications->links() }}</div>
    @endif
</x-layouts.app>
