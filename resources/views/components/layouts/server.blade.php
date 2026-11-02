{{-- Сторінки сервера й голосування (design/server): стиль бере гра сервера (.g-<тема>), шапка — як у тем ігор --}}
@props(['game', 'title', 'description' => null, 'image' => null, 'headerBanner' => true])
@php
    use App\Support\HomePageData;

    $theme = $game->themeKey();
    // Шрифт заголовків гри (усі з кирилицею)
    $fonts = ['l2' => 'family=Cormorant:wght@700', 'gta' => 'family=Russo+One', 'mc' => 'family=Press+Start+2P', 'dayz' => 'family=Stalinist+One', 'rust' => 'family=Rubik+Dirt', 'cs' => 'family=Exo+2:ital,wght@1,800', 'wow' => 'family=Marcellus', 'aion' => 'family=Cormorant+SC:wght@700', 'mu' => 'family=Cinzel:wght@700'][$theme] ?? null;
    $logo = HomePageData::url(config('graj.home.logo_image')) ?: asset('images/brand/logo.svg');
    $og = $image ?? HomePageData::url($game->cover) ?? HomePageData::url(config('graj.home.og_image'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<x-favicons />
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
      <a class="logo" href="{{ route('home') }}"><picture>@if ($logo === asset('images/brand/logo.svg'))<source media="(max-width: 480px)" srcset="{{ asset('images/brand/icon.svg') }}">@endif<img src="{{ $logo }}" alt="GRAJ.CLUB" height="40" style="height:clamp(28px,8vw,40px);width:auto"></picture></a>
      <x-game-switcher :current="$game" />
      @auth<div class="actions"><x-notification-bell /></div>@endauth
    </div>
  </div>
</header>

<main class="wrap" id="main">
  @if ($headerBanner)<x-ad-banner placement="header" gap="16px auto 0" />@endif
  {{ $slot }}
</main>

<footer><div class="wrap"><span>© {{ date('Y') }} graj.club</span><span>{{ __('ui.footer.monitoring') }}</span>
  <span class="foot-tools"><x-locale-switcher />@auth<a href="{{ route('cabinet') }}">{{ __('cabinet.title') }}</a>@else<a href="{{ route('login') }}">{{ __('ui.nav.login') }}</a>@endauth</span></div></footer>
<x-cookie-notice />
<x-analytics />
</body>
</html>
