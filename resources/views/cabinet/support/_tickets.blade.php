{{-- Список звернень (ліва колонка «Підтримки») --}}
<nav class="c-threads" aria-label="{{ __('messages.support.list') }}">
    <div class="c-threads-head">{{ __('messages.support.list') }}
        <a class="c-more" href="{{ route('cabinet.support') }}#t-new">+ {{ __('messages.support.new_short') }}</a></div>
    @forelse ($tickets as $item)
        @php $unread = $item->conversation?->isUnreadFor(auth()->user()); @endphp
        <a href="{{ route('cabinet.support.show', $item) }}" @class(['c-thread', 'is-unread' => $unread]) @if (isset($current) && $item->is($current)) aria-current="page" @endif>
            <span class="c-ava" style="background:linear-gradient(135deg,#60a5fa,#2563eb);color:#fff" aria-hidden="true">№</span>
            <span class="c-thread-text">
                <b>№{{ $item->id }} · {{ $item->subject }}</b>
                <p>{{ __("messages.support.categories.{$item->category}") }} · <span @class(['c-up' => $item->status === 'answered'])>{{ __("messages.support.statuses.{$item->status}") }}</span></p>
            </span>
            <time datetime="{{ $item->updated_at->toIso8601String() }}">{{ $item->updated_at->diffForHumans(short: true) }}</time>
            @if ($unread)<span class="c-unread" aria-label="{{ __('messages.unread') }}"></span>@endif
        </a>
    @empty
        <p class="c-muted" style="padding:16px;font-size:14px">{{ __('messages.support.empty') }}</p>
    @endforelse
</nav>
