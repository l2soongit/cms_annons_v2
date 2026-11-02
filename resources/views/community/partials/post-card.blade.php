{{-- Тема в стрічці: оцінка ▲▼, тип, автор, заголовок, опис, картка сервера чи деталі клану, лічильники --}}
@php
    use App\Support\HomePageData;
    $mine = $myVotes['topic-'.$topic->id] ?? 0;
    $pinned = $topic->is_pinned || ($topic->pinned_until && $topic->pinned_until->isFuture());
    $cover = $topic->cover ? HomePageData::url($topic->cover) : null;
@endphp
<article @class(['cm-card', 'cm-post', 'is-news' => $topic->isNews() && $topic->type === 'news', 'is-pending' => ! $topic->isPublished()])>
  <div class="cm-vote" data-react="topic" data-id="{{ $topic->id }}" data-mine="{{ $mine }}">
    <button type="button" class="up" data-value="1" aria-label="{{ __('community.react.up') }}" @auth @else disabled @endauth>▲</button>
    <b class="num">{{ $topic->score }}</b>
    <button type="button" class="down" data-value="-1" aria-label="{{ __('community.react.down') }}" @auth @else disabled @endauth>▼</button>
  </div>
  <div>
    <div class="cm-phead">
      @if ($pinned)<span class="cm-chip pin">📌 {{ __('community.pinned') }}</span>@endif
      @if (! $topic->isPublished())<span class="cm-chip pending">{{ __('community.status.'.$topic->status) }}</span>@endif
      @include('community.partials.type', ['topic' => $topic])
      <a class="cm-chip" href="{{ route('community.category', $topic->category) }}">{{ $topic->category->game?->shortName() ?? $topic->category->name }}</a>
      @include('community.partials.ava', ['user' => $topic->user, 'size' => 's']) @include('community.partials.name', ['user' => $topic->user])
      <span>· {{ ($topic->published_at ?? $topic->created_at)->diffForHumans() }}</span>
    </div>
    <h2><a href="{{ route('community.topic', $topic) }}">{{ $topic->title }}</a></h2>
    @if ($topic->excerpt)<p>{{ $topic->excerpt }}</p>@endif
    @if ($cover)<a class="cm-img" href="{{ route('community.topic', $topic) }}"><img src="{{ $cover }}" alt="" loading="lazy"></a>@endif

    @if ($topic->type === 'server_news' && $topic->server)
      <div class="cm-srvcard">
        <span @class(['dot', 'off' => ! $topic->server->is_online])></span><b>{{ $topic->server->name }}</b>
        @if ($topic->server->opens_at && $topic->server->opens_at->isFuture())<span class="cm-meta">{{ __('community.opens', ['when' => $topic->server->opens_at->diffForHumans()]) }}</span>@endif
        <a class="btn btn-ghost btn-sm" href="{{ route('servers.show', $topic->server) }}">{{ __('community.server_page') }}</a>
      </div>
    @endif
    @if ($topic->type === 'clan' && $topic->meta)
      <div class="cm-clanline">@foreach ($topic->meta as $key => $value)<span class="cm-chip">{{ __('community.clan.'.$key) }}: {{ $value }}</span>@endforeach</div>
    @endif

    <div class="cm-pfoot">
      <a href="{{ route('community.topic', $topic) }}#thread">💬 {{ trans_choice('community.comments', $topic->posts_count, ['n' => $topic->posts_count]) }}</a>
      <span>👁 {{ number_format($topic->views, 0, ',', ' ') }}</span>
      @if ($topic->best_post_id)<span class="cm-chip guide">✓ {{ __('community.solved') }}</span>@endif
      @if ($topic->lastPostUser && $topic->posts_count)
        <span class="cm-last">@include('community.partials.ava', ['user' => $topic->lastPostUser, 'size' => 's']){{ $topic->lastPostUser->nickname ?: $topic->lastPostUser->name }} · {{ $topic->last_post_at?->diffForHumans() }}</span>
      @endif
    </div>
  </div>
</article>
