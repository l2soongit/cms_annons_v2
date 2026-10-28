{{-- Список діалогів (ліва колонка «Повідомлень») --}}
<nav class="c-threads" aria-label="{{ __('messages.title') }}">
    <div class="c-threads-head">{{ __('messages.title') }}</div>
    @forelse ($conversations as $item)
        @php
            $other = $item->other($user);
            $last = $item->messages->first();
            $unread = $item->isUnreadFor($user);
        @endphp
        <a href="{{ route('cabinet.messages.show', $item) }}" @class(['c-thread', 'is-unread' => $unread]) @if (isset($current) && $item->is($current)) aria-current="page" @endif>
            <span class="c-ava" aria-hidden="true">{{ mb_strtoupper(mb_substr($other?->name ?? '?', 0, 1)) }}</span>
            <span class="c-thread-text">
                <b>{{ $other?->name ?? __('messages.deleted_user') }}</b>
                <p>@if ($item->server){{ $item->server->name }} · @endif@if ($last?->user_id === $user->id){{ __('messages.you') }}: @endif{{ $last?->body ?: '🖼' }}</p>
            </span>
            <time datetime="{{ $item->last_message_at?->toIso8601String() }}">{{ $item->last_message_at?->diffForHumans(short: true) }}</time>
            @if ($unread)<span class="c-unread" aria-label="{{ __('messages.unread') }}"></span>@endif
        </a>
    @empty
        <p class="c-muted" style="padding:16px;font-size:14px">{{ __('messages.empty') }}</p>
    @endforelse
</nav>
