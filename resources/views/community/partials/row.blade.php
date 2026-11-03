{{-- Тема в списку: автор, префікс, назва, сторінки, лічильники, остання відповідь --}}
@php
    $pages = (int) ceil(max(1, $topic->posts_count) / \App\Http\Controllers\CommunityController::PER_PAGE);
    $pinned = $topic->is_pinned || ($topic->pinned_until && $topic->pinned_until->isFuture());
    $fresh = $topic->last_post_at && $topic->last_post_at->gt(now()->subDay());
@endphp
<div @class(['f-th', 'fresh' => $fresh, 'pending' => ! $topic->isPublished()])>
  @unless ($compact ?? false)@include('community.partials.ava', ['user' => $topic->user])@endunless
  <div>
    <h3>
      @if (! $topic->isPublished())<span class="f-pfx pending">{{ __('community.status.'.$topic->status) }}</span>@endif
      @include('community.partials.pfx')<a href="{{ route('community.topic', $topic) }}">{{ $topic->title }}</a>
    </h3>
    <div class="f-thmeta">
      <span>{{ $topic->user->nickname ?: $topic->user->name }} · {{ ($topic->published_at ?? $topic->created_at)->translatedFormat('j M Y') }}</span>
      @if ($showCategory ?? false)<a href="{{ route('community.category', $topic->category) }}">{{ $topic->category->game?->shortName() ?? $topic->category->name }}</a>@endif
      @if ($pages > 1)
        <span class="f-pg">@foreach (array_unique([1, 2, 3, $pages]) as $n)@if ($n <= $pages)<a href="{{ route('community.topic', $topic) }}?page={{ $n }}">{{ $n }}</a>@endif @endforeach</span>
      @endif
      @if ($pinned)<span title="{{ __('community.forum.pinned_flag') }}">📌</span>@endif
      @if ($topic->is_locked)<span title="{{ __('community.forum.locked_flag') }}">🔒</span>@endif
      @if ($topic->best_post_id)<span class="f-pfx ok">✓ {{ __('community.solved') }}</span>@endif
      @if ($topic->type === 'server_news' && $topic->server)<span>· {{ $topic->server->name }}</span>@endif
    </div>
  </div>
  <div class="f-cnt">{{ __('community.forum.replies') }}<b class="num">{{ number_format($topic->posts_count, 0, ',', ' ') }}</b>{{ __('community.forum.views') }}<b class="num">{{ number_format($topic->views, 0, ',', ' ') }}</b></div>
  <div class="f-last">
    @php $lu = $topic->lastPostUser ?? $topic->user; @endphp
    <div><b>{{ $lu->nickname ?: $lu->name }}</b>{{ ($topic->last_post_at ?? $topic->published_at ?? $topic->created_at)->diffForHumans(short: true) }}</div>
    @include('community.partials.ava', ['user' => $lu, 'size' => 's'])
  </div>
</div>
