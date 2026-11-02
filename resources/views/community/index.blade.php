@php
    $pageTitle = $category ? $category->name.' — '.__('community.nav') : ($tab === 'news' ? __('community.tabs.news') : __('community.meta_title'));
@endphp
<x-layouts.home :title="$pageTitle.' | graj.club'" :description="$category?->description ?: __('community.lead')" :assets="['resources/css/home.css', 'resources/css/community.css', 'resources/js/community.js']">
<div class="wrap cm">
  @include('community.partials.top')

  @if (session('status'))<div class="cm-flash" role="status">{{ session('status') }}</div>@endif

  <div class="cm-layout">
    @include('community.partials.nav')

    <section aria-label="{{ __('community.tabs.feed') }}">
      @if ($category)
        <div class="cm-cathead">
          <div><h2 class="cm-h2">{{ $category->name }}</h2>@if ($category->description)<p class="cm-meta">{{ $category->description }}</p>@endif</div>
          @auth<button type="button" class="btn btn-ghost btn-sm" data-subscribe="category" data-id="{{ $category->id }}" aria-pressed="{{ $subscribed ? 'true' : 'false' }}"
            data-on="{{ __('community.subscribe.following') }}" data-off="{{ __('community.subscribe.follow') }}">{{ $subscribed ? __('community.subscribe.following') : __('community.subscribe.follow') }}</button>@endauth
        </div>
      @endif

      @if ($canPost)
        <div class="cm-card cm-compose">
          @include('community.partials.ava', ['user' => auth()->user()])
          <a class="fake" href="{{ route('community.create', array_filter(['category' => $category?->id])) }}">{{ __('community.compose.placeholder') }}</a>
          <a class="btn btn-hot btn-sm" href="{{ route('community.create', array_filter(['category' => $category?->id])) }}">{{ __('community.compose.button') }}</a>
        </div>
      @elseif (! auth()->check())
        <div class="cm-card cm-compose"><span class="fake">{{ __('community.compose.guest') }}</span><a class="btn btn-hot btn-sm" href="{{ route('login') }}">{{ __('ui.nav.login') }}</a></div>
      @endif

      <form class="cm-sort" method="get">
        @foreach (\App\Http\Controllers\CommunityController::SORTS as $s)
          <a href="{{ request()->fullUrlWithQuery(['sort' => $s, 'page' => null]) }}" @if ($sort === $s) aria-current="page" @endif>{{ __('community.sort.'.$s) }}</a>
        @endforeach
        <span class="sep"></span>
        <label class="sr" for="cm-type">{{ __('community.sort.type') }}</label>
        <select id="cm-type" name="type" data-autosubmit>
          <option value="">{{ __('community.sort.all_types') }}</option>
          @foreach (array_keys(\App\Models\ForumTopic::TYPES) as $t)<option value="{{ $t }}" @selected($type === $t)>{{ __('community.types.'.$t) }}</option>@endforeach
        </select>
        @if (count(\App\Support\Locales::all()) > 1)
          <label class="sr" for="cm-lang">{{ __('community.sort.lang') }}</label>
          <select id="cm-lang" name="lang" data-autosubmit>
            <option value="">{{ __('community.sort.all_langs') }}</option>
            @foreach (\App\Support\Locales::all() as $code => $name)<option value="{{ $code }}" @selected($lang === $code)>{{ $name }}</option>@endforeach
          </select>
        @endif
        <input type="hidden" name="sort" value="{{ $sort }}">
        <noscript><button class="btn btn-ghost btn-sm">OK</button></noscript>
      </form>

      @forelse ($topics as $topic)
        @include('community.partials.post-card', ['topic' => $topic])
      @empty
        <div class="cm-card cm-empty"><p>{{ $tab === 'mine' ? __('community.empty_mine') : __('community.empty') }}</p></div>
      @endforelse

      @if ($topics->hasPages())<div class="cm-pages">{{ $topics->links() }}</div>@endif
    </section>

    @include('community.partials.aside')
  </div>
</div>
</x-layouts.home>
