@php
    use App\Support\HomePageData;
    use App\Support\StructuredData;
    $cover = $topic->cover ? HomePageData::url($topic->cover) : HomePageData::url($topic->category->game?->cover);
    $words = (int) preg_match_all('/[\pL\pN]+/u', strip_tags($topic->body));
    $minutes = max(1, (int) round($words / 200));
    $myTopicVote = $myVotes['topic-'.$topic->id] ?? 0;
@endphp
<x-layouts.home :title="$topic->title.' | graj.club'" :description="$topic->excerpt ?: __('community.lead')" :assets="['resources/css/home.css', 'resources/css/community.css', 'resources/js/community.js']">
<x-slot:head>
  @if ($cover)<meta property="og:image" content="{{ $cover }}">@endif
  @if (! $topic->isPublished())<meta name="robots" content="noindex">@endif
  @if ($topic->isPublished())<x-json-ld :data="StructuredData::forumTopic($topic, $posts->getCollection())" />@endif
</x-slot:head>
<div class="wrap cm cm-topic">
  <nav class="cm-crumbs" aria-label="{{ __('ui.breadcrumbs') }}">
    <a href="{{ route('community') }}">{{ __('community.nav') }}</a><span aria-hidden="true">/</span>
    <a href="{{ route('community.forum') }}">{{ __('community.tabs.forum') }}</a><span aria-hidden="true">/</span>
    <a href="{{ route('community.category', $topic->category) }}">{{ $topic->category->name }}</a>
  </nav>

  @if (session('status'))<div class="cm-flash" role="status">{{ session('status') }}</div>@endif
  @if (! $topic->isPublished())
    <div class="cm-flash warn" role="status">
      <b>{{ __('community.status.'.$topic->status) }}.</b>
      {{ $topic->status === 'pending' ? __('community.pending_note') : ($topic->reject_reason ? __('community.mail.reason', ['reason' => $topic->reject_reason]) : '') }}
      @if ($topic->status === 'rejected' && auth()->id() === $topic->user_id) <a href="{{ route('community.edit', $topic) }}">{{ __('community.fix_resend') }}</a>@endif
    </div>
  @endif

  <section @class(['cm-hero', 'no-img' => ! $cover])>
    @if ($cover)<img src="{{ $cover }}" alt="">@endif
    <div>
      <div class="cm-chips">@include('community.partials.type', ['topic' => $topic])@if ($topic->category->game)<span class="cm-chip">{{ $topic->category->game->shortName() }}</span>@endif<span class="cm-chip">{{ trans_choice('community.read_time', $minutes, ['n' => $minutes]) }}</span></div>
      <h1>{{ $topic->title }}</h1>
      <div class="cm-byline">
        @include('community.partials.ava', ['user' => $topic->user]) @include('community.partials.name', ['user' => $topic->user])
        <span>{{ ($topic->published_at ?? $topic->created_at)->translatedFormat('j F Y') }}</span>
        <span>👁 {{ number_format($topic->views, 0, ',', ' ') }}</span>
        <a href="#thread">💬 {{ trans_choice('community.comments', $topic->posts_count, ['n' => $topic->posts_count]) }}</a>
        @if ($canEdit)<a href="{{ route('community.edit', $topic) }}">✎ {{ __('community.edit') }}</a>@endif
      </div>
    </div>
  </section>

  <div class="cm-article-layout">
    <article>
      <div class="cm-body">{!! $body !!}</div>

      @if ($topic->type === 'clan' && $topic->meta)
        <div class="cm-clanline">@foreach ($topic->meta as $key => $value)<span class="cm-chip">{{ __('community.clan.'.$key) }}: {{ $value }}</span>@endforeach</div>
      @endif

      @if ($topic->server)
        <div class="cm-mention">
          @php $gicon = \App\Support\HomePageData::url($topic->server->game->icon) ?? \App\Support\GameNav::builtinLogo($topic->server->game); @endphp
          @if ($gicon)<img src="{{ $gicon }}" alt="">@endif
          <div><b>{{ $topic->server->name }}</b><span class="cm-meta">{{ $topic->server->game->shortName() }} · {{ $topic->server->fieldLabels() }} · {{ __('community.online_now', ['n' => $topic->server->players]) }}</span></div>
          @auth<button type="button" class="btn btn-ghost btn-sm" data-subscribe="server" data-id="{{ $topic->server->id }}" aria-pressed="{{ \App\Community\Subscriptions::isSubscribed(auth()->user(), $topic->server) ? 'true' : 'false' }}"
            data-on="{{ __('community.subscribe.following_server') }}" data-off="{{ __('community.subscribe.follow_server') }}">{{ \App\Community\Subscriptions::isSubscribed(auth()->user(), $topic->server) ? __('community.subscribe.following_server') : __('community.subscribe.follow_server') }}</button>@endauth
          <a class="btn btn-ghost btn-sm" href="{{ route('servers.show', $topic->server) }}">{{ __('community.server_page') }}</a>
        </div>
      @endif

      @if ($poll)
        @php $total = array_sum($pollVotes); $voted = $myPollVotes !== []; $showResults = $voted || $poll->isClosed() || ! auth()->check(); @endphp
        <form class="cm-card cm-poll" id="poll" method="post" action="{{ route('community.poll', $poll) }}">
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
              @auth <button class="btn btn-ghost btn-sm">{{ $voted ? __('community.poll.revote') : __('community.poll.vote') }}</button>
              @else <a class="cm-link" href="{{ route('login') }}">{{ __('community.poll.login') }}</a>
              @endauth
            @endif
          </div>
        </form>
      @endif

      <div class="cm-react">
        <div class="cm-vote row" data-react="topic" data-id="{{ $topic->id }}" data-mine="{{ $myTopicVote }}">
          <button type="button" class="up" data-value="1" aria-label="{{ __('community.react.up') }}" @guest disabled @endguest>▲</button>
          <b class="num">{{ $topic->score }}</b>
          <button type="button" class="down" data-value="-1" aria-label="{{ __('community.react.down') }}" @guest disabled @endguest>▼</button>
        </div>
        @auth<button type="button" class="cm-pill" data-subscribe="topic" data-id="{{ $topic->id }}" aria-pressed="{{ $subscribed ? 'true' : 'false' }}"
          data-on="🔔 {{ __('community.subscribe.following') }}" data-off="🔕 {{ __('community.subscribe.follow') }}">{{ $subscribed ? '🔔 '.__('community.subscribe.following') : '🔕 '.__('community.subscribe.follow') }}</button>@endauth
        <button type="button" class="cm-pill" data-share="{{ route('community.topic', $topic) }}" data-title="{{ $topic->title }}" data-done="{{ __('community.copied') }}">↗ {{ __('community.share') }}</button>
        @auth
          @if (auth()->id() !== $topic->user_id) @include('community.partials.report', ['target' => 'topic', 'id' => $topic->id]) @endif
        @endauth
      </div>

      <section class="cm-thread" id="thread">
        <div class="cm-thhead">
          <div><h2 class="cm-h2">{{ __('community.discussion') }} · {{ $topic->posts_count }}</h2>
            <div class="cm-meta">{{ __('community.thread_in') }} <a href="{{ route('community.category', $topic->category) }}">{{ $topic->category->name }}</a> · {{ trans_choice('community.subscribers', $subscribers, ['n' => $subscribers]) }}</div></div>
          <div class="cm-thsort">@foreach (['best', 'new', 'old'] as $o)<a href="{{ request()->fullUrlWithQuery(['order' => $o, 'page' => null]) }}#thread" @if ($sort === $o) aria-current="page" @endif>{{ __('community.order.'.$o) }}</a>@endforeach</div>
        </div>

        @forelse ($posts as $post)
          @php
              $isBest = $topic->best_post_id === $post->id;
              $postVote = $myVotes['post-'.$post->id] ?? 0;
              $role = in_array($post->user_id, $staffIds, true) ? 'team' : (in_array($post->user_id, $ownerIds, true) ? 'owner' : null);
              $level = \App\Community\Bonus::level($post->user);
          @endphp
          <div @class(['cm-reply', 'best' => $isBest, 'pending' => $post->status === 'pending']) id="post-{{ $post->id }}">
            <div class="who">
              @include('community.partials.ava', ['user' => $post->user])
              @include('community.partials.name', ['user' => $post->user])
              @if ($role)<span class="role {{ $role }}">{{ __('community.roles.'.$role) }}</span>@endif
              <span class="cm-meta">{{ __('community.levels.'.$level['key']) }} · {{ trans_choice('community.posts_count', $post->user->forum_posts_count, ['n' => $post->user->forum_posts_count]) }}</span>
            </div>
            <div class="msg">
              <div class="cm-meta row"><span>{{ $post->created_at->diffForHumans() }}@if ($post->edited_at) · {{ __('community.edited') }}@endif @if ($post->status === 'pending') · <b class="warn">{{ __('community.status.pending') }}</b>@endif</span>
                <span>@if ($isBest)<span class="cm-chip guide">✓ {{ __('community.best.label') }}</span>@endif <a href="#post-{{ $post->id }}">#{{ $post->id }}</a></span></div>
              @if ($post->quote && $post->quote->status === 'published')
                <blockquote class="cm-quote"><b>{{ $post->quote->user->nickname ?: $post->quote->user->name }}:</b> {{ \Illuminate\Support\Str::limit(strip_tags($post->quote->body), 220) }}</blockquote>
              @endif
              <div class="cm-postbody">{!! $post->body !!}</div>
              <div class="acts">
                <span class="cm-vote inline" data-react="post" data-id="{{ $post->id }}" data-mine="{{ $postVote }}"><button type="button" class="up" data-value="1" aria-label="{{ __('community.react.up') }}" @guest disabled @endguest>▲ <b class="num">{{ $post->score }}</b></button></span>
                @if ($canReply)<button type="button" data-quote="{{ $post->id }}" data-author="{{ $post->user->nickname ?: $post->user->name }}">{{ __('community.quote') }}</button>@endif
                @if ($canChooseBest && $post->status === 'published' && $post->user_id !== $topic->user_id)
                  <form method="post" action="{{ route('community.posts.best', $post) }}">@csrf<button>{{ $isBest ? __('community.best.unmark') : __('community.best.mark') }}</button></form>
                @endif
                @auth
                  @if (auth()->id() === $post->user_id || auth()->user()->can('community.moderate'))<button type="button" data-edit="{{ $post->id }}">{{ __('community.edit') }}</button>@endif
                  @if (auth()->id() !== $post->user_id) @include('community.partials.report', ['target' => 'post', 'id' => $post->id]) @endif
                @endauth
              </div>
              @auth
                @if (auth()->id() === $post->user_id || auth()->user()->can('community.moderate'))
                  <form class="cm-editform" id="edit-{{ $post->id }}" method="post" action="{{ route('community.posts.update', $post) }}" hidden>
                    @csrf @method('put')
                    <label class="sr" for="edit-body-{{ $post->id }}">{{ __('community.reply.body') }}</label>
                    <textarea id="edit-body-{{ $post->id }}" name="body" rows="5" maxlength="5000" required>{{ \App\Community\Markup::source($post->source, $post->body) }}</textarea>
                    <div class="row"><button type="button" class="btn btn-ghost btn-sm" data-edit="{{ $post->id }}">{{ __('community.cancel') }}</button><button class="btn btn-hot btn-sm">{{ __('community.save') }}</button></div>
                  </form>
                @endif
              @endauth
            </div>
          </div>
        @empty
          <p class="cm-empty-thread">{{ __('community.no_replies') }}</p>
        @endforelse

        @if ($posts->hasPages())<div class="cm-pages">{{ $posts->fragment('thread')->links() }}</div>@endif

        @if ($canReply)
          <form class="cm-card cm-editor" id="reply" method="post" action="{{ route('community.reply', $topic) }}">
            @csrf
            <input type="hidden" name="quote_id" value="{{ old('quote_id') }}">
            <div class="cm-quoting" hidden><span></span><button type="button" data-unquote aria-label="{{ __('community.cancel') }}">✕</button></div>
            @include('community.partials.toolbar', ['for' => 'reply-body'])
            <label class="sr" for="reply-body">{{ __('community.reply.body') }}</label>
            <textarea id="reply-body" name="body" rows="5" maxlength="5000" required placeholder="{{ __('community.reply.placeholder') }}">{{ old('body') }}</textarea>
            @error('body')<p class="cm-error">{{ $message }}</p>@enderror
            <div class="row"><span class="cm-meta">{{ __('community.reply.hint') }}</span><button class="btn btn-hot btn-sm">{{ __('community.reply.send') }}</button></div>
          </form>
        @elseif (! auth()->check())
          <div class="cm-card cm-editor"><p>{{ __('community.reply.login') }}</p><a class="btn btn-hot btn-sm" href="{{ route('login') }}">{{ __('ui.nav.login') }}</a></div>
        @elseif ($topic->is_locked)
          <p class="cm-empty-thread">🔒 {{ __('community.locked') }}</p>
        @endif
      </section>
    </article>

    <aside class="cm-aside sticky">
      @if (count($toc) > 1)
        <div class="cm-card"><h3>{{ __('community.toc') }}</h3><nav class="cm-toc">@foreach ($toc as $item)<a href="#{{ $item['id'] }}">{{ $item['text'] }}</a>@endforeach<a href="#thread">{{ __('community.discussion') }} · {{ $topic->posts_count }}</a></nav></div>
      @endif
      <div class="cm-card"><h3>{{ __('community.in_forum') }}</h3><div class="cm-where">
        <div>{{ __('community.section') }} <a href="{{ route('community.category', $topic->category) }}"><b>{{ $topic->category->name }}</b></a></div>
        <div>{{ __('community.stats.posts') }} <b class="num">{{ $topic->posts_count }}</b></div>
        <div>{{ __('community.followers') }} <b class="num">{{ $subscribers }}</b></div>
        @if ($topic->last_post_at)<div>{{ __('community.last_reply') }} <b>{{ $topic->last_post_at->diffForHumans() }}</b></div>@endif
      </div></div>
      @if ($related->isNotEmpty())
        <div class="cm-card"><h3>{{ __('community.related') }}</h3><div class="cm-list">
          @foreach ($related as $item)<a href="{{ route('community.topic', $item) }}">{{ $item->title }}<span class="cm-meta">{{ __('community.types.'.$item->type) }} · {{ trans_choice('community.replies', $item->posts_count, ['n' => $item->posts_count]) }}</span></a>@endforeach
        </div></div>
      @endif
    </aside>
  </div>
</div>
</x-layouts.home>
