@php
    use App\Billing\Money;
    use App\Models\Banner;
@endphp
<x-layouts.app :title="__('billing.ads.title').' | graj.club'">
    <x-cabinet-nav />
    <x-flash class="mb-6" />

    <h1 class="font-display text-3xl font-bold tracking-tight">{{ __('billing.ads.title') }}</h1>
    <p class="mt-3 max-w-3xl text-moss">{{ __('billing.ads.lead') }}</p>
    <p class="mt-2 text-sm text-moss">{{ __('billing.promo.lead', ['balance' => Money::format($user->balance)]) }}
        <a href="{{ route('cabinet.balance') }}" class="ml-2 text-amber hover:underline">{{ __('billing.promo.top_up') }}</a></p>

    <div class="mt-10 grid gap-x-12 gap-y-12 lg:grid-cols-[1fr_22rem]">
        {{-- Бронювання --}}
        <section aria-labelledby="ads-book">
            <h2 id="ads-book" class="font-display text-lg font-medium">{{ __('billing.ads.book') }}</h2>
            @if ($servers->isEmpty())
                <p class="mt-3 text-sm text-moss">{{ __('billing.ads.no_servers') }}</p>
            @elseif ($tariffs->isEmpty())
                <p class="mt-3 text-sm text-moss">{{ __('billing.deposit.unavailable') }}</p>
            @else
                <form method="POST" action="{{ route('cabinet.ads.store') }}" enctype="multipart/form-data" class="mt-5 space-y-6">
                    @csrf
                    <div>
                        <label for="ad-server" class="mb-1.5 block text-sm font-medium">{{ __('billing.ads.server') }}</label>
                        <select id="ad-server" name="server_id" required class="w-full rounded-md border border-seam bg-slab px-3 py-2.5 text-bone focus:border-amber focus:outline-none">
                            @foreach ($servers as $server)
                                <option value="{{ $server->id }}" @selected((int) old('server_id') === $server->id)>{{ $server->name }} · {{ $server->game->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <fieldset>
                        <legend class="mb-1.5 text-sm font-medium">{{ __('billing.ads.tariff') }}</legend>
                        <div class="space-y-4">
                            @foreach ($tariffs->groupBy(fn ($t) => $t->placement.'-'.(Banner::isPerGame($t->placement) ? $t->game_id : '')) as $key => $group)
                                @php
                                    $first = $group->first();
                                    [, $w, $h] = Banner::PLACEMENTS[$first->placement];
                                    $busyDays = $busy[$first->placement.'-'.(Banner::isPerGame($first->placement) ? $first->game_id : '')] ?? [];
                                @endphp
                                <div class="flex flex-col-reverse gap-4 rounded-md border border-seam p-4 sm:flex-row sm:items-start">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium">{{ __("billing.ads.placements.{$first->placement}") }}@if (Banner::isPerGame($first->placement) && $first->game) · {{ $first->game->name }}@endif</p>
                                    <p class="mt-1 text-xs text-moss">{{ __('billing.ads.size', ['w' => $w, 'h' => $h]) }}</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($group as $tariff)
                                            <label class="flex items-center gap-2 rounded border border-seam px-2.5 py-2 text-sm has-checked:border-amber">
                                                <input type="radio" name="tariff_id" value="{{ $tariff->id }}" @checked((int) old('tariff_id') === $tariff->id) required class="accent-amber">
                                                {{ __('billing.promo.days', ['n' => $tariff->days]) }} · <span class="tabular-nums">{{ Money::format($tariff->price) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <p class="mt-3 text-xs text-moss">{{ __('billing.ads.busy') }}:
                                        @if ($busyDays)
                                            {{ collect($busyDays)->sort()->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d.m'))->implode(', ') }}
                                        @else
                                            {{ __('billing.ads.busy_none') }}
                                        @endif
                                    </p>
                                </div>
                                <x-ad-placement-preview :placement="$first->placement" :game="$first->game" class="w-full sm:w-72 lg:w-80" />
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-3 text-xs text-moss">{{ __('billing.ads.clash_hint') }}</p>
                        @error('tariff_id') <p class="mt-3 text-sm text-fade" role="alert">{{ $message }}</p> @enderror
                    </fieldset>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field name="starts_at" :label="__('billing.ads.start')" type="date" :value="old('starts_at', today()->toDateString())"
                                 :min="today()->toDateString()" :max="today()->addDays(config('graj.billing.banner_booking_days'))->toDateString()" required />
                        <x-field name="promo_code" :label="__('billing.promo.code').' ('.__('billing.promo.code_optional').')'" maxlength="32" />
                    </div>

                    <div>
                        <label for="ad-image" class="mb-1.5 block text-sm font-medium">{{ __('billing.ads.image') }}</label>
                        <input id="ad-image" name="image" type="file" accept="image/webp,image/png,image/jpeg,image/gif" required class="block w-full text-sm text-moss file:mr-3 file:rounded-md file:border-0 file:bg-slab-2 file:px-3 file:py-2 file:text-bone">
                        @error('image') <p class="mt-1.5 text-sm text-fade">{{ $message }}</p> @enderror
                    </div>

                    <fieldset>
                        <legend class="mb-1.5 text-sm font-medium">{{ __('billing.ads.link') }}</legend>
                        <div class="flex flex-wrap gap-4 text-sm">
                            <label class="flex items-center gap-2"><input type="radio" name="link" value="server" @checked(old('link', 'server') === 'server') class="accent-amber">{{ __('billing.ads.link_server') }}</label>
                            <label class="flex items-center gap-2"><input type="radio" name="link" value="site" @checked(old('link') === 'site') class="accent-amber">{{ __('billing.ads.link_site') }}</label>
                        </div>
                    </fieldset>

                    <x-field name="alt" :label="__('billing.ads.alt')" maxlength="120" />

                    <x-button>{{ __('billing.ads.submit') }}</x-button>
                </form>
            @endif
        </section>

        {{-- Мої банери --}}
        <section aria-labelledby="ads-mine">
            <h2 id="ads-mine" class="font-display text-lg font-medium">{{ __('billing.ads.mine') }}</h2>
            @forelse ($banners as $banner)
                <article class="mt-4 rounded-md border border-seam p-3">
                    <img src="{{ \App\Support\HomePageData::url($banner->image) }}" alt="" class="w-full rounded" loading="lazy">
                    <p class="mt-2 text-sm font-medium">{{ __("billing.ads.placements.{$banner->placement}") }}@if ($banner->game) · {{ $banner->game->name }}@endif</p>
                    <p class="text-xs text-moss tabular-nums">{{ $banner->starts_at->format('d.m.Y') }} – {{ $banner->ends_at->copy()->subSecond()->format('d.m.Y') }} · {{ $banner->server?->name }}</p>
                    <p class="mt-1 text-xs text-moss">{{ __('billing.ads.stats', ['views' => number_format($banner->impressions, 0, ',', ' '), 'clicks' => $banner->clicks, 'ctr' => $banner->ctr()]) }}</p>
                    @unless ($banner->is_active)<p class="mt-1 text-xs text-fade">{{ __('billing.ads.hidden') }}</p>@endunless
                </article>
            @empty
                <p class="mt-3 text-sm text-moss">{{ __('billing.ads.mine_empty') }}</p>
            @endforelse
        </section>
    </div>
</x-layouts.app>
