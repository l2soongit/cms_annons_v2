@php
    $logo = $server->media->firstWhere('type', 'logo');
    $screens = $server->media->where('type', 'screenshot');
    $tags = $server->fieldValues->map(fn ($v) => collect($v->field?->options)->firstWhere('value', $v->value)['label'] ?? $v->value);
    $canManage = auth()->user()?->canManage($server);
@endphp

<x-layouts.app :title="__('ui.server_page.meta_title', ['server' => $server->name, 'game' => $server->game->name])"
               :description="\Illuminate\Support\Str::limit(\App\Support\HtmlSanitizer::text($server->description), 155)">
    <nav class="text-sm text-moss" aria-label="{{ __('ui.breadcrumbs') }}">
        <a href="{{ route('games.show', $server->game) }}" class="hover:text-bone">{{ $server->game->name }}</a>
        <span aria-hidden="true">/</span>
        <span class="text-bone">{{ $server->name }}</span>
    </nav>

    @if (session('voted'))
        <p class="mt-6 rounded-md border border-signal/40 bg-signal/10 px-4 py-3 text-sm" role="status">{{ __('vote.thanks') }}</p>
    @endif
    <x-flash class="mt-6" />

    <div class="mt-6 grid gap-10 lg:grid-cols-[1fr_20rem]">
        <div class="min-w-0">
            <div class="flex items-start gap-5">
                @if ($logo)
                    <img src="{{ Storage::disk('public')->url($logo->path) }}" alt="" class="size-16 shrink-0 rounded-md object-cover" width="64" height="64">
                @endif
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        <span @class(['rank-numeral text-4xl', 'text-amber' => ($server->rank ?? 99) <= 3, 'text-moss' => ($server->rank ?? 99) > 3])>{{ $server->rank ?? '—' }}</span>
                        <h1 class="font-display text-3xl font-bold tracking-tight">{{ $server->name }}</h1>
                    </div>
                    <p class="mt-2 flex flex-wrap gap-2 text-sm">
                        @foreach ($tags as $tag)
                            <span class="rounded border border-seam px-2 py-0.5 text-moss">{{ $tag }}</span>
                        @endforeach
                        @if ($server->verified_at)
                            <span class="rounded border border-signal/40 px-2 py-0.5 text-signal">{{ __('ui.server.verified') }}</span>
                        @endif
                    </p>
                </div>
            </div>

            {{-- Телефон: голос, статус і адреса одразу під назвою (бічна колонка опиняється в самому низу) --}}
            <div class="mt-5 grid gap-3 lg:hidden">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p @class(['text-sm font-medium', 'text-signal' => $server->is_online, 'text-fade' => ! $server->is_online])>
                        {{ $server->is_online ? __('ui.server.players', ['n' => $server->players, 'max' => $server->max_players]) : __('ui.server.offline') }}
                    </p>
                    <x-button :href="route('servers.vote', $server)">{{ __('ui.server.vote') }}</x-button>
                </div>
                <div class="flex items-center gap-2 rounded-md border border-seam bg-slab px-3 py-2 text-sm">
                    <span class="min-w-0 flex-1 break-all" data-copy-source>{{ $server->address() }}</span>
                    <button type="button" data-copy class="shrink-0 rounded border border-seam px-2 py-0.5 text-xs text-moss hover:text-bone" data-copied-label="{{ __('ui.server_page.copied') }}">{{ __('ui.server_page.copy') }}</button>
                </div>
            </div>

            {{-- Опис очищено HTMLPurifier при збереженні --}}
            <div class="prose-description mt-8 max-w-prose leading-relaxed">{!! \App\Support\HtmlSanitizer::render($server->description) !!}</div>

            <section class="mt-10" aria-label="{{ __('ui.server_page.chart') }}">
                <nav class="flex gap-1 text-sm" aria-label="{{ __('ui.server_page.period') }}">
                    @foreach (\App\Servers\ServerHistory::PERIODS as $key => $days)
                        <a href="{{ request()->fullUrlWithQuery(['period' => $key]) }}#history" @if ($period === $key) aria-current="true" @endif
                           @class(['rounded-md px-3 py-1.5', 'bg-slab-2 text-bone' => $period === $key, 'text-moss hover:text-bone' => $period !== $key])>{{ __("ui.server_page.period_{$key}") }}</a>
                    @endforeach
                </nav>
                @if ($history->isNotEmpty())
                    <div id="history" class="mt-5 grid gap-8 sm:grid-cols-2">
                        <x-bar-chart :points="$history" key="players" :label="__('ui.server_page.chart_online')" />
                        <x-bar-chart :points="$history" key="votes" :label="__('ui.server_page.chart_votes')" tone="amber" />
                    </div>
                @endif
            </section>

            @if ($screens->isNotEmpty())
                <div class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($screens as $shot)
                        <a href="{{ Storage::disk('public')->url($shot->path) }}" target="_blank" rel="noopener">
                            <img src="{{ Storage::disk('public')->url($shot->path) }}" alt="{{ __('ui.server_page.screenshot', ['n' => $loop->iteration]) }}" loading="lazy" class="aspect-video w-full rounded-md object-cover">
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Відгуки --}}
            <section id="reviews" class="mt-14" aria-labelledby="reviews-title">
                <div class="flex items-baseline justify-between gap-4 border-b border-bone/80 pb-2">
                    <h2 id="reviews-title" class="font-display text-lg font-medium">{{ __('reviews.title') }}</h2>
                    @if ($rating->total)
                        <p class="text-sm text-moss">{{ __('reviews.summary', ['avg' => number_format($rating->avg, 1, ',', ''), 'n' => $rating->total]) }}</p>
                    @endif
                </div>

                @auth
                    @unless ($canManage)
                        <form method="POST" action="{{ route('reviews.store', $server) }}" class="mt-6 space-y-4">
                            @csrf
                            <fieldset>
                                <legend class="mb-2 text-sm font-medium">{{ __('reviews.your_rating') }}</legend>
                                <div class="flex gap-1">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <label class="cursor-pointer">
                                            <input type="radio" name="rating" value="{{ $i }}" class="peer sr-only" @checked((int) old('rating', $myReview?->rating) === $i) required>
                                            <span class="block rounded-md border border-seam px-3 py-1.5 text-sm peer-checked:border-amber peer-checked:bg-amber/10 peer-focus-visible:outline-2 peer-focus-visible:outline-amber">{{ $i }}</span>
                                        </label>
                                    @endfor
                                </div>
                                @error('rating') <p class="mt-1.5 text-sm text-fade">{{ $message }}</p> @enderror
                            </fieldset>
                            <x-field name="body" type="textarea" rows="4" :label="__('reviews.body')" :value="$myReview?->body" />
                            <x-button variant="secondary">{{ $myReview ? __('reviews.update') : __('reviews.submit') }}</x-button>
                            @if ($myReview?->status === 'pending')
                                <p class="text-sm text-moss">{{ __('reviews.pending') }}</p>
                            @endif
                        </form>
                    @endunless
                @else
                    <p class="mt-6 text-sm text-moss"><a href="{{ route('login') }}" class="text-amber hover:underline">{{ __('ui.nav.login') }}</a>, {{ __('reviews.login_to_write') }}</p>
                @endauth

                <ul class="mt-6">
                    @forelse ($reviews as $review)
                        <li class="border-b border-seam py-5">
                            <div class="flex items-baseline justify-between gap-4">
                                <p class="font-medium">{{ $review->user->name }} <span class="ml-2 text-amber" aria-label="{{ __('reviews.stars', ['n' => $review->rating]) }}">{{ str_repeat('★', $review->rating) }}<span class="text-seam">{{ str_repeat('★', 5 - $review->rating) }}</span></span></p>
                                <time class="text-sm text-moss" datetime="{{ $review->created_at->toDateString() }}">{{ $review->created_at->translatedFormat('j M Y') }}</time>
                            </div>
                            <p class="mt-2 whitespace-pre-line">{{ $review->body }}</p>

                            @if ($review->owner_reply)
                                <div class="mt-3 border-l-2 border-amber/60 pl-4 text-sm">
                                    <p class="text-moss">{{ __('reviews.owner_reply') }}</p>
                                    <p class="mt-1 whitespace-pre-line">{{ $review->owner_reply }}</p>
                                </div>
                            @endif

                            <div class="mt-3 flex flex-wrap gap-4 text-sm">
                                @if ($canManage)
                                    <details>
                                        <summary class="cursor-pointer text-moss hover:text-bone">{{ $review->owner_reply ? __('reviews.edit_reply') : __('reviews.reply') }}</summary>
                                        <form method="POST" action="{{ route('reviews.reply', $review) }}" class="mt-3 space-y-3">
                                            @csrf
                                            <textarea name="owner_reply" rows="3" class="w-full rounded-md border border-seam bg-slab px-3 py-2 text-bone" aria-label="{{ __('reviews.reply') }}">{{ $review->owner_reply }}</textarea>
                                            <x-button variant="secondary">{{ __('reviews.save_reply') }}</x-button>
                                        </form>
                                    </details>
                                @endif
                                @auth
                                    <details>
                                        <summary class="cursor-pointer text-moss hover:text-bone">{{ __('reviews.report') }}</summary>
                                        <x-report-form :action="route('reviews.report', $review)" />
                                    </details>
                                @endauth
                            </div>
                        </li>
                    @empty
                        <li class="py-6 text-moss">{{ __('reviews.empty') }}</li>
                    @endforelse
                </ul>
                <div class="mt-4">{{ $reviews->fragment('reviews')->links() }}</div>
            </section>
        </div>

        <aside class="space-y-6 lg:sticky lg:top-6 lg:self-start lg:border-l lg:border-seam lg:pl-8">
            <x-ad-banner placement="sidebar" />
            <div>
                <p class="text-sm text-moss">{{ __('ui.server.address') }}</p>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <p class="min-w-0 break-all font-medium" data-copy-source>{{ $server->address() }}</p>
                    <button type="button" data-copy class="rounded border border-seam px-2 py-0.5 text-xs text-moss hover:text-bone" data-copied-label="{{ __('ui.server_page.copied') }}">{{ __('ui.server_page.copy') }}</button>
                </div>
            </div>
            <div>
                <p class="text-sm text-moss">{{ __('ui.server.status') }}</p>
                <p @class(['mt-1 font-medium', 'text-signal' => $server->is_online, 'text-fade' => ! $server->is_online])>
                    {{ $server->is_online ? __('ui.server.players', ['n' => $server->players, 'max' => $server->max_players]) : __('ui.server.offline') }}
                </p>
            </div>
            <div>
                <p class="text-sm text-moss">{{ __('ui.server.uptime') }}</p>
                <p class="mt-1 font-medium">{{ $server->uptime }}%</p>
            </div>
            @if ($server->languages || $server->country)
                <div>
                    <p class="text-sm text-moss">{{ __('ui.server_page.languages') }}</p>
                    <p class="mt-1 font-medium">
                        {{ collect($server->languages)->map(fn ($code) => \App\Livewire\Cabinet\ServerForm::LANGUAGES[$code] ?? $code)->implode(', ') ?: '—' }}
                    </p>
                    @if ($server->country)
                        <p class="mt-1 text-sm text-moss">{{ __('ui.server_page.hosted_in', ['country' => \App\Livewire\Cabinet\ServerForm::COUNTRIES[$server->country] ?? $server->country]) }}</p>
                    @endif
                </div>
            @endif
            <div id="vote">
                <p class="font-display text-2xl font-medium tabular-nums">{{ number_format($server->votes_month, 0, ',', ' ') }}</p>
                <p class="text-sm text-moss">{{ trans_choice('ui.server.votes', $server->votes_month) }} {{ __('ui.server_page.this_month') }}</p>
                <x-button class="mt-3 w-full py-3" :href="route('servers.vote', $server)">{{ __('ui.server.vote') }}</x-button>
            </div>

            @if ($server->website || $server->discord)
                <div class="space-y-1 text-sm">
                    @if ($server->website)<a href="{{ $server->website }}" rel="nofollow noopener" target="_blank" class="block text-amber hover:underline">{{ __('ui.server_page.website') }}</a>@endif
                    @if ($server->discord)<a href="{{ $server->discord }}" rel="nofollow noopener" target="_blank" class="block text-amber hover:underline">Discord</a>@endif
                </div>
            @endif

            {{-- Приватне повідомлення власнику (ТЗ 3.8) --}}
            @auth
                @if ($server->user_id !== auth()->id())
                    <details class="text-sm" @if ($errors->message->any()) open @endif>
                        <summary class="cursor-pointer font-medium text-amber hover:underline">{{ __('messages.write_owner') }}</summary>
                        <p class="mt-2 text-xs text-moss">{{ __('messages.write_lead') }}</p>
                        <form method="POST" action="{{ route('servers.message', $server) }}" class="mt-3 space-y-3">
                            @csrf
                            <textarea name="body" rows="3" maxlength="2000" required aria-label="{{ __('messages.placeholder') }}" placeholder="{{ __('messages.placeholder') }}"
                                      class="w-full rounded-md border border-seam bg-slab px-3 py-2 text-bone">{{ old('body') }}</textarea>
                            @error('body', 'message') <p class="text-sm text-fade" role="alert">{{ $message }}</p> @enderror
                            <x-button variant="secondary">{{ __('messages.send') }}</x-button>
                        </form>
                    </details>
                @endif
            @endauth

            @auth
                <details class="text-sm">
                    <summary class="cursor-pointer text-moss hover:text-bone">{{ __('ui.server_page.report') }}</summary>
                    <x-report-form :action="route('servers.report', $server)" />
                </details>
            @endauth
        </aside>
    </div>
</x-layouts.app>
