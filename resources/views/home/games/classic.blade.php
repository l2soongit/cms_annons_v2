{{-- «Обери свою гру»: класичний слайдер карток зі сценами ігор (керує resources/js/home.js) --}}
        <div class="slider" id="games-slider" role="region" aria-roledescription="carousel" aria-label="{{ __('ui.home.games_title') }}">
        <button class="slider-btn prev" type="button" aria-label="{{ __('ui.home.slider_prev') }}" aria-controls="worlds" hidden><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        <button class="slider-btn next" type="button" aria-label="{{ __('ui.home.slider_next') }}" aria-controls="worlds" hidden><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        <div class="worlds" id="worlds" tabindex="0">
          @foreach ($games as $game)
            <a @class(['world', 'g-'.$game['theme'], 'has-img' => $game['image']]) href="{{ $game['url'] }}">
              @if ($game['image'])
                <div class="scene scene-img" style="background-image:url('{{ $game['image'] }}')"></div>
              @else
                <div data-scene="{{ $game['theme'] }}" class="scene scene-{{ $game['theme'] }}"></div>
              @endif
              <span class="shine" aria-hidden="true"></span>
              @if ($game['icon'])<img class="gicon" src="{{ $game['icon'] }}" alt="" width="48" height="48" loading="lazy">@endif
              <h3 class="gtitle">{{ $game['name'] }}</h3>
              <div class="wmeta num"><span>{!! str_replace($fmt($game['servers']), '<b>'.$fmt($game['servers']).'</b>', e(trans_choice('ui.home.servers_n', $game['servers'], ['n' => $fmt($game['servers'])]))) !!}</span><span>{!! str_replace($fmt($game['online']), '<b>'.$fmt($game['online']).'</b>', e(__('ui.home.online', ['n' => $fmt($game['online'])]))) !!}</span></div>
              @if ($game['leader'])
                <div class="wlead">{{ __('ui.home.leader') }} <b style="color:var(--gc)">{{ $game['leader'] }}</b></div>
              @endif
            </a>
          @endforeach
        </div>
        <div class="slider-dots" role="group" aria-label="{{ __('ui.home.slider_pages') }}" data-label="{{ __('ui.home.slider_page', ['n' => '#']) }}"></div>
        </div>
