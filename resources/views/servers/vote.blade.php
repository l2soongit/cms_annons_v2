@php
    use App\Support\HomePageData;

    $game = $server->game;
    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
    $hours = (int) config('graj.voting.interval_hours');
    $logoUrl = HomePageData::url($server->media->first()?->path);
    $letter = mb_strtoupper(mb_substr($server->name, 0, 1));
    $units = __('serverpage.units');
    // Стан сторінки: щойно проголосував → «Дякуємо»; голос ще недоступний → відлік; інакше форма (з помилкою, якщо була)
    $state = $voted ? 'done' : ($nextVoteAt ? 'wait' : 'form');
    $client = [
        'locale' => app()->getLocale(),
        't' => ['sending' => __('serverpage.sending'), 'linkCopied' => __('serverpage.link_copied'), 'copied' => __('serverpage.copied')],
    ];
@endphp

<x-layouts.server :game="$game" :title="__('vote.title', ['server' => $server->name])" :header-banner="false">
  <x-slot:head><meta name="robots" content="noindex"></x-slot:head>

  <div class="vote-page">
    <div class="vote-main">
      <a class="back" href="{{ route('servers.show', $server) }}">{{ __('serverpage.back') }}</a>

      <section class="vcard" aria-live="polite">
        <div class="vhead">
          <span class="vlogo" aria-hidden="true">@if ($logoUrl)<img src="{{ $logoUrl }}" alt="">@else{{ $letter }}@endif</span>
          <div style="min-width:0">
            <h1 class="gtitle">{{ $server->name }}</h1>
            <div class="meta">
              @if ($server->rank)<span class="vpill">🏆 #{{ $server->rank }} · {{ $game->name }}</span>@endif
              <span @class(['vpill', 'on' => $server->is_online])>{{ $server->is_online ? __('serverpage.online', ['n' => $fmt($server->players)]) : __('ui.server.offline') }}</span>
            </div>
          </div>
        </div>

        <div class="vbody">
          @if ($state === 'form')
            <p class="lead">{{ $server->callback_url ? __('serverpage.vote_lead_bonus', ['hours' => $hours]) : __('serverpage.vote_lead', ['hours' => $hours]) }}</p>
            @if ($server->rank && $neighbor !== null)
              @php($lead = $server->rank === 1)
              @php($gap = $lead ? $server->votes_month - $neighbor : max(0, $neighbor - $server->votes_month))
              <div class="race"><div class="l"><span>{{ $lead ? __('serverpage.race_lead') : __('serverpage.race_behind', ['place' => $server->rank - 1]) }}</span><b class="num">{{ trans_choice('serverpage.race_votes', $gap, ['n' => $fmt($gap)]) }}</b></div>
                <div class="track"><i data-w="{{ $lead ? 100 : ($neighbor ? min(100, round($server->votes_month / max(1, $neighbor) * 100)) : 0) }}"></i></div></div>
            @endif
            <form class="vform" method="POST" action="{{ route('servers.vote.store', $server) }}" data-fingerprint-form>
              @csrf
              <input type="hidden" name="fingerprint">
              @error('vote')<div class="valert" role="alert"><b>{{ __('serverpage.error_title') }}</b>{{ $message }}</div>@enderror
              <div class="field"><label for="nickname">{{ __('vote.nickname') }}</label>
                <div class="input"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
                  <input id="nickname" name="nickname" maxlength="32" autocomplete="off" value="{{ old('nickname') }}" placeholder="{{ __('serverpage.nick_placeholder') }}" aria-describedby="nick-hint"></div>
                <small id="nick-hint">{{ __('vote.nickname_hint') }}</small>
                @error('nickname')<small class="err" role="alert">{{ $message }}</small>@enderror</div>
              <x-turnstile />
              <button class="btn btn-g btn-xl pulse" id="go" type="submit"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>{{ __('vote.submit') }}</button>
              <p class="small-print">{{ __('serverpage.small_print', ['hours' => $hours]) }}</p>
            </form>

          @elseif ($state === 'done')
            <div class="done">
              <svg class="check" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="44"/><path d="M30 52 L45 66 L71 37"/></svg>
              <h2>{{ __('serverpage.done_title') }}</h2>
              <p class="sub">{{ $server->rank === 1 ? __('serverpage.done_first', ['server' => $server->name]) : __('serverpage.done_climb', ['server' => $server->name]) }}</p>
              <div class="result">
                <div><small>{{ __('serverpage.votes_month') }}</small><b class="num grad" id="newvotes" data-to="{{ $server->votes_month }}">{{ $fmt($server->votes_month) }}</b></div>
                <div><small>{{ __('serverpage.place') }}</small><b class="num">{{ $server->rank ? '#'.$server->rank : '—' }}</b></div>
              </div>
              @if ($server->callback_url && ! empty($voted['nickname']))
                <div class="reward"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M20 12v10H4V12M2 7h20v5H2zM12 22V7"/></svg><span>{{ __('serverpage.reward', ['nick' => $voted['nickname']]) }}</span></div>
              @endif
              @include('servers.partials.vote-countdown')
              <div class="after">
                <a class="btn btn-ghost" href="{{ route('servers.show', $server) }}">{{ __('serverpage.to_server') }}</a>
                <button class="btn btn-ghost" type="button" id="share" data-url="{{ route('servers.show', $server) }}">{{ __('serverpage.share') }}</button>
                <a class="btn btn-g wide" href="{{ route('games.show', $game) }}">{{ __('serverpage.other_servers', ['game' => $game->name]) }}</a>
              </div>
            </div>

          @else
            <div class="waiting">
              <div class="clock"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path class="hand" d="M12 12V6"/><path d="M12 12h4"/></svg></div>
              <h2>{{ __('serverpage.wait_title') }}</h2>
              <p class="sub">{{ __('serverpage.wait_sub', ['server' => $server->name, 'hours' => $hours]) }}</p>
              @include('servers.partials.vote-countdown')
              <div class="after">
                <a class="btn btn-ghost" href="{{ route('servers.show', $server) }}">{{ __('serverpage.to_server') }}</a>
                <a class="btn btn-g" href="{{ route('games.show', $game) }}">{{ __('serverpage.other_servers', ['game' => $game->shortName()]) }}</a>
              </div>
            </div>
          @endif
        </div>
      </section>

      {{-- Окреме платне місце; лише під формою, щоб гравець спершу проголосував. Шапки тут немає --}}
      <x-ad-banner placement="vote" gap="0 auto" />
    </div>
  </div>

  <script type="application/json" id="server-data">@json($client)</script>
</x-layouts.server>
