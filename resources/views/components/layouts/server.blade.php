{{-- Сторінки сервера й голосування (design/server): стиль бере гра сервера (.g-<тема>), шапка — як у тем ігор --}}
@props(['game', 'title', 'description' => null, 'image' => null, 'headerBanner' => true])
@php
    use App\Support\HomePageData;

    $theme = $game->themeKey();
    // Шрифт заголовків гри (усі з кирилицею)
    $fonts = ['l2' => 'family=Cormorant:wght@700', 'gta' => 'family=Russo+One', 'mc' => 'family=Press+Start+2P', 'dayz' => 'family=Stalinist+One', 'rust' => 'family=Rubik+Dirt', 'cs' => 'family=Exo+2:ital,wght@1,800'][$theme] ?? null;
    $logo = HomePageData::url(config('graj.home.logo_image'));
    $og = $image ?? HomePageData::url($game->cover) ?? HomePageData::url(config('graj.home.og_image'));
    $cup = '<svg width="16" height="16" viewBox="0 0 24 24" fill="#1c1003" aria-hidden="true"><path d="M7 3h10v3h4v3a5 5 0 0 1-5 5h-.3A5 5 0 0 1 13 16.9V19h3v2H8v-2h3v-2.1A5 5 0 0 1 8.3 14H8a5 5 0 0 1-5-5V6h4z"/></svg>';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
<meta name="description" content="{{ $description ?? __('ui.meta.description') }}">
<link rel="canonical" href="{{ url()->current() }}">
<x-locale-alternates />
<meta property="og:type" content="website">
<meta property="og:site_name" content="graj.club">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description ?? __('ui.meta.description') }}">
<meta property="og:url" content="{{ url()->current() }}">
@if ($og)
<meta property="og:image" content="{{ $og }}">
<meta name="twitter:card" content="summary_large_image">
@endif
{{ $head ?? '' }}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@500;700;800&family=Manrope:wght@400;500;600;700;800{{ $fonts ? '&'.$fonts : '' }}&display=swap" rel="stylesheet">
@vite(['resources/css/server.css', 'resources/js/server.js'])
<x-layouts.mobile-bar />
</head>
<body class="g-{{ $theme }}">
<div class="glow-bg" aria-hidden="true"></div>

<header>
  <div class="wrap">
    <div class="bar">
      <a class="logo" href="{{ route('home') }}">@if ($logo)<img src="{{ $logo }}" alt="graj.club" height="32">@else<i>{!! $cup !!}</i>{{ 'graj.club' }}@endif</a>
      <nav class="games-nav" aria-label="{{ __('ui.nav.games') }}">
        @foreach ($navGames as $navGame)
          <a href="{{ route('games.show', $navGame) }}" @if ($navGame->is($game)) aria-current="page" @endif>{{ $navGame->navName() }}</a>
        @endforeach
      </nav>
      <div class="actions">
        <x-locale-switcher />
        @auth
          <x-notification-bell />
          <a class="btn btn-ghost btn-sm login" href="{{ route('cabinet') }}" title="{{ auth()->user()->name }}">{{ __('cabinet.title') }}</a>
        @else
          <a class="btn btn-ghost btn-sm login" href="{{ route('login') }}">{{ __('ui.nav.login') }}</a>
        @endauth
        <span class="header-cta"><a class="btn btn-g btn-sm" href="{{ route('cabinet.servers.create', ['game' => $game->slug]) }}">{{ __('ui.nav.add_server') }}</a></span>
      </div>
    </div>
  </div>
</header>

<main class="wrap" id="main">
  @if ($headerBanner)<x-ad-banner placement="header" gap="16px auto 0" />@endif
  {{ $slot }}
</main>

<footer><div class="wrap"><span>© {{ date('Y') }} graj.club</span><span>{{ __('ui.footer.monitoring') }}</span></div></footer>
<x-cookie-notice />
</body>
</html>
