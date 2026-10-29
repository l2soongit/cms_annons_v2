@php
    use App\Livewire\Cabinet\ServerForm;
    use App\Support\HomePageData;
    use App\Support\HtmlSanitizer;
    use Illuminate\Support\Str;

    $game = $server->game;
    $theme = $game->themeKey();
    $fmt = fn ($n) => number_format((float) $n, 0, ',', "\u{00A0}");
    $logo = $server->media->firstWhere('type', 'logo');
    $banner = $server->media->firstWhere('type', 'banner');
    $screens = $server->media->where('type', 'screenshot')->values();
    $logoUrl = HomePageData::url($logo?->path);
    $heroUrl = HomePageData::url($banner?->path) ?? HomePageData::url($game->cover);
    $shareImage = HomePageData::url(($banner ?? $logo)?->path);
    $tags = $server->fieldValues->sortBy(fn ($v) => $v->field?->sort)->values()->map(fn ($v) => $v->field?->type === 'boolean'
        ? ($v->value === '1' ? $v->field->label : null)
        : (collect($v->field?->options)->firstWhere('value', $v->value)['label'] ?? $v->value))->filter();
    $canManage = auth()->user()?->canManage($server);
    $hours = (int) config('graj.voting.interval_hours');
    $connect = $server->connectCommand();
    $avg = $rating?->total ? (float) $rating->avg : null;
    $fill = $server->is_online && $server->max_players ? (int) round($server->fillRatio() * 100) : 0;
    $letter = mb_strtoupper(mb_substr($server->name, 0, 1));
    $client = [
        'locale' => app()->getLocale(),
        'charts' => $charts,
        't' => [
            'copied' => __('serverpage.copied'), 'more' => __('serverpage.more'), 'less' => __('serverpage.less'),
            'avg' => __('serverpage.avg'), 'sum' => __('serverpage.sum'), 'players' => __('serverpage.players'), 'noData' => __('serverpage.no_data'),
            'stars' => __('serverpage.stars'), 'pick' => __('serverpage.pick'),
        ],
    ];
@endphp

