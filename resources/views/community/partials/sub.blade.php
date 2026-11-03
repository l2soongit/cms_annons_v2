{{-- Друге меню форуму: нові повідомлення й теми, новини, мої теми, бонуси --}}
@php $sortNow = request('sort'); @endphp
<nav class="f-sub" aria-label="{{ __('community.forum.menu') }}">
  <a href="{{ route('community') }}" @if (request()->routeIs('community')) aria-current="page" @endif>🗂 {{ __('community.forum.title') }}</a>
  <a href="{{ route('community.latest') }}" @if (request()->routeIs('community.latest') && $sortNow !== 'new') aria-current="page" @endif>🆕 {{ __('community.forum.latest') }}</a>
  <a href="{{ route('community.latest', ['sort' => 'new']) }}" @if (request()->routeIs('community.latest') && $sortNow === 'new') aria-current="page" @endif>📝 {{ __('community.forum.new_topics') }}</a>
  <a href="{{ route('community.news') }}" @if (request()->routeIs('community.news')) aria-current="page" @endif>📣 {{ __('community.tabs.news') }}</a>
  @auth
    <a href="{{ route('community.mine') }}" @if (request()->routeIs('community.mine')) aria-current="page" @endif>⭐ {{ __('community.tabs.mine') }}</a>
    <span class="sp"></span>
    <a class="pts" href="{{ route('cabinet.bonus') }}">✨ {{ number_format(auth()->user()->bonus_balance, 0, ',', ' ') }} {{ __('community.bonus.points_short') }}</a>
  @endauth
</nav>
