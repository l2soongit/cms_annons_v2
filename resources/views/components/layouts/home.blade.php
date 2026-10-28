@php
    use App\Support\HomeContent;
    use App\Support\HomePageData;

    $logo = HomePageData::url(config('graj.home.logo_image'));
    $og = HomePageData::url(config('graj.home.og_image'));
    $cup = '<svg width="16" height="16" viewBox="0 0 24 24" fill="#1c1003" aria-hidden="true"><path d="M7 3h10v3h4v3a5 5 0 0 1-5 5h-.3A5 5 0 0 1 13 16.9V19h3v2H8v-2h3v-2.1A5 5 0 0 1 8.3 14H8a5 5 0 0 1-5-5V6h4z"/></svg>';
    // Секція => якір; пункт меню зникає разом із вимкненою в адмінці секцією
    $sections = ['ranking' => '#top', 'games' => '#games', 'soon' => '#soon', 'owners' => '#owners', 'plans' => '#plans'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? __('ui.meta.title') }}</title>
<meta name="description" content="{{ $description ?? __('ui.meta.description') }}">
<link rel="canonical" href="{{ url()->current() }}">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $title ?? __('ui.meta.title') }}">
<meta property="og:description" content="{{ $description ?? __('ui.meta.description') }}">
<meta property="og:url" content="{{ url()->current() }}">
@if ($og)
<meta property="og:image" content="{{ $og }}">
<meta name="twitter:card" content="summary_large_image">
@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@500;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
{{-- Шрифти ігор (усі з кирилицею) --}}
<link href="https://fonts.googleapis.com/css2?family=Cormorant:wght@700&family=Russo+One&family=Press+Start+2P&family=Stalinist+One&family=Rubik+Dirt&family=Exo+2:ital,wght@1,800&display=swap" rel="stylesheet">
@vite(['resources/css/home.css', 'resources/js/home.js'])
</head>
<body>
<div class="aurora" aria-hidden="true"><i></i><i></i><i></i></div>
<div class="toasts" id="toasts" aria-live="polite"></div>

<header>
  <div class="wrap">
    <div class="bar">
      <a class="logo" href="{{ route('home') }}">@if ($logo)<img src="{{ $logo }}" alt="graj.club" height="32">@else<i>{!! $cup !!}</i>{{ 'graj.club' }}@endif</a>
      <nav aria-label="{{ __('ui.home.nav.aria') }}">
        @foreach ($sections as $key => $href)
          @if (HomeContent::shows($key))<a href="{{ $href }}">{{ __("ui.home.nav.{$key}") }}</a>@endif
        @endforeach
      </nav>
      <div class="actions">
        @auth
          <x-notification-bell />
          <a class="btn btn-ghost btn-sm login" href="{{ route('cabinet') }}" title="{{ auth()->user()->name }}">{{ __('cabinet.title') }}</a>
        @else
          <a class="btn btn-ghost btn-sm login" href="{{ route('login') }}">{{ __('ui.nav.login') }}</a>
        @endauth
        <a class="btn btn-hot btn-sm" href="{{ route('cabinet.servers.create') }}">{{ __('ui.nav.add_server') }}</a>
      </div>
    </div>
  </div>
</header>

<main id="main">
  <x-ad-banner placement="header" gap="16px auto 0" :inset="16" :max="1152" />
  {{ $slot }}
</main>

<footer>
  <div class="wrap">
    <div class="fgrid">
      <div><a class="logo" href="{{ route('home') }}">@if ($logo)<img src="{{ $logo }}" alt="graj.club" height="32">@else<i>{!! $cup !!}</i>{{ 'graj.club' }}@endif</a><p>{{ __('ui.footer.tagline') }}</p></div>
      <div><h4>{{ __('ui.nav.games') }}</h4><ul>
        @foreach ($navGames as $navGame)
          <li><a href="{{ route('games.show', $navGame) }}">{{ $navGame->name }}</a></li>
        @endforeach
      </ul></div>
      <div><h4>{{ __('ui.footer.owners') }}</h4><ul>
        <li><a href="{{ route('cabinet.servers.create') }}">{{ __('ui.nav.add_server') }}</a></li>
        @if (HomeContent::shows('plans'))<li><a href="#plans">{{ __('ui.home.plans_label') }}</a></li>@endif
        <li><a href="#">{{ __('ui.footer.api') }}</a></li>
      </ul></div>
      <div><h4>{{ __('ui.footer.portal') }}</h4><ul>
        <li><a href="#">{{ __('ui.footer.rules') }}</a></li>
        <li><a href="{{ route('search') }}">{{ __('ui.search.title') }}</a></li>
        <li><a href="#">{{ __('ui.footer.contacts') }}</a></li>
      </ul></div>
    </div>
    <div class="fbottom"><span>© {{ date('Y') }} graj.club</span><span>{{ __('ui.footer.monitoring') }}</span></div>
  </div>
</footer>
</body>
</html>