<x-layouts.server :game="$game" :image="$shareImage"
                  :title="__('ui.server_page.meta_title', ['server' => $server->name, 'game' => $game->name])"
                  :description="Str::limit(HtmlSanitizer::text($server->description), 155)">
  <x-slot:head>
    @foreach (\App\Support\StructuredData::server($server, $rating?->avg, (int) ($rating?->total ?? 0), $shareImage) as $ld)
      <x-json-ld :data="$ld" />
    @endforeach
  </x-slot:head>

  {{-- Липка панель: з'являється, коли картку голосу прокручено --}}
  <div class="vbar" id="vbar" aria-hidden="true">
    <span class="mini">@if ($logoUrl)<img src="{{ $logoUrl }}" alt="">@else{{ $letter }}@endif</span>
    <div class="sp"><b>{{ $server->name }}</b><div class="meta">@if ($server->rank)#{{ $server->rank }} · @endif{{ $server->is_online ? __('serverpage.online', ['n' => $fmt($server->players)]) : __('ui.server.offline') }}</div></div>
    <button class="btn btn-ghost btn-sm copy" type="button" data-copy="{{ $connect }}">{{ __('serverpage.connect') }}</button>
    <a class="btn btn-g btn-sm" href="{{ route('servers.vote', $server) }}">{{ __('ui.server.vote') }}</a>
  </div>

  <nav class="crumbs" aria-label="{{ __('ui.breadcrumbs') }}"><a href="{{ route('home') }}">{{ __('ui.game.home') }}</a><span aria-hidden="true">/</span><a href="{{ route('games.show', $game) }}">{{ $game->name }}</a><span aria-hidden="true">/</span><span>{{ $server->name }}</span></nav>

  @if (session('status'))<p class="flash" role="status">{{ session('status') }}</p>@endif
  @if ($errors->any() && ! $errors->has('rating') && ! $errors->has('body'))<p class="flash err" role="alert">{{ $errors->first() }}</p>@endif

  {{-- Банер сервера: фото від власника, обкладинка гри або сцена стилю --}}
  <section class="shero" aria-labelledby="sname">
    @if ($heroUrl)
      <div class="scene"><div class="scene-img" style="background-image:url('{{ $heroUrl }}')"></div></div>
    @else
      <div class="scene scene-{{ $theme }}" data-scene="{{ $theme }}" aria-hidden="true"></div>
    @endif
    <div class="ident">
      <span class="slogo" aria-hidden="true">@if ($logoUrl)<img src="{{ $logoUrl }}" alt="" width="96" height="96">@else{{ $letter }}@endif</span>
      <div style="min-width:0">
        <div class="rankline">
          @if ($server->rank)<span class="medal"><b>{{ $server->rank }}</b><span>{{ __('serverpage.rank_in', ['game' => $game->name]) }}</span></span>@endif
          <span @class(['pill', 'on' => $server->is_online])>{{ $server->is_online ? __('serverpage.online', ['n' => $fmt($server->players)]) : __('ui.server.offline') }}</span>
          @if ($server->verified_at)<span class="pill ver">✓ {{ __('ui.server.verified') }}</span>@endif
        </div>
        <h1 class="gtitle" id="sname">{{ $server->name }}</h1>
        @if ($tags->isNotEmpty())<div class="tags">@foreach ($tags as $tag)<span class="tag">{{ $tag }}</span>@endforeach</div>@endif
      </div>
    </div>
    <div class="votecard" id="vote">
      <div class="big"><b class="num grad" data-count="{{ $server->votes_month }}">{{ $fmt($server->votes_month) }}</b><span>{{ trans_choice('serverpage.votes_word', $server->votes_month) }}<br>{{ __('serverpage.this_month') }}</span></div>
      <div class="trend num">
        {{ __('serverpage.per_day', ['n' => $fmt($dayVotes)]) }}
        @if ($server->rank === 1 && $neighbors['below'] !== null) · {{ __('serverpage.lead_by', ['n' => $fmt($server->votes_month - $neighbors['below'])]) }}
        @elseif ($neighbors['above'] !== null) · {{ __('serverpage.behind', ['place' => $server->rank - 1, 'n' => $fmt(max(0, $neighbors['above'] - $server->votes_month))]) }}@endif
      </div>
      <a @class(['btn', 'btn-g', 'btn-lg', 'pulse']) href="{{ route('servers.vote', $server) }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>{{ __('ui.server.vote') }}</a>
      <div class="addr"><code title="{{ $connect }}">{{ $connect }}</code><button class="btn btn-ghost btn-sm copy" type="button" data-copy="{{ $connect }}">{{ __('serverpage.copy') }}</button></div>
      <p class="hint">{{ __('serverpage.vote_hint', ['hours' => $hours]) }}@if ($server->callback_url) · {{ __('serverpage.vote_bonus') }}@endif</p>
    </div>
  </section>

  {{-- Показники --}}
  <div class="stats" data-reveal>
    <div class="stat"><small>{{ __('serverpage.players_now') }}</small>
      @if ($server->is_online)<b class="num">{{ $fmt($server->players) }}<small style="display:inline;font:600 14px var(--font)"> / {{ $fmt($server->max_players) }}</small></b>@else<b style="color:var(--down)">{{ __('ui.server.offline') }}</b>@endif
      <div class="meter"><i data-w="{{ $fill }}"></i></div></div>
    <div class="stat"><small>{{ __('serverpage.peak') }}</small>
      @if ($peak)<b class="num" data-count="{{ $peak['players'] }}">{{ $fmt($peak['players']) }}</b><span class="up">{{ __('serverpage.peak_at', ['time' => $peak['at']->format('H:00')]) }}</span>@else<b>—</b>@endif</div>
    <div class="stat"><small>{{ __('serverpage.uptime') }}</small><b class="num">{{ number_format((float) $server->uptime, 1, ',', '') }}%</b></div>
    <div class="stat"><small>{{ __('serverpage.reviews') }}</small>
      @if ($avg)<b class="num">{{ number_format($avg, 1, ',', '') }} <span class="stars" style="font-size:16px">★</span></b><span class="up" style="color:var(--muted)">{{ trans_choice('serverpage.reviews_n', $rating->total, ['n' => $rating->total]) }}</span>
      @else<b>—</b><span class="up" style="color:var(--muted)">{{ __('serverpage.no_reviews') }}</span>@endif</div>
  </div>

  <div class="layout">
    <div style="min-width:0">
      @if (HtmlSanitizer::text($server->description) !== '')
        <section class="card" data-reveal aria-labelledby="about-h">
          <h2 id="about-h">{{ __('serverpage.about') }}</h2>
          {{-- Опис очищено HTMLPurifier при збереженні --}}
          <div class="desc" id="desc">{!! HtmlSanitizer::render($server->description) !!}</div>
          <button class="more-btn" id="desc-more" type="button" hidden>{{ __('serverpage.more') }}</button>
        </section>
      @endif

      <section class="card" data-reveal aria-labelledby="stats-h" id="history">
        <h2 id="stats-h">{{ __('serverpage.stats') }}
          <span class="seg" role="group" aria-label="{{ __('ui.server_page.period') }}" id="period">
            @foreach (array_keys(\App\Servers\ServerHistory::PERIODS) as $p)
              <button type="button" aria-pressed="{{ $p === 'week' ? 'true' : 'false' }}" data-p="{{ $p }}">{{ __("ui.server_page.period_{$p}") }}</button>
            @endforeach
          </span></h2>
        <div class="charts">
          <div class="chart"><h3>{{ __('ui.server_page.chart_online') }} <b class="num" id="avg-online"></b></h3><div class="plot" id="plot-online"></div><div class="axis" id="axis-online"></div></div>
          <div class="chart"><h3>{{ __('ui.server_page.chart_votes') }} <b class="num" id="sum-votes"></b></h3><div class="plot" id="plot-votes" style="height:120px"></div><div class="axis" id="axis-votes"></div></div>
        </div>
      </section>

      @if ($screens->isNotEmpty())
        <section class="card" data-reveal aria-labelledby="shots-h">
          <h2 id="shots-h">{{ __('serverpage.shots') }} <small>{{ $screens->count() }}</small></h2>
          <div class="shots">
            @foreach ($screens as $shot)
              @php($url = HomePageData::url($shot->path))
              <button class="shot" type="button" data-full="{{ $url }}" aria-label="{{ __('ui.server_page.screenshot', ['n' => $loop->iteration]) }}"><img src="{{ $url }}" alt="" loading="lazy"></button>
            @endforeach
          </div>
        </section>
      @endif

      {{-- Відгуки --}}
      <section class="card" data-reveal aria-labelledby="rev-h" id="reviews">
        <h2 id="rev-h">{{ __('serverpage.reviews_title') }} @if ($rating?->total)<small>{{ trans_choice('serverpage.reviews_n', $rating->total, ['n' => $rating->total]) }}</small>@endif</h2>
        @if ($avg)
          @php($top = max(1, max($stars ?: [1])))
          <div class="rsum">
            <div class="avg"><b class="num">{{ number_format($avg, 1, ',', '') }}</b><div class="stars" aria-label="{{ __('reviews.stars', ['n' => number_format($avg, 1, ',', '')]) }}">{{ str_repeat('★', (int) round($avg)) }}<i>{{ str_repeat('★', 5 - (int) round($avg)) }}</i></div><small>{{ __('serverpage.average') }}</small></div>
            <div class="dist">
              @foreach ([5, 4, 3, 2, 1] as $i => $s)
                <div style="--i:{{ $i }}"><span>{{ $s }} ★</span><span class="t"><i data-w="{{ round(($stars[$s] ?? 0) / $top * 100) }}"></i></span><span class="num">{{ $stars[$s] ?? 0 }}</span></div>
              @endforeach
            </div>
          </div>
        @endif

        @auth
          @unless ($canManage)
            <form class="rform" method="POST" action="{{ route('reviews.store', $server) }}">
              @csrf
              <fieldset style="border:0;padding:0"><legend>{{ __('reviews.your_rating') }}</legend>
                <div class="picker" id="picker" role="radiogroup">
                  @for ($i = 1; $i <= 5; $i++)
                    <input class="sr" type="radio" name="rating" id="star-{{ $i }}" value="{{ $i }}" @checked((int) old('rating', $myReview?->rating) === $i) required>
                    <label for="star-{{ $i }}" aria-label="{{ __('reviews.stars', ['n' => $i]) }}">★</label>
                  @endfor
                </div>
                <div class="picker-label" id="picker-label">{{ __('serverpage.pick') }}</div>
                @error('rating')<p class="err">{{ $message }}</p>@enderror
              </fieldset>
              <label class="sr" for="rtext">{{ __('reviews.body') }}</label>
              <textarea id="rtext" name="body" maxlength="2000" required placeholder="{{ __('serverpage.placeholder') }}">{{ old('body', $myReview?->body) }}</textarea>
              @error('body')<p class="err">{{ $message }}</p>@enderror
              <div class="row"><span>{{ __('serverpage.review_note') }}</span><button class="btn btn-g btn-sm" type="submit">{{ $myReview ? __('reviews.update') : __('reviews.submit') }}</button></div>
              @if ($myReview?->status === 'pending')<p class="pending">{{ __('reviews.pending') }}</p>@endif
            </form>
          @endunless
        @else
          <p class="login-note"><a href="{{ route('login') }}">{{ __('ui.nav.login') }}</a>, {{ __('reviews.login_to_write') }}</p>
        @endauth

        @if ($reviews->isNotEmpty())
          <ul class="reviews">
            @foreach ($reviews as $review)
              <li class="review">
                <div class="top"><span class="av">{{ mb_strtoupper(mb_substr($review->user->name, 0, 1)) }}</span>
                  <div><b>{{ $review->user->name }}</b><div class="stars" style="font-size:13px" aria-label="{{ __('reviews.stars', ['n' => $review->rating]) }}">{{ str_repeat('★', $review->rating) }}<i>{{ str_repeat('★', 5 - $review->rating) }}</i></div></div>
                  <time datetime="{{ $review->created_at->toDateString() }}">{{ $review->created_at->locale(app()->getLocale())->diffForHumans() }}</time></div>
                <p style="white-space:pre-line">{{ $review->body }}</p>
                @if ($review->owner_reply)<div class="reply"><small>{{ __('reviews.owner_reply') }}</small><span style="white-space:pre-line">{{ $review->owner_reply }}</span></div>@endif
                <div class="acts">
                  @if ($canManage)
                    <details><summary>{{ $review->owner_reply ? __('reviews.edit_reply') : __('reviews.reply') }}</summary>
                      <form method="POST" action="{{ route('reviews.reply', $review) }}">@csrf<textarea name="owner_reply" rows="3" aria-label="{{ __('reviews.reply') }}">{{ $review->owner_reply }}</textarea><button class="btn btn-ghost btn-sm">{{ __('reviews.save_reply') }}</button></form>
                    </details>
                  @endif
                  @auth
                    <details><summary>{{ __('reviews.report') }}</summary>
                      <form method="POST" action="{{ route('reviews.report', $review) }}">@csrf
                        <select name="reason" required aria-label="{{ __('reviews.report_reason') }}">@foreach (\App\Models\Report::REASONS as $reason)<option value="{{ $reason }}">{{ __("reviews.reasons.{$reason}") }}</option>@endforeach</select>
                        <textarea name="comment" rows="2" maxlength="1000" placeholder="{{ __('reviews.report_comment') }}" aria-label="{{ __('reviews.report_comment') }}"></textarea>
                        <button class="btn btn-ghost btn-sm">{{ __('reviews.send_report') }}</button></form>
                    </details>
                  @endauth
                </div>
              </li>
            @endforeach
          </ul>
          @if ($reviews->hasPages())
            <div class="pager">
              @if ($reviews->previousPageUrl())<a class="btn btn-ghost btn-sm" href="{{ $reviews->fragment('reviews')->previousPageUrl() }}">←</a>@else<span></span>@endif
              @if ($reviews->nextPageUrl())<a class="btn btn-ghost btn-sm" href="{{ $reviews->fragment('reviews')->nextPageUrl() }}">→</a>@endif
            </div>
          @endif
        @else
          <p class="empty-note">{{ __('reviews.empty') }}</p>
        @endif
      </section>
    </div>

    <aside>
      <section class="card" data-reveal>
        <h2>{{ __('serverpage.server') }}</h2>
        <div class="uptime"><div class="ring"><svg viewBox="0 0 84 84" aria-hidden="true"><circle class="bg" cx="42" cy="42" r="36" fill="none" stroke-width="8"/><circle class="fg" id="ring" data-value="{{ (float) $server->uptime }}" cx="42" cy="42" r="36" fill="none" stroke-width="8" stroke-dasharray="226.2" stroke-dashoffset="226.2"/></svg><b class="num">{{ number_format((float) $server->uptime, 1, ',', '') }}%</b></div>
          <p>{{ __('serverpage.uptime') }}<br><b>{{ $server->checked_at ? __('serverpage.checked', ['time' => $server->checked_at->locale(app()->getLocale())->diffForHumans()]) : __('serverpage.not_checked') }}</b></p></div>
        <div class="facts">
          <div class="fact"><span>{{ __('ui.server.status') }}</span><b style="color:{{ $server->is_online ? 'var(--up)' : 'var(--down)' }}">{{ $server->is_online ? __('ui.server.online') : __('ui.server.offline') }}</b></div>
          @if ($server->current_map)<div class="fact"><span>{{ __('serverpage.map') }}</span><b>{{ $server->current_map }}</b></div>@endif
          @if ($server->is_online && $server->ping_ms)<div class="fact"><span>{{ __('serverpage.ping') }}</span><b>{{ __('serverpage.ping_ms', ['n' => $server->ping_ms]) }}</b></div>@endif
          @if ($server->is_online && $server->queue)<div class="fact"><span>{{ __('serverpage.queue') }}</span><b>{{ $server->queue }}</b></div>@endif
          @if ($server->is_online && $server->game_time)<div class="fact"><span>{{ __('serverpage.game_time') }}</span><b>{{ $server->game_time }}</b></div>@endif
          @if ($server->last_wiped_at)<div class="fact"><span>{{ __('serverpage.wiped') }}</span><b>{{ $server->last_wiped_at->locale(app()->getLocale())->diffForHumans() }}</b></div>@endif
          @if ($server->opens_at)<div class="fact"><span>{{ $server->opens_at->isFuture() ? __('serverpage.opens') : __('serverpage.opened') }}</span><b>{{ $server->opens_at->translatedFormat('j F Y') }}</b></div>@endif
          @if ($server->languages)<div class="fact"><span>{{ __('ui.server_page.languages') }}</span><b class="flags">@foreach ($server->languages as $code)<span title="{{ ServerForm::LANGUAGES[$code] ?? $code }}">{{ mb_strtoupper($code) }}</span>@endforeach</b></div>@endif
          @if ($server->country)<div class="fact"><span>{{ __('serverpage.hosting') }}</span><b>{{ ServerForm::countryName($server->country) }}</b></div>@endif
          <div class="fact"><span>{{ __('serverpage.since') }}</span><b>{{ $server->created_at->translatedFormat('F Y') }}</b></div>
        </div>
      </section>

      @if ($server->website || $server->discord || $server->youtube || auth()->check())
        <section class="card" data-reveal>
          @if ($server->website || $server->discord || $server->youtube)
            <h2>{{ __('serverpage.links') }}</h2>
            <div class="links">
              @if ($server->website)<a class="link" href="{{ $server->website }}" rel="nofollow noopener" target="_blank"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>{{ __('ui.server_page.website') }}<small>{{ parse_url($server->website, PHP_URL_HOST) }}</small></a>@endif
              @if ($server->discord)<a class="link" href="{{ $server->discord }}" rel="nofollow noopener" target="_blank"><svg width="18" height="18" viewBox="0 0 24 24" fill="#5865f2" aria-hidden="true"><path d="M20 5a17 17 0 0 0-4.3-1.3l-.2.4a13 13 0 0 1 3.8 1.9 14 14 0 0 0-12.6 0 13 13 0 0 1 3.8-1.9l-.2-.4A17 17 0 0 0 4 5C1.4 9 .7 12.8 1 16.6a17 17 0 0 0 5.2 2.6l1.1-1.7a11 11 0 0 1-1.7-.8l.4-.3a12 12 0 0 0 12 0l.4.3-1.7.8 1.1 1.7a17 17 0 0 0 5.2-2.6C23.3 12.2 22.3 8.4 20 5ZM8.7 14.3c-1 0-1.8-.9-1.8-2s.8-2 1.8-2 1.8.9 1.8 2-.8 2-1.8 2Zm6.6 0c-1 0-1.8-.9-1.8-2s.8-2 1.8-2 1.8.9 1.8 2-.8 2-1.8 2Z"/></svg>Discord</a>@endif
              @if ($server->youtube)<a class="link" href="{{ $server->youtube }}" rel="nofollow noopener" target="_blank"><svg width="18" height="18" viewBox="0 0 24 24" fill="#ff0033" aria-hidden="true"><path d="M23 7.2a3 3 0 0 0-2.1-2.1C19 4.6 12 4.6 12 4.6s-7 0-8.9.5A3 3 0 0 0 1 7.2 31 31 0 0 0 .5 12 31 31 0 0 0 1 16.8a3 3 0 0 0 2.1 2.1c1.9.5 8.9.5 8.9.5s7 0 8.9-.5a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .5-4.8 31 31 0 0 0-.5-4.8ZM9.7 15.1V8.9l5.8 3.1-5.8 3.1Z"/></svg>YouTube</a>@endif
            </div>
          @endif
          @auth
            <div class="owner-tools">
              {{-- Приватне повідомлення власнику (ТЗ 3.8) --}}
              @if ($server->user_id !== auth()->id())
                <details @if ($errors->message->any()) open @endif><summary>{{ __('messages.write_owner') }}</summary>
                  <form method="POST" action="{{ route('servers.message', $server) }}">@csrf
                    <textarea name="body" rows="3" maxlength="2000" required placeholder="{{ __('messages.placeholder') }}" aria-label="{{ __('messages.placeholder') }}">{{ old('body') }}</textarea>
                    @error('body', 'message')<p style="color:var(--down);font-size:13px">{{ $message }}</p>@enderror
                    <button class="btn btn-ghost btn-sm">{{ __('messages.send') }}</button></form>
                </details>
              @endif
              <details><summary>{{ __('ui.server_page.report') }}</summary>
                <form method="POST" action="{{ route('servers.report', $server) }}">@csrf
                  <select name="reason" required aria-label="{{ __('reviews.report_reason') }}">@foreach (\App\Models\Report::REASONS as $reason)<option value="{{ $reason }}">{{ __("reviews.reasons.{$reason}") }}</option>@endforeach</select>
                  <textarea name="comment" rows="2" maxlength="1000" placeholder="{{ __('reviews.report_comment') }}" aria-label="{{ __('reviews.report_comment') }}"></textarea>
                  <button class="btn btn-ghost btn-sm">{{ __('reviews.send_report') }}</button></form>
              </details>
            </div>
          @endauth
        </section>
      @endif

      <x-ad-banner placement="sidebar" />
    </aside>
  </div>

  @if ($similar->isNotEmpty())
    <section class="similar" data-reveal aria-labelledby="sim-h">
      <h2 id="sim-h">{{ __('serverpage.similar') }}</h2>
      <div class="sim-row">
        @foreach ($similar as $s)
          @php($sLogo = HomePageData::url($s->media->first()?->path))
          <a class="sim" href="{{ route('servers.show', $s) }}">
            <div class="top"><span class="mini">@if ($sLogo)<img src="{{ $sLogo }}" alt="">@else{{ mb_strtoupper(mb_substr($s->name, 0, 1)) }}@endif</span>
              <div style="min-width:0"><b>{{ $s->name }}</b><small>{{ $s->fieldLabels() }}</small></div></div>
            <div class="meta"><span>{!! $s->is_online ? str_replace(':n', '<b class="num">'.$fmt($s->players).'</b>', e(__('serverpage.online'))) : e(__('ui.server.offline')) !!}</span><span class="num">{{ $fmt($s->votes_month) }} {{ trans_choice('serverpage.votes_word', $s->votes_month) }}</span></div>
          </a>
        @endforeach
      </div>
    </section>
  @endif

  <div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="{{ __('serverpage.shots') }}">
    <div class="frame" id="lb-frame"></div>
    <button class="nav prev" type="button" aria-label="←">‹</button><button class="nav next" type="button" aria-label="→">›</button>
    <button class="close" type="button" aria-label="✕">✕</button><span class="count" id="lb-count"></span>
  </div>

  <script type="application/json" id="server-data">@json($client)</script>
</x-layouts.server>
