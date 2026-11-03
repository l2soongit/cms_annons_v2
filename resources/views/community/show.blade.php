{{-- Тема форуму (демо design/community/community-v2-d.html): перше повідомлення — сама тема, далі відповіді; картка автора зліва --}}
@php
    use App\Support\HomePageData;
    use App\Support\StructuredData;
    $cover = $topic->cover ? HomePageData::url($topic->cover) : null;
    $ogImage = $cover ?? HomePageData::url($topic->category->game?->cover);
    $myTopicVote = $myVotes['topic-'.$topic->id] ?? 0;
    $pinned = $topic->is_pinned || ($topic->pinned_until && $topic->pinned_until->isFuture());
    $whom = function ($user) use ($staffIds, $ownerIds, $serverOwnerIds) {
        $isStaff = in_array($user->id, $staffIds, true);
        $isOwner = in_array($user->id, $ownerIds, true) || in_array($user->id, $serverOwnerIds, true);

        return [$isStaff, $isOwner];
    };
@endphp
<x-layouts.home :title="$topic->title.' | graj.club'" :description="$topic->excerpt ?: __('community.lead')" :assets="['resources/css/home.css', 'resources/css/community.css', 'resources/css/forum.css', 'resources/js/community.js']">
<x-slot:head>
  @if ($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endif
  @if (! $topic->isPublished())<meta name="robots" content="noindex">@endif
  @if ($topic->isPublished())<x-json-ld :data="StructuredData::forumTopic($topic, $posts->getCollection())" />@endif
</x-slot:head>
@include('community.partials.skin')
<div class="wrap cm f">
  @include('community.partials.sub')
  <nav class="f-crumbs" aria-label="{{ __('ui.breadcrumbs') }}">
    <a href="{{ route('community') }}">{{ __('community.forum.title') }}</a> ›
    <a href="{{ route('community') }}">{{ __('community.groups.'.$topic->category->group) }}</a> ›
    @if ($topic->category->parent)<a href="{{ route('community.category', $topic->category->parent) }}">{{ $topic->category->parent->name }}</a> ›@endif
    <a href="{{ route('community.category', $topic->category) }}">{{ $topic->category->name }}</a>
  </nav>

  <div class="f-title">
    <div>
      <h1>@include('community.partials.pfx'){{ $topic->title }}</h1>
      <div class="f-tmeta">
        <span>✍️ <b>{{ $topic->user->nickname ?: $topic->user->name }}</b></span>
        <span>🕒 {{ ($topic->published_at ?? $topic->created_at)->translatedFormat('j M Y') }}</span>
        <a href="#thread">💬 {{ trans_choice('community.replies', $topic->posts_count, ['n' => $topic->posts_count]) }}</a>
        <span>👁 {{ number_format($topic->views, 0, ',', ' ') }}</span>
        @if ($pinned)<span>📌 {{ __('community.forum.pinned_flag') }}</span>@endif
        @if ($topic->is_locked)<span>🔒 {{ __('community.forum.locked_flag') }}</span>@endif
      </div>
    </div>
    <div class="f-acts">
      @auth<button type="button" class="f-btn" data-subscribe="topic" data-id="{{ $topic->id }}" aria-pressed="{{ $subscribed ? 'true' : 'false' }}"
        data-on="🔔 {{ __('community.subscribe.following') }}" data-off="🔕 {{ __('community.subscribe.follow') }}">{{ $subscribed ? '🔔 '.__('community.subscribe.following') : '🔕 '.__('community.subscribe.follow') }}</button>@endauth
      <button type="button" class="f-btn" data-share="{{ route('community.topic', $topic) }}" data-title="{{ $topic->title }}" data-done="{{ __('community.copied') }}" aria-label="{{ __('community.share') }}">↗</button>
      @if ($canEdit)<a class="f-btn" href="{{ route('community.edit', $topic) }}">✎ {{ __('community.edit') }}</a>@endif
    </div>
  </div>

  @if (session('status'))<div class="cm-flash" role="status" style="margin:0 0 16px">{{ session('status') }}</div>@endif
  @if (! $topic->isPublished())
    <div class="cm-flash warn" role="status" style="margin:0 0 16px">
      <b>{{ __('community.status.'.$topic->status) }}.</b>
      {{ $topic->status === 'pending' ? __('community.pending_note') : ($topic->reject_reason ? __('community.mail.reason', ['reason' => $topic->reject_reason]) : '') }}
      @if ($topic->status === 'rejected' && auth()->id() === $topic->user_id) <a href="{{ route('community.edit', $topic) }}">{{ __('community.fix_resend') }}</a>@endif
    </div>
  @endif

  @if ($canModerate)
    <div class="f-modbar"><b>🛡 {{ __('community.mod.bar') }} · {{ __('community.status.'.$topic->status) }}</b>@include('community.partials.modtopic', ['topic' => $topic])</div>
  @endif

  <div class="f-tools">
    <nav class="f-acts" aria-label="{{ __('community.forum.sort') }}">
      @foreach (['old', 'best', 'new'] as $o)<a class="f-btn sm" href="{{ request()->fullUrlWithQuery(['order' => $o, 'page' => null]) }}#thread" @if ($sort === $o) style="border-color:var(--amber)" aria-current="page" @endif>{{ __('community.order.'.$o) }}</a>@endforeach
    </nav>
    @include('community.partials.pager', ['p' => $posts, 'fragment' => 'thread'])
  </div>

  {{-- Перше повідомлення — сама тема (на кожній сторінці обговорення, щоб було видно, про що мова) --}}
  @php [$tStaff, $tOwner] = $whom($topic->user); @endphp
  <article class="f-post op" id="topic">
    @include('community.partials.postbit', ['author' => $topic->user])
    <div class="f-pmain">
      <div class="f-phead">
        <span class="f-whom">@include('community.partials.ava', ['user' => $topic->user])<span>@include('community.partials.name', ['user' => $topic->user])@if ($tStaff)<span class="f-badge staff">{{ __('community.roles.team') }}</span>@endif @if ($tOwner)<span class="f-badge owner">{{ __('community.roles.owner') }}</span>@endif</span></span>
        <span class="d">{{ ($topic->published_at ?? $topic->created_at)->translatedFormat('j M Y · H:i') }}</span>
        <span class="r"><a href="#topic">#1</a></span>
      </div>
      <div class="f-pbody">
        @if ($cover)<img class="f-cover" src="{{ $cover }}" alt="">@endif
        {!! $body !!}
        @if ($topic->type === 'clan' && $topic->meta)
          <div class="f-clan">@foreach ($topic->meta as $key => $value)<span class="cm-chip">{{ __('community.clan.'.$key) }}: {{ $value }}</span>@endforeach</div>
        @endif
        @if ($topic->server)
          @php $gicon = HomePageData::url($topic->server->game->icon) ?? \App\Support\GameNav::builtinLogo($topic->server->game); $srvSub = \App\Community\Subscriptions::isSubscribed(auth()->user(), $topic->server); @endphp
          <div class="f-srv">
            @if ($gicon)<img src="{{ $gicon }}" alt="">@endif
            <div><b>{{ $topic->server->name }}</b><small>{{ $topic->server->game->shortName() }} · {{ $topic->server->fieldLabels() }} · {{ __('community.online_now', ['n' => $topic->server->players]) }}</small></div>
            @auth<button type="button" class="f-btn sm" data-subscribe="server" data-id="{{ $topic->server->id }}" aria-pressed="{{ $srvSub ? 'true' : 'false' }}"
              data-on="{{ __('community.subscribe.following_server') }}" data-off="{{ __('community.subscribe.follow_server') }}">{{ $srvSub ? __('community.subscribe.following_server') : __('community.subscribe.follow_server') }}</button>@endauth
            <a class="f-btn sm" href="{{ route('servers.show', $topic->server) }}">{{ __('community.server_page') }} →</a>
          </div>
        @endif
      </div>

      @include('community.partials.signature', ['author' => $topic->user])

      @if ($poll)
        @php $total = array_sum($pollVotes); $voted = $myPollVotes !== []; $showResults = $voted || $poll->isClosed() || ! auth()->check(); @endphp
        <form class="cm-card cm-poll f-poll" id="poll" method="post" action="{{ route('community.poll', $poll) }}">
          @csrf
          <h3>📊 {{ $poll->question }}</h3>
          @foreach ($poll->options as $i => $option)
            @php $n = $pollVotes[$i] ?? 0; $pct = $total ? round($n / $total * 100) : 0; @endphp
            <label @class(['cm-opt', 'mine' => in_array($i, $myPollVotes, true)])>
              @if (! $poll->isClosed() && auth()->check())<input type="{{ $poll->multiple ? 'checkbox' : 'radio' }}" name="options[]" value="{{ $i }}" @checked(in_array($i, $myPollVotes, true))>@endif
              <span class="t">{{ $option }}</span>
              @if ($showResults)<span class="p num">{{ $pct }}% · {{ $n }}</span><i style="width: {{ $pct }}%"></i>@endif
            </label>
          @endforeach
          <div class="cm-poll-foot"><span class="cm-meta">{{ trans_choice('community.poll.votes', $total, ['n' => $total]) }}@if ($poll->closes_at) · {{ $poll->isClosed() ? __('community.poll.closed') : __('community.poll.until', ['date' => $poll->closes_at->translatedFormat('j F')]) }}@endif</span>
            @if (! $poll->isClosed())
              @auth <button class="f-btn sm">{{ $voted ? __('community.poll.revote') : __('community.poll.vote') }}</button>
              @else <a class="cm-link" href="{{ route('login') }}">{{ __('community.poll.login') }}</a>
              @endauth
            @endif
          </div>
        </form>
      @endif

      <div class="f-pfoot">
        <div class="l">
          @auth @if (auth()->id() !== $topic->user_id) @include('community.partials.report', ['target' => 'topic', 'id' => $topic->id]) @endif @endauth
          @if ($canEdit)<a href="{{ route('community.edit', $topic) }}">✎ {{ __('community.edit') }}</a>@endif
        </div>
        <div class="r">
          <span class="f-like" data-react="topic" data-id="{{ $topic->id }}" data-mine="{{ $myTopicVote }}">
            <button type="button" class="up" data-value="1" aria-label="{{ __('community.react.up') }}" @guest disabled @endguest>▲</button>
            <b class="num">{{ $topic->score }}</b>
            <button type="button" class="down" data-value="-1" aria-label="{{ __('community.react.down') }}" @guest disabled @endguest>▼</button>
          </span>
          @if ($canReply)<a href="#reply" data-to-reply>↩ {{ __('community.forum.reply') }}</a>@endif
        </div>
      </div>
    </div>
  </article>

  <x-ad-banner placement="forum_post" gap="0 auto 14px" />

  <section id="thread" style="scroll-margin-top:96px">
    @foreach ($posts as $post)
      @php
          $isBest = $topic->best_post_id === $post->id;
          $postVote = $myVotes['post-'.$post->id] ?? 0;
          [$pStaff, $pOwner] = $whom($post->user);
          $canManage = auth()->check() && (auth()->id() === $post->user_id || auth()->user()->can('community.moderate'));
      @endphp
      <article @class(['f-post', 'best' => $isBest, 'pending' => $post->status === 'pending']) id="post-{{ $post->id }}">
        @include('community.partials.postbit', ['author' => $post->user])
        <div class="f-pmain">
          <div class="f-phead">
            <span class="f-whom">@include('community.partials.ava', ['user' => $post->user])<span>@include('community.partials.name', ['user' => $post->user])@if ($pStaff)<span class="f-badge staff">{{ __('community.roles.team') }}</span>@endif @if ($pOwner)<span class="f-badge owner">{{ __('community.roles.owner') }}</span>@endif</span></span>
            <span class="d">@if ($isBest)<span class="f-bestflag">✓ {{ __('community.best.label') }}</span> · @endif{{ $post->created_at->diffForHumans() }}@if ($post->edited_at) · {{ __('community.edited') }}@endif @if ($post->status === 'pending') · <b style="color:var(--amber)">{{ __('community.status.pending') }}</b>@endif</span>
            <span class="r"><a href="#post-{{ $post->id }}">#{{ ($posts->currentPage() - 1) * $posts->perPage() + $loop->iteration + 1 }}</a></span>
          </div>
          <div class="f-pbody">
            @if ($post->quote && $post->quote->status === 'published')
              <div class="f-quote"><b>{{ __('community.forum.wrote', ['name' => $post->quote->user->nickname ?: $post->quote->user->name]) }}</b><p>{{ \Illuminate\Support\Str::limit(strip_tags($post->quote->body), 260) }}</p></div>
            @endif
            {!! $post->body !!}
          </div>
          @include('community.partials.signature', ['author' => $post->user])
          @if ($canManage)
            <form class="cm-editform f-edit" id="edit-{{ $post->id }}" method="post" action="{{ route('community.posts.update', $post) }}" hidden>
              @csrf @method('put')
              <label class="sr" for="edit-body-{{ $post->id }}">{{ __('community.reply.body') }}</label>
              <textarea id="edit-body-{{ $post->id }}" name="body" rows="5" maxlength="5000" required>{{ \App\Community\Markup::source($post->source, $post->body) }}</textarea>
              <div class="row"><button type="button" class="f-btn sm" data-edit="{{ $post->id }}">{{ __('community.cancel') }}</button><button class="f-btn hot sm">{{ __('community.save') }}</button></div>
            </form>
          @endif
          <div class="f-pfoot">
            <div class="l">
              @auth @if (auth()->id() !== $post->user_id) @include('community.partials.report', ['target' => 'post', 'id' => $post->id]) @endif @endauth
              @if ($canManage)<button type="button" data-edit="{{ $post->id }}">✎ {{ __('community.edit') }}</button>@endif
              @if ($canModerate)
                @if ($post->status !== 'published')<form method="post" action="{{ route('community.mod.post', [$post, 'approve']) }}">@csrf<button style="color:var(--up)">✓ {{ __('community.mod.approve') }}</button></form>@endif
                <form method="post" action="{{ route('community.mod.post', [$post, 'hide']) }}">@csrf<button style="color:#fb7185">{{ __('community.mod.hide') }}</button></form>
              @endif
              @if ($canChooseBest && $post->status === 'published' && $post->user_id !== $topic->user_id)
                <form method="post" action="{{ route('community.posts.best', $post) }}">@csrf<button>✓ {{ $isBest ? __('community.best.unmark') : __('community.best.mark') }}</button></form>
              @endif
            </div>
            <div class="r">
              <span class="f-like" data-react="post" data-id="{{ $post->id }}" data-mine="{{ $postVote }}"><button type="button" class="up" data-value="1" aria-label="{{ __('community.react.up') }}" @guest disabled @endguest>👍 {{ __('community.forum.like') }}</button><b class="num">{{ $post->score }}</b></span>
              @if ($canReply)<button type="button" data-quote="{{ $post->id }}" data-author="{{ $post->user->nickname ?: $post->user->name }}">❝ {{ __('community.quote') }}</button>@endif
              @if ($canReply)<a href="#reply" data-to-reply>↩ {{ __('community.forum.reply') }}</a>@endif
            </div>
          </div>
        </div>
      </article>
    @endforeach
    @if ($posts->isEmpty())<p class="f-empty">{{ __('community.no_replies') }}</p>@endif
  </section>

  <div class="f-tools"><span class="f-meta">{{ __('community.forum.page_of', ['n' => $posts->currentPage(), 'of' => max(1, $posts->lastPage())]) }}</span>@include('community.partials.pager', ['p' => $posts, 'fragment' => 'thread'])</div>

  @if ($canReply)
    <div class="f-qr" id="reply">
      @include('community.partials.ava', ['user' => auth()->user()])
      <form class="cm-editor" method="post" action="{{ route('community.reply', $topic) }}" style="margin:0;padding:0">
        @csrf
        <input type="hidden" name="quote_id" value="{{ old('quote_id') }}">
        <div class="cm-quoting" hidden><span></span><button type="button" data-unquote aria-label="{{ __('community.cancel') }}">✕</button></div>
        @include('community.partials.toolbar', ['for' => 'reply-body'])
        <label class="sr" for="reply-body">{{ __('community.reply.body') }}</label>
        <textarea id="reply-body" name="body" rows="5" maxlength="5000" required placeholder="{{ __('community.reply.placeholder') }}">{{ old('body') }}</textarea>
        @error('body')<p class="cm-error">{{ $message }}</p>@enderror
        <div class="row"><span class="f-meta">{{ __('community.reply.hint') }}</span><button class="f-btn hot">↩ {{ __('community.reply.send') }}</button></div>
      </form>
    </div>
  @elseif (! auth()->check())
    <div class="f-qr guest" id="reply"><p>{{ __('community.reply.login') }}</p><a class="f-btn hot" href="{{ route('login') }}">{{ __('ui.nav.login') }}</a></div>
  @elseif ($topic->is_locked)
    <p class="f-empty">🔒 {{ __('community.locked') }}</p>
  @endif

  @if ($related->isNotEmpty())
    <section class="f-blk f-related" style="margin-top:24px">
      <div class="f-bh"><h2>{{ __('community.forum.more') }}: {{ $topic->category->name }}</h2></div>
      @foreach ($related as $item)@include('community.partials.row', ['topic' => $item, 'compact' => true])@endforeach
    </section>
  @endif
</div>
@include('community.partials.mobilebar')
</x-layouts.home>
