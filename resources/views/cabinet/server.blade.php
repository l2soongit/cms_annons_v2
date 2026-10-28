<x-layouts.app :title="$server->name.' | '.__('cabinet.title')">
    <a href="{{ route('cabinet') }}" class="text-sm text-moss hover:text-bone">{{ __('cabinet.my_servers') }}</a>

    <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold tracking-tight">{{ $server->name }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-moss">
                <x-status-badge :status="$server->status" />
                <span>{{ $server->game->name }}</span>
                <span>{{ $server->address() }}</span>
            </div>
        </div>
        <div class="flex gap-2">
            @if ($server->status === \App\Enums\ServerStatus::Published)
                <x-button variant="secondary" :href="route('servers.show', $server)">{{ __('cabinet.view_public') }}</x-button>
            @endif
            <x-button :href="route('cabinet.servers.edit', $server)">{{ __('cabinet.edit') }}</x-button>
        </div>
    </div>

    <x-flash class="mt-6" />

    @if ($server->status === \App\Enums\ServerStatus::Rejected && $server->rejection_reason)
        <div class="mt-6 rounded-md border border-fade/40 bg-fade/10 px-4 py-3 text-sm" role="alert">
            <p class="font-medium">{{ __('cabinet.rejected_title') }}</p>
            <p class="mt-1">{{ $server->rejection_reason }}</p>
        </div>
    @elseif ($server->status === \App\Enums\ServerStatus::Pending && ! session('status'))
        <p class="mt-6 rounded-md border border-amber/40 bg-amber/10 px-4 py-3 text-sm">{{ __('cabinet.pending_notice') }}</p>
    @endif

    <div class="mt-10 grid gap-x-12 gap-y-12 lg:grid-cols-2">
        {{-- Статистика --}}
        <section aria-labelledby="s-stats" class="lg:col-span-2">
            <h2 id="s-stats" class="font-display text-lg font-medium">{{ __('cabinet.stats.title') }}</h2>
            <dl class="mt-4 grid grid-cols-2 gap-6 border-y border-seam py-5 sm:grid-cols-4">
                <div><dt class="text-sm text-moss">{{ __('cabinet.stats.rank') }}</dt><dd class="mt-1 font-display text-2xl">{{ $server->rank ? '#'.$server->rank : '—' }}</dd></div>
                <div><dt class="text-sm text-moss">{{ __('cabinet.stats.votes') }}</dt><dd class="mt-1 font-display text-2xl tabular-nums">{{ number_format($server->votes_month, 0, ',', ' ') }}</dd></div>
                <div><dt class="text-sm text-moss">{{ __('cabinet.stats.online') }}</dt><dd @class(['mt-1 font-display text-2xl tabular-nums', 'text-fade' => ! $server->is_online])>{{ $server->is_online ? $server->players.' / '.$server->max_players : __('ui.server.offline') }}</dd></div>
                <div><dt class="text-sm text-moss">{{ __('cabinet.stats.uptime') }}</dt><dd class="mt-1 font-display text-2xl tabular-nums">{{ $server->uptime }}%</dd></div>
            </dl>
            @if ($server->suspicious_online)
                <p class="mt-3 text-sm text-fade">{{ __('cabinet.stats.suspicious') }}</p>
            @endif
            <p class="mt-3 text-xs text-moss">{{ $server->checked_at ? __('cabinet.stats.checked', ['time' => $server->checked_at->diffForHumans()]) : __('cabinet.stats.not_checked') }}</p>

            @if ($stats->isNotEmpty())
                @php
                    $maxPlayers = max(1, $stats->max('players_avg'));
                @endphp
                <figure class="mt-6">
                    <figcaption class="text-sm text-moss">{{ __('cabinet.stats.chart') }}</figcaption>
                    <div class="mt-3 flex h-32 items-end gap-px" role="img" aria-label="{{ __('cabinet.stats.chart') }}">
                        @foreach ($stats as $point)
                            <div class="flex-1 rounded-t-sm bg-signal/70" style="height: {{ max(2, round($point->players_avg / $maxPlayers * 100)) }}%" title="{{ \Illuminate\Support\Carbon::parse($point->hour)->format('d.m H:00') }}: {{ $point->players_avg }}"></div>
                        @endforeach
                    </div>
                </figure>
            @endif
        </section>

        {{-- Просування: платні послуги з балансу --}}
        @php
            $balance = auth()->user()->balance;
            $published = $server->status === \App\Enums\ServerStatus::Published;
        @endphp
        <section aria-labelledby="s-promo" id="s-promo" class="lg:col-span-2">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="s-promo" class="font-display text-lg font-medium">{{ __('billing.promo.title') }}</h2>
                <p class="text-sm text-moss">{{ __('billing.promo.lead', ['balance' => \App\Billing\Money::format($balance)]) }}
                    <a href="{{ route('cabinet.balance') }}" class="ml-2 text-amber hover:underline">{{ __('billing.promo.top_up') }}</a></p>
            </div>

            @if ($activeOrders->isNotEmpty())
                <h3 class="mt-5 text-sm font-medium text-moss">{{ __('billing.promo.active') }}</h3>
                <ul class="mt-2 divide-y divide-seam border-y border-seam text-sm">
                    @foreach ($activeOrders as $order)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <span><b class="font-medium">{{ $order->tariff->name }}</b>
                                <span class="text-moss">{{ $order->starts_at->isFuture() ? $order->starts_at->format('d.m.Y').' – ' : '' }}{{ __('billing.promo.until', ['date' => $order->ends_at?->format('d.m.Y H:i') ?? '∞']) }}</span></span>
                            @unless (in_array($order->tariff->service, ['boost', 'coming_soon'], true))
                                <form method="POST" action="{{ route('cabinet.orders.auto-renew', $order) }}">
                                    @csrf
                                    <button class="inline-flex items-center gap-2 text-moss hover:text-bone" aria-pressed="{{ $order->auto_renew ? 'true' : 'false' }}">
                                        <span @class(['inline-block size-3 rounded-full border', 'border-signal bg-signal' => $order->auto_renew, 'border-moss' => ! $order->auto_renew])></span>{{ __('billing.promo.auto_renew') }}
                                    </button>
                                </form>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            @endif

            @if (! $published)
                <p class="mt-4 text-sm text-moss">{{ __('billing.promo.unpublished') }}</p>
            @elseif ($tariffs->isNotEmpty())
                <form method="POST" action="{{ route('cabinet.servers.orders', $server) }}" class="mt-6">
                    @csrf
                    <fieldset>
                        <legend class="text-sm font-medium">{{ __('billing.promo.buy') }}</legend>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($tariffs as $service => $list)
                                @php
                                    $blocked = $service === 'coming_soon' && ! $server->opens_at?->isFuture();
                                @endphp
                                <div class="rounded-md border border-seam p-4">
                                    <p class="font-medium">{{ __("billing.services.{$service}") }}</p>
                                    <p class="mt-1 text-xs text-moss">{{ __("billing.service_hints.{$service}") }}</p>
                                    @if ($service === 'vip')
                                        <p class="mt-1 text-xs text-moss">{{ __('billing.promo.vip_slots', ['taken' => $vipTaken, 'total' => $server->game->vip_slots]) }}</p>
                                    @endif
                                    @if ($blocked)
                                        <p class="mt-3 text-xs text-fade">{{ __('billing.promo.opening_needed') }}</p>
                                    @else
                                        <div class="mt-3 space-y-2">
                                            @foreach ($list as $tariff)
                                                <label class="flex items-center justify-between gap-2 rounded border border-seam px-2.5 py-2 text-sm has-checked:border-amber">
                                                    @php
                                                        $term = match ($service) {
                                                            'boost' => __('billing.promo.points', ['n' => $tariff->points]),
                                                            'coming_soon' => __('billing.promo.until_opening'),
                                                            default => __('billing.promo.days', ['n' => $tariff->days]),
                                                        };
                                                    @endphp
                                                    <span class="flex items-center gap-2"><input type="radio" name="tariff_id" value="{{ $tariff->id }}" @checked((int) old('tariff_id') === $tariff->id) required class="accent-amber">{{ $term }}</span>
                                                    <span class="tabular-nums">{{ \App\Billing\Money::format($tariff->price) }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                    @error('tariff_id', 'promo') <p class="mt-3 text-sm text-fade" role="alert">{{ $message }}</p> @enderror
                    <div class="mt-4 flex flex-wrap items-end gap-4">
                        <div>
                            <label for="promo_code" class="mb-1.5 block text-sm font-medium">{{ __('billing.promo.code') }} <span class="font-normal text-moss">({{ __('billing.promo.code_optional') }})</span></label>
                            <input id="promo_code" name="promo_code" value="{{ old('promo_code') }}" maxlength="32" class="w-44 rounded-md border border-seam bg-slab px-3 py-2.5 uppercase text-bone focus:border-amber focus:outline-none">
                        </div>
                        <label class="flex items-center gap-2 pb-3 text-sm"><input type="checkbox" name="auto_renew" value="1" @checked(old('auto_renew')) class="accent-amber">{{ __('billing.promo.renew_hint') }}</label>
                        <x-button class="ml-auto">{{ __('billing.promo.submit') }}</x-button>
                    </div>
                </form>
            @endif
        </section>

        {{-- Підтвердження власності --}}
        <section aria-labelledby="s-verify">
            <h2 id="s-verify" class="font-display text-lg font-medium">{{ __('cabinet.verify.title') }}</h2>
            @if ($server->verified_at)
                <p class="mt-3 text-signal">{{ __('cabinet.verify.done', ['date' => $server->verified_at->format('d.m.Y')]) }}</p>
            @else
                <p class="mt-3 text-moss">{{ __('cabinet.verify.lead') }}</p>
                <p class="mt-4 text-sm">{{ __('cabinet.verify.code') }}</p>
                <p class="mt-1 select-all rounded-md border border-seam bg-slab px-3 py-2 font-medium">{{ $verifyCode }}</p>
                <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm text-moss">
                    <li>{{ __('cabinet.verify.way_motd') }}</li>
                    @unless (filter_var($server->host, FILTER_VALIDATE_IP))
                        <li>{{ __('cabinet.verify.way_dns', ['host' => $server->host, 'code' => $verifyCode]) }}</li>
                    @endunless
                    <li>{{ __('cabinet.verify.way_file') }}</li>
                </ol>
                <form method="POST" action="{{ route('cabinet.servers.verify', $server) }}" class="mt-5">
                    @csrf
                    <x-button variant="secondary">{{ __('cabinet.verify.check') }}</x-button>
                </form>
            @endif
        </section>

        {{-- Vote-callback --}}
        <section aria-labelledby="s-callback">
            <h2 id="s-callback" class="font-display text-lg font-medium">{{ __('cabinet.callback.title') }}</h2>
            <p class="mt-3 text-moss">{{ __('cabinet.callback.lead') }}</p>
            <form method="POST" action="{{ route('cabinet.servers.callback', $server) }}" class="mt-4 space-y-4">
                @csrf
                <x-field name="callback_url" type="url" :label="__('cabinet.callback.url')" :value="$server->callback_url" placeholder="https://" />
                <div class="flex flex-wrap gap-3">
                    <x-button variant="secondary">{{ __('cabinet.callback.save') }}</x-button>
                </div>
            </form>
            @if ($server->callback_url)
                <form method="POST" action="{{ route('cabinet.servers.callback.test', $server) }}" class="mt-3">
                    @csrf
                    <x-button variant="ghost" class="px-0">{{ __('cabinet.callback.test') }}</x-button>
                </form>
            @endif
        </section>

        {{-- API-ключ --}}
        <section aria-labelledby="s-api">
            <h2 id="s-api" class="font-display text-lg font-medium">{{ __('cabinet.api.title') }}</h2>
            <p class="mt-3 text-moss">{{ __('cabinet.api.lead') }}</p>
            <p class="mt-4 text-sm">{{ __('cabinet.api.server_id') }}: <span class="font-medium">{{ $server->id }}</span></p>
            <details class="mt-3">
                <summary class="cursor-pointer text-sm text-moss hover:text-bone">{{ __('cabinet.api.show_key') }}</summary>
                <p class="mt-2 select-all break-all rounded-md border border-seam bg-slab px-3 py-2 text-sm">{{ $server->callback_secret }}</p>
            </details>
            <form method="POST" action="{{ route('cabinet.servers.key', $server) }}" class="mt-4" onsubmit="return confirm(@js(__('cabinet.api.confirm_regenerate')))">
                @csrf
                <x-button variant="danger">{{ __('cabinet.api.regenerate') }}</x-button>
            </form>
        </section>

        {{-- Співвласники --}}
        <section aria-labelledby="s-owners">
            <h2 id="s-owners" class="font-display text-lg font-medium">{{ __('owners.title') }}</h2>
            <p class="mt-3 text-moss">{{ __('owners.lead') }}</p>
            <ul class="mt-4 text-sm">
                <li class="flex justify-between gap-4 border-b border-seam py-2">
                    <span>{{ $server->owner->name }}</span><span class="text-moss">{{ __('owners.owner') }}</span>
                </li>
                @foreach ($server->coOwners as $co)
                    <li class="flex items-center justify-between gap-4 border-b border-seam py-2">
                        <span>{{ $co->name }}</span>
                        @if ($isMainOwner)
                            <form method="POST" action="{{ route('cabinet.servers.owners.remove', [$server, $co]) }}" onsubmit="return confirm(@js(__('owners.remove_confirm', ['name' => $co->name])))">
                                @csrf @method('DELETE')
                                <button class="text-moss hover:text-fade">{{ __('owners.remove') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
                @foreach ($invitations as $invitation)
                    <li class="flex items-center justify-between gap-4 border-b border-seam py-2">
                        <span class="min-w-0 truncate">{{ $invitation->email }} <span class="text-moss">— {{ __('owners.pending', ['date' => $invitation->expires_at->format('d.m')]) }}</span></span>
                        @if ($isMainOwner)
                            <form method="POST" action="{{ route('cabinet.servers.invitations.cancel', [$server, $invitation]) }}">
                                @csrf @method('DELETE')
                                <button class="text-moss hover:text-fade">{{ __('owners.cancel') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($isMainOwner)
                <form method="POST" action="{{ route('cabinet.servers.owners.invite', $server) }}" class="mt-4 flex items-end gap-2">
                    @csrf
                    <x-field name="email" type="email" class="flex-1" :label="__('owners.invite_email')" required />
                    <x-button variant="secondary">{{ __('owners.invite') }}</x-button>
                </form>

                @if ($server->coOwners->isNotEmpty())
                    <details class="mt-6">
                        <summary class="cursor-pointer text-sm text-moss hover:text-bone">{{ __('owners.transfer_title') }}</summary>
                        <form method="POST" action="{{ route('cabinet.servers.transfer', $server) }}" class="mt-3 space-y-3">
                            @csrf
                            <p class="text-sm text-moss">{{ __('owners.transfer_lead') }}</p>
                            <label class="block text-sm">
                                <span class="mb-1.5 block font-medium">{{ __('owners.transfer_to') }}</span>
                                <select name="user_id" required class="w-full rounded-md border border-seam bg-slab px-3 py-2.5 text-bone">
                                    @foreach ($server->coOwners as $co)
                                        <option value="{{ $co->id }}">{{ $co->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <x-field name="password" type="password" :label="__('owners.password')" autocomplete="current-password" required />
                            <x-button variant="danger">{{ __('owners.transfer') }}</x-button>
                        </form>
                    </details>
                @endif
            @endif
        </section>

        {{-- Останні голоси --}}
        <section aria-labelledby="s-votes">
            <h2 id="s-votes" class="font-display text-lg font-medium">{{ __('cabinet.votes.title') }}</h2>
            @if ($recentVotes->isEmpty())
                <p class="mt-3 text-moss">{{ __('cabinet.votes.empty') }}</p>
            @else
                <ul class="mt-3 text-sm">
                    @foreach ($recentVotes as $vote)
                        <li class="flex justify-between gap-4 border-b border-seam py-2">
                            <span>{{ $vote->nickname ?: __('cabinet.votes.anonymous') }}</span>
                            <span class="flex gap-3 text-moss">
                                <span>{{ __("cabinet.votes.callback_{$vote->callback_status}") }}</span>
                                <time datetime="{{ $vote->created_at->toIso8601String() }}">{{ $vote->created_at->format('d.m H:i') }}</time>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.app>
