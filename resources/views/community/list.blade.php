{{-- Список тем (розділ, нові повідомлення, новини, мої теми): підрозділи, фільтри, закріплені окремо, банер після 5-ї теми --}}
@php
    $heading = match ($tab) {
        'category' => $category->name,
        'news' => __('community.tabs.news'),
        'mine' => __('community.tabs.mine'),
        default => $sort === 'new' ? __('community.forum.new_topics') : __('community.forum.latest'),
    };
    $lead = match ($tab) {
        'category' => $category->description,
        'news' => __('community.forum.news_lead'),
        'mine' => __('community.forum.mine_lead'),
        default => __('community.forum.latest_lead'),
    };
    $isPinned = fn ($t) => $t->is_pinned || ($t->pinned_until && $t->pinned_until->isFuture());
    $items = $topics->getCollection();
    $pinned = $items->filter($isPinned);
    $rest = $items->reject($isPinned)->values();
    $createUrl = auth()->check() ? route('community.create', array_filter(['category' => $category?->id])) : route('login');
@endphp
<x-layouts.home :title="$heading.' — '.__('community.forum.title').' | graj.club'" :description="$lead ?: __('community.forum.lead')" :assets="['resources/css/home.css', 'resources/css/community.css', 'resources/css/forum.css', 'resources/js/community.js']">
@include('community.partials.skin')
<div class="wrap cm f">
  @include('community.partials.sub')
  <nav class="f-crumbs" aria-label="{{ __('ui.breadcrumbs') }}">
    <a href="{{ route('community') }}">{{ __('community.forum.title') }}</a>
    @if ($category)
      › <a href="{{ route('community') }}">{{ __('community.groups.'.$category->group) }}</a>
      @if ($category->parent) › <a href="{{ route('community.category', $category->parent) }}">{{ $category->parent->name }}</a>@endif
    @endif
    › <span>{{ $heading }}</span>
  </nav>
  <div class="f-title">
    <div><h1>{{ $heading }}</h1>@if ($lead)<p>{{ $lead }}</p>@endif</div>
    <div class="f-acts">
      @if ($category && auth()->check())
        <button type="button" class="f-btn" data-subscribe="category" data-id="{{ $category->id }}" aria-pressed="{{ $subscribed ? 'true' : 'false' }}"
          data-on="🔔 {{ __('community.subscribe.following') }}" data-off="🔕 {{ __('community.subscribe.follow') }}">{{ $subscribed ? '🔔 '.__('community.subscribe.following') : '🔕 '.__('community.subscribe.follow') }}</button>
      @endif
      @if ($canPost || ! auth()->check())<a class="f-btn hot" href="{{ $createUrl }}">+ {{ __('community.forum.create') }}</a>@endif
    </div>
  </div>

  @if (session('status'))<div class="cm-flash" role="status" style="margin:0 0 16px">{{ session('status') }}</div>@endif

  @if ($category?->game)
    <x-ad-banner placement="forum_game" :game="$category->game" gap="0 auto 18px" />
  @else
    <x-ad-banner placement="forum_top" gap="0 auto 18px" />
  @endif

  <div class="f-layout">
    <div>
      @if ($children->isNotEmpty())
        <section class="f-blk">
          @foreach ($children as $child)
            @include('community.partials.node', ['cat' => $child->setRelation('children', collect()), 'info' => ['topics' => $child->topics_count, 'posts' => $child->posts_count, 'last' => $childLast[$child->id] ?? null]])
          @endforeach
        </section>
      @endif

      <div class="f-tools">
        <form method="get">
          <label class="sr" for="f-type">{{ __('community.forum.prefix') }}</label>
          <select id="f-type" name="type" data-autosubmit>
            <option value="">{{ __('community.forum.prefix') }}: {{ __('community.forum.all_prefixes') }}</option>
            @foreach (array_keys(\App\Models\ForumTopic::TYPES) as $t)<option value="{{ $t }}" @selected($type === $t)>{{ __('community.types.'.$t) }}</option>@endforeach
          </select>
          <label class="sr" for="f-sort">{{ __('community.forum.sort') }}</label>
          <select id="f-sort" name="sort" data-autosubmit>
            @foreach (\App\Http\Controllers\CommunityController::SORTS as $s)<option value="{{ $s }}" @selected($sort === $s)>{{ __('community.forum.sort') }}: {{ __('community.forum.sorts.'.$s) }}</option>@endforeach
          </select>
          @if (count(\App\Support\Locales::all()) > 1)
            <label class="sr" for="f-lang">{{ __('community.sort.lang') }}</label>
            <select id="f-lang" name="lang" data-autosubmit>
              <option value="">{{ __('community.sort.all_langs') }}</option>
              @foreach (\App\Support\Locales::all() as $code => $name)<option value="{{ $code }}" @selected($lang === $code)>{{ $name }}</option>@endforeach
            </select>
          @endif
          <noscript><button class="f-btn sm">OK</button></noscript>
        </form>
        @include('community.partials.pager', ['p' => $topics])
      </div>

      @if ($pinned->isNotEmpty())
        <section class="f-blk">
          <div class="f-sticky">📌 {{ __('community.forum.pinned') }}</div>
          @foreach ($pinned as $topic)@include('community.partials.row', ['topic' => $topic, 'showCategory' => ! $category])@endforeach
        </section>
      @endif

      <section class="f-blk">
        <div class="f-bh"><h2>{{ __('community.forum.list') }}</h2><small>{{ trans_choice('community.forum.topics_total', $topics->total(), ['n' => number_format($topics->total(), 0, ',', ' ')]) }}</small></div>
        @forelse ($rest as $topic)
          @include('community.partials.row', ['topic' => $topic, 'showCategory' => ! $category])
          @if ($loop->iteration === 5 && $loop->remaining > 0)<div class="f-thad"><x-ad-banner placement="forum_list" gap="0" /></div>@endif
        @empty
          @if ($pinned->isEmpty())<p class="f-empty">{{ $tab === 'mine' ? __('community.empty_mine') : __('community.forum.empty_list') }}</p>@endif
        @endforelse
      </section>

      <div class="f-total"><span class="f-meta">{{ __('community.forum.page_of', ['n' => $topics->currentPage(), 'of' => max(1, $topics->lastPage())]) }}</span>@include('community.partials.pager', ['p' => $topics])</div>
    </div>

    @include('community.partials.side')
  </div>
</div>
@include('community.partials.mobilebar')
</x-layouts.home>
