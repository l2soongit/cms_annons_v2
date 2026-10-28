{{-- Дзвіночок сповіщень у шапці (ТЗ 3.8: сповіщення на сайті). Стилі вбудовані — однаково в загальному макеті й темах ігор --}}
@auth
    @php($unread = auth()->user()->unreadNotifications()->count())
    <a href="{{ route('cabinet.inbox') }}" {{ $attributes->merge(['class' => 'notif-bell']) }}
       aria-label="{{ __('notifications.inbox.title') }}{{ $unread ? ' · '.trans_choice('notifications.inbox.unread', $unread, ['n' => $unread]) : '' }}"
       style="position:relative;display:inline-grid;place-items:center;width:36px;height:36px;border-radius:8px;color:inherit;opacity:.85">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
        @if ($unread)
            <span aria-hidden="true" style="position:absolute;top:2px;right:2px;min-width:16px;height:16px;padding:0 4px;border-radius:999px;background:#ef4444;color:#fff;font:700 10px/16px system-ui,sans-serif;text-align:center">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </a>
@endauth
