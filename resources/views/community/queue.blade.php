{{-- Черга модерації на сайті: теми й відповіді на перевірці в розділах, які модерує користувач --}}
<x-layouts.home :title="__('community.mod.title').' — '.__('community.forum.title').' | graj.club'" :assets="['resources/css/home.css', 'resources/css/community.css', 'resources/css/forum.css', 'resources/js/community.js']">
<x-slot:head><meta name="robots" content="noindex"></x-slot:head>
<div class="wrap cm f">
  @include('community.partials.sub')
  <nav class="f-crumbs" aria-label="{{ __('ui.breadcrumbs') }}"><a href="{{ route('community') }}">{{ __('community.forum.title') }}</a> › <span>{{ __('community.mod.title') }}</span></nav>
  <div class="f-title"><div><h1>🛡 {{ __('community.mod.title') }}</h1><p>{{ __('community.mod.lead') }}</p></div></div>

  @if (session('status'))<div class="cm-flash" role="status" style="margin:0 0 16px">{{ session('status') }}</div>@endif

  <div class="f-layout">
    <div>
      <section class="f-blk">
        <div class="f-bh"><h2>{{ __('community.mod.topics') }}</h2><small>{{ $topics->count() }}</small></div>
        @forelse ($topics as $topic)
          <div class="f-modrow">
            <div>
              <h3>@include('community.partials.pfx')<a href="{{ route('community.topic', $topic) }}">{{ $topic->title }}</a></h3>
              <div class="f-thmeta"><span>{{ $topic->user->nickname ?: $topic->user->name }} · {{ $topic->created_at->diffForHumans() }}</span><a href="{{ route('community.category', $topic->category) }}">{{ $topic->category->name }}</a>
                @if ($topic->flag_reason)<span class="f-pfx pending">{{ __('community.mod.flag') }}: {{ $topic->flag_reason }}</span>@endif</div>
              @if ($topic->excerpt)<p class="f-meta" style="margin-top:6px">{{ $topic->excerpt }}</p>@endif
            </div>
            @include('community.partials.modtopic', ['topic' => $topic])
          </div>
        @empty
          <p class="f-empty">{{ __('community.mod.empty') }}</p>
        @endforelse
      </section>

      <section class="f-blk">
        <div class="f-bh"><h2>{{ __('community.mod.posts') }}</h2><small>{{ $posts->count() }}</small></div>
        @forelse ($posts as $post)
          <div class="f-modrow">
            <div>
              <div class="f-thmeta"><b style="color:var(--text)">{{ $post->user->nickname ?: $post->user->name }}</b> · {{ $post->created_at->diffForHumans() }} · {{ __('community.mod.in_topic') }} <a href="{{ route('community.topic', $post->topic) }}#post-{{ $post->id }}">{{ $post->topic->title }}</a>
                @if ($post->flag_reason)<span class="f-pfx pending">{{ __('community.mod.flag') }}: {{ $post->flag_reason }}</span>@endif</div>
              <div class="f-pbody" style="padding:8px 0 0">{!! $post->body !!}</div>
            </div>
            <div class="f-modacts">
              <form method="post" action="{{ route('community.mod.post', [$post, 'approve']) }}">@csrf<button class="f-btn hot sm">✓ {{ __('community.mod.approve') }}</button></form>
              <form method="post" action="{{ route('community.mod.post', [$post, 'hide']) }}">@csrf<button class="f-btn sm">{{ __('community.mod.hide') }}</button></form>
            </div>
          </div>
        @empty
          <p class="f-empty">{{ __('community.mod.empty') }}</p>
        @endforelse
      </section>
    </div>

    @if ($sections->isNotEmpty())
    <aside class="f-side">
      <section class="f-blk"><div class="f-bh"><h2>{{ __('community.mod.sections') }}</h2></div><div class="f-bb"><div class="f-dl">
        @foreach ($sections as $s)<div><a href="{{ route('community.category', $s) }}">{{ $s->name }}</a></div>@endforeach
      </div></div></section>
    </aside>
    @endif
  </div>
</div>
@include('community.partials.mobilebar')
</x-layouts.home>
