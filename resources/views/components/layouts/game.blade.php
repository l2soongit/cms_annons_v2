@props(['game', 'assets', 'title' => null, 'description' => null, 'fonts' => null])
@php
    use App\Support\HomePageData;

    $logo = HomePageData::url(config('graj.home.logo_image')) ?: asset('images/brand/logo.svg');
    $og = HomePageData::url($game->cover) ?? HomePageData::url(config('graj.home.og_image'));
    // Сторінка з фільтрами з ЧПУ (/lineage2/interlude/x100) — свій заголовок і опис, без дублів між сторінками
    $seoFilters = $seoFilters ?? '';
    $title ??= $seoFilters !== ''
        ? ($game->seo_title ? $game->seo_title.' — '.$seoFilters : __('ui.game.meta_title', ['game' => $game->name.' '.$seoFilters]))
        : ($game->seo_title ?: __('ui.game.meta_title', ['game' => $game->name]));
    $description ??= ($seoFilters !== '' ? $game->name.' '.$seoFilters.'. ' : '').($game->seo_description ?: $game->description ?: __('ui.meta.description'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<x-favicons />
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ url()->current() }}">
<x-locale-alternates />
<x-json-ld :data="\App\Support\StructuredData::game($game, $seoFilters)" />
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ url()->current() }}">
@if ($og)
<meta property="og:image" content="{{ $og }}">
<meta name="twitter:card" content="summary_large_image">
@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@500;700;800&family=Manrope:wght@400;500;600;700;800{{ $fonts ? '&'.$fonts : '' }}&display=swap" rel="stylesheet">
@vite($assets)
<x-layouts.mobile-bar />
</head>
<body>
{{ $background ?? '' }}
<div class="toasts" id="toasts" aria-live="polite"></div>

<header>
  <div class="wrap">
    <div class="bar">
      <a class="logo" href="{{ route('home') }}"><img src="{{ $logo }}" alt="GRAJ.CLUB" height="40" style="height:clamp(28px,8vw,40px);width:auto"></a>
      <x-game-switcher :current="$game" />
      @auth<div class="actions"><x-notification-bell /></div>@endauth
    </div>
  </div>
</header>

<main class="wrap" id="main">
  <x-ad-banner placement="header" gap="16px auto 0" />
  <nav class="crumbs" aria-label="{{ __('ui.breadcrumbs') }}"><a href="{{ route('home') }}">{{ __('ui.game.home') }}</a><span aria-hidden="true">/</span><span>{{ $game->name }}</span></nav>
  {{ $slot }}
</main>

<footer><div class="wrap"><span>© {{ date('Y') }} graj.club</span><span>{{ __('ui.footer.monitoring') }}</span>
  <span class="foot-tools"><x-locale-switcher />@auth<a href="{{ route('cabinet') }}">{{ __('cabinet.title') }}</a>@else<a href="{{ route('login') }}">{{ __('ui.nav.login') }}</a>@endauth</span></div></footer>
<x-cookie-notice />
<x-analytics />
</body>
</html>
