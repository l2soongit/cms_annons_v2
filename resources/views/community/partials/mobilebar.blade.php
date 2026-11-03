{{-- Нижня панель спільноти на телефоні: розділи під великим пальцем і кнопка «написати» / «відповісти» посередині --}}
@php
    $onTopic = request()->routeIs('community.topic');
    $plusHref = $onTopic && ($canReply ?? false) ? '#reply' : (auth()->check() ? route('community.create', array_filter(['category' => ($category ?? null)?->id])) : route('login'));
    $plusLabel = $onTopic && ($canReply ?? false) ? __('community.reply.send') : __('community.compose.button');
@endphp
<nav class="cm-mbar" aria-label="{{ __('community.tabs.aria') }}">
  <a href="{{ route('community') }}" @if (request()->routeIs('community', 'community.category')) aria-current="page" @endif><span aria-hidden="true">🏠</span>{{ __('community.tabs.feed') }}</a>
  <a href="{{ route('community.forum') }}" @if (request()->routeIs('community.forum')) aria-current="page" @endif><span aria-hidden="true">🗂</span>{{ __('community.mbar.forum') }}</a>
  <a class="plus" href="{{ $plusHref }}"><span aria-hidden="true">{{ $onTopic && ($canReply ?? false) ? '💬' : '＋' }}</span>{{ $plusLabel }}</a>
  <a href="{{ route('community.news') }}" @if (request()->routeIs('community.news')) aria-current="page" @endif><span aria-hidden="true">📣</span>{{ __('community.tabs.news') }}</a>
  @auth
    <a href="{{ route('community.mine') }}" @if (request()->routeIs('community.mine')) aria-current="page" @endif><span aria-hidden="true">⭐</span>{{ __('community.mbar.mine') }}</a>
  @else
    <a href="{{ route('login') }}"><span aria-hidden="true">👤</span>{{ __('ui.nav.login') }}</a>
  @endauth
</nav>
