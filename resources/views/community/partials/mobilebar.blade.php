{{-- Нижня панель форуму на телефоні: головна форуму, нові повідомлення, «написати» / «відповісти», новини, мої теми --}}
@php
    $onTopic = request()->routeIs('community.topic');
    $replyHere = $onTopic && ($canReply ?? false);
    $plusHref = $replyHere ? '#reply' : (auth()->check() ? route('community.create', array_filter(['category' => ($category ?? null)?->id])) : route('login'));
@endphp
<nav class="cm-mbar" aria-label="{{ __('community.forum.menu') }}">
  <a href="{{ route('community') }}" @if (request()->routeIs('community')) aria-current="page" @endif><span aria-hidden="true">🗂</span>{{ __('community.mbar.home') }}</a>
  <a href="{{ route('community.latest') }}" @if (request()->routeIs('community.latest')) aria-current="page" @endif><span aria-hidden="true">🆕</span>{{ __('community.mbar.new') }}</a>
  <a class="plus" href="{{ $plusHref }}"><span aria-hidden="true">{{ $replyHere ? '💬' : '＋' }}</span>{{ $replyHere ? __('community.reply.send') : __('community.compose.button') }}</a>
  <a href="{{ route('community.news') }}" @if (request()->routeIs('community.news')) aria-current="page" @endif><span aria-hidden="true">📣</span>{{ __('community.tabs.news') }}</a>
  @auth
    <a href="{{ route('community.mine') }}" @if (request()->routeIs('community.mine')) aria-current="page" @endif><span aria-hidden="true">⭐</span>{{ __('community.mbar.mine') }}</a>
  @else
    <a href="{{ route('login') }}"><span aria-hidden="true">👤</span>{{ __('ui.nav.login') }}</a>
  @endauth
</nav>
