@php
    $items = [
        'cabinet' => __('cabinet.my_servers'),
        'cabinet.inbox' => __('notifications.inbox.nav').(($unreadNotes = auth()->user()->unreadNotifications()->count()) ? " ({$unreadNotes})" : ''),
        'cabinet.messages' => __('messages.nav').(($unread = app(\App\Messaging\Messenger::class)->unreadCount(auth()->user())) ? " ({$unread})" : ''),
        'cabinet.support' => __('messages.support.nav'),
        'cabinet.ads' => __('billing.ads.nav'),
        'cabinet.balance' => __('billing.nav').' · '.\App\Billing\Money::format(auth()->user()->balance),
        'cabinet.profile' => __('profile.nav.profile'),
        'cabinet.security' => __('profile.nav.security'),
        'cabinet.notifications' => __('profile.nav.notifications'),
    ];
@endphp

<nav class="-mx-4 mb-8 overflow-x-auto border-b border-seam px-4 [scrollbar-width:none] sm:mx-0 sm:px-0" aria-label="{{ __('cabinet.title') }}">
    <ul class="flex gap-1 whitespace-nowrap text-sm">
        @foreach ($items as $route => $label)
            <li>
                <a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif
                   @class([
                       '-mb-px block border-b-2 px-3 py-2.5',
                       'border-amber text-bone' => request()->routeIs($route),
                       'border-transparent text-moss hover:text-bone' => ! request()->routeIs($route),
                   ])>{{ $label }}</a>
            </li>
        @endforeach
        @if (auth()->user()->isStaff())
            <li><a href="/admin" class="block border-b-2 border-transparent px-3 py-2.5 text-moss hover:text-bone">{{ __('profile.nav.admin') }}</a></li>
        @endif
    </ul>
</nav>
