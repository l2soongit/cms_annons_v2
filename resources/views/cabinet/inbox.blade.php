<x-layouts.cabinet :title="__('notifications.inbox.title').' | graj.club'" :heading="__('notifications.inbox.title')" :subtitle="__('notifications.inbox.subtitle')">
    <x-flash class="mb-6" />

    <section class="c-card" aria-labelledby="inbox-title" style="max-width:900px">
        <h2 id="inbox-title">{{ __('notifications.inbox.all') }}
            <span class="c-more" style="display:flex;gap:12px;align-items:center">
                <a href="{{ route('cabinet.notifications') }}">{{ __('cabinet.nav.notify_settings') }}</a>
                @if (auth()->user()->unreadNotifications()->exists())
                    <form method="POST" action="{{ route('cabinet.inbox.read-all') }}">@csrf<button class="c-btn is-sm">{{ __('notifications.inbox.mark_all') }}</button></form>
                @endif
            </span>
        </h2>
        @if ($notifications->isEmpty())
            <p class="c-muted" style="margin-top:12px;font-size:14px">{{ __('notifications.inbox.empty') }}</p>
        @else
            <div style="margin-top:8px">
                @foreach ($notifications as $notification)
                    @php $unread = $notification->read_at === null; @endphp
                    <a href="{{ route('cabinet.inbox.open', $notification->id) }}" @class(['c-thread', 'is-unread' => $unread]) style="padding-left:0;padding-right:0">
                        <span class="c-ava" style="width:34px;height:34px;font-size:15px;{{ $unread ? '' : 'background:var(--c-card-2);color:var(--c-faint)' }}" aria-hidden="true">🔔</span>
                        <span class="c-thread-text">
                            <b style="white-space:normal">{{ $notification->data['text'] ?? __('notifications.inbox.untitled') }}</b>
                            @if (! empty($notification->data['reason']))<p style="white-space:normal">{{ $notification->data['reason'] }}</p>@endif
                        </span>
                        <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans(short: true) }}</time>
                        @if ($unread)<span class="c-unread" aria-label="{{ __('messages.unread') }}"></span>@endif
                    </a>
                @endforeach
            </div>
            <div style="margin-top:16px">{{ $notifications->links() }}</div>
        @endif
    </section>
</x-layouts.cabinet>
