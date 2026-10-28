<x-layouts.cabinet :server="$server" :title="$server->name.' | '.__('cabinet.title')">
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

    <div class="mt-6 grid gap-4 lg:grid-cols-2 lg:items-start">
        {{-- Статистика --}}
        <section aria-labelledby="s-stats" class="c-card lg:col-span-2">
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
        @php
            // Відрив до місця вище — щоб підказати пакет «Підняття», який його закриває
            $above = $published && $server->rank > 1
                ? \App\Models\Server::where('game_id', $server->game_id)->published()->where('rank', $server->rank - 1)->first(['score'])
                : null;
            $gapUp = $above ? (int) max(1, ceil($above->score - $server->score) + 1) : null;
            $offerStyle = [
                'boost' => ['▲', '#34d3991a', '#34d399'],
                'vip' => ['★', '#fbbf241a', '#fbbf24'],
                'highlight' => ['◆', '#60a5fa1a', '#60a5fa'],
                'coming_soon' => ['⏱', '#f973161a', '#f97316'],
            ];
        @endphp
        <section aria-labelledby="s-promo" id="s-promo" class="lg:col-span-2" style="scroll-margin-top:16px">
            <div class="c-card">
                <h2 id="s-promo">{{ __('billing.promo.title') }}
                    <span class="c-more">{{ __('billing.promo.lead', ['balance' => \App\Billing\Money::format($balance)]) }} <a href="{{ route('cabinet.balance') }}" style="color:var(--c-amber)">{{ __('billing.promo.top_up') }}</a></span>
                </h2>

                @if ($activeOrders->isNotEmpty())
                    <div class="c-svc">
                        @foreach ($activeOrders as $order)
                            @php
                                [$glyph, $bg, $fg] = $offerStyle[$order->tariff->service] ?? ['▣', '#fbbf241a', '#fbbf24'];
                                $total = $order->starts_at && $order->ends_at ? max(1, $order->starts_at->diffInSeconds($order->ends_at)) : null;
                                $left = $total ? max(0, min(100, round(now()->diffInSeconds($order->ends_at, false) / $total * 100))) : 100;
                                $ending = $order->ends_at && $order->ends_at->lt(now()->addDays(3));
                            @endphp
                            <div @class(['c-svc-row', 'is-warn' => $ending])>
                                <span class="c-ic" style="background:{{ $bg }};color:{{ $fg }}" aria-hidden="true">{{ $glyph }}</span>
                                <div>
                                    <b>{{ $order->tariff->name }}</b>
                                    <small>{{ $order->starts_at->isFuture() ? $order->starts_at->format('d.m.Y').' – ' : '' }}{{ __('billing.promo.until', ['date' => $order->ends_at?->format('d.m.Y H:i') ?? '∞']) }}</small>
                                    @if ($order->ends_at)<div class="c-left"><i style="width:{{ $order->starts_at->isFuture() ? 100 : $left }}%"></i></div>@endif
                                </div>
                                @unless (in_array($order->tariff->service, ['boost', 'coming_soon'], true))
                                    <form method="POST" action="{{ route('cabinet.orders.auto-renew', $order) }}" class="c-ar">
                                        @csrf
                                        <span class="c-hide-sm">{{ __('billing.promo.auto_renew') }}</span>
                                        <button class="c-switch" role="switch" aria-checked="{{ $order->auto_renew ? 'true' : 'false' }}" aria-pressed="{{ $order->auto_renew ? 'true' : 'false' }}" aria-label="{{ __('billing.promo.auto_renew') }}"></button>
                                    </form>
                                @endunless
                            </div>
                        @endforeach
                    </div>
                @endif
                @if (! $published)
                    <p class="c-muted" style="margin-top:12px;font-size:14px">{{ __('billing.promo.unpublished') }}</p>
                @endif
                @error('tariff_id', 'promo')<div class="c-warn" role="alert">{{ $message }}</div>@enderror
            </div>

            @if ($published && $tariffs->isNotEmpty())
                <div class="c-offers" style="margin-top:16px">
                    @foreach ($tariffs as $service => $list)
                        @php
                            [$glyph, $bg, $fg] = $offerStyle[$service] ?? ['▣', '#fbbf241a', '#fbbf24'];
                            $blocked = $service === 'coming_soon' && ! $server->opens_at?->isFuture();
                            $closes = $service === 'boost' && $gapUp ? $list->first(fn ($t) => $t->points >= $gapUp) : null;
                            $pick = $list->firstWhere('id', (int) old('tariff_id')) ?? $closes ?? $list->first();
                        @endphp
                        <form method="POST" action="{{ route('cabinet.servers.orders', $server) }}" class="c-card c-offer" data-offer>
                            @csrf
                            @if ($closes)<span class="c-tag">{{ __('billing.promo.closes_gap', ['place' => $server->rank - 1]) }}</span>@endif
                            <span class="c-ic" style="background:{{ $bg }};color:{{ $fg }}" aria-hidden="true">{{ $glyph }}</span>
                            <h3>{{ __("billing.services.{$service}") }}</h3>
                            <p>{{ __("billing.service_hints.{$service}") }}@if ($service === 'vip') {{ __('billing.promo.vip_slots', ['taken' => $vipTaken, 'total' => $server->game->vip_slots]) }}@endif</p>
                            @if ($blocked)
                                <p class="c-down" style="flex:0">{{ __('billing.promo.opening_needed') }}</p>
                                <a class="c-btn" href="{{ route('cabinet.servers.edit', $server) }}">{{ __('cabinet.edit') }}</a>
                            @else
                                @if ($list->count() > 1)
                                    <div class="c-days" role="radiogroup" aria-label="{{ __("billing.services.{$service}") }}" style="margin-top:0">
                                        @foreach ($list as $tariff)
                                            @php
                                                $term = match ($service) {
                                                    'boost' => '+'.$tariff->points,
                                                    'coming_soon' => __('billing.promo.until_opening'),
                                                    default => __('billing.promo.days', ['n' => $tariff->days]),
                                                };
                                            @endphp
                                            <button type="button" role="radio" aria-checked="{{ $tariff->is($pick) ? 'true' : 'false' }}" aria-pressed="{{ $tariff->is($pick) ? 'true' : 'false' }}"
                                                    data-tariff="{{ $tariff->id }}" data-price="{{ \App\Billing\Money::format($tariff->price) }}">{{ $term }}</button>
                                        @endforeach
                                    </div>
                                @elseif ($service === 'coming_soon')
                                    <p class="c-faint" style="flex:0;font-size:12px">{{ __('billing.promo.until_opening') }} · {{ $server->opens_at->format('d.m') }}</p>
                                @endif
                                <div class="c-price c-num" data-price-out>{{ \App\Billing\Money::format($pick->price) }}</div>
                                <details class="c-extra">
                                    <summary>{{ __('billing.promo.more_options') }}</summary>
                                    <div class="c-inp" style="margin-top:8px"><input name="promo_code" maxlength="32" placeholder="{{ __('billing.promo.code') }}" aria-label="{{ __('billing.promo.code') }}" value="{{ old('promo_code') }}" style="text-transform:uppercase"></div>
                                    @unless (in_array($service, ['boost', 'coming_soon'], true))
                                        <label class="c-check"><input type="checkbox" name="auto_renew" value="1" @checked(old('auto_renew'))>{{ __('billing.promo.renew_hint') }}</label>
                                    @endunless
                                </details>
                                <button class="c-btn is-go" name="tariff_id" value="{{ $pick->id }}" data-buy>{{ __('billing.promo.buy_now') }}</button>
                            @endif
                        </form>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Віджети для сайту сервера (ТЗ 7.3) --}}
        @if ($server->status === \App\Enums\ServerStatus::Published)
            @php
                $voteUrl = route('servers.vote', $server);
                $voteImg = route('widgets.vote', ['server' => $server->id, 'size' => '120x40']);
                $statusImg = route('widgets.status', ['server' => $server->id, 'size' => '468x60']);
                $pageUrl = route('servers.show', $server);
            @endphp
            <section aria-labelledby="s-widgets" class="c-card lg:col-span-2">
                <h2 id="s-widgets" class="font-display text-lg font-medium">{{ __('cabinet.widgets.title') }}</h2>
                <p class="mt-3 text-moss">{{ __('cabinet.widgets.lead') }} <a href="{{ route('developers') }}#widgets" class="text-amber hover:underline">{{ __('cabinet.widgets.more') }}</a></p>
                <div class="mt-4 flex flex-wrap items-center gap-4">
                    <img src="{{ $voteImg }}" alt="{{ __('widgets.vote') }}" width="120" height="40">
                    <img src="{{ $statusImg }}" alt="{{ $server->name }}" width="468" height="60" class="max-w-full">
                </div>
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div>
                        <label for="w-html" class="text-sm font-medium">HTML</label>
                        <textarea id="w-html" readonly rows="4" onclick="this.select()" class="mt-1.5 w-full rounded-md border border-seam bg-slab px-3 py-2 font-mono text-xs text-bone">&lt;a href="{{ $voteUrl }}"&gt;&lt;img src="{{ $voteImg }}" alt="{{ __('widgets.vote') }}" width="120" height="40"&gt;&lt;/a&gt;
&lt;a href="{{ $pageUrl }}"&gt;&lt;img src="{{ $statusImg }}" alt="{{ $server->name }}" width="468" height="60"&gt;&lt;/a&gt;</textarea>
                    </div>
                    <div>
                        <label for="w-bb" class="text-sm font-medium">BBCode</label>
                        <textarea id="w-bb" readonly rows="4" onclick="this.select()" class="mt-1.5 w-full rounded-md border border-seam bg-slab px-3 py-2 font-mono text-xs text-bone">[url={{ $voteUrl }}][img]{{ $voteImg }}[/img][/url]
[url={{ $pageUrl }}][img]{{ $statusImg }}[/img][/url]</textarea>
                    </div>
                </div>
                <p class="mt-2 text-xs text-moss">{{ __('cabinet.widgets.sizes', ['vote' => implode(', ', array_keys(\App\Http\Controllers\WidgetController::VOTE_SIZES)), 'status' => implode(', ', array_keys(\App\Http\Controllers\WidgetController::STATUS_SIZES))]) }}</p>
            </section>
        @endif

        {{-- Підтвердження власності --}}
        <section aria-labelledby="s-verify" class="c-card">
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
        <section aria-labelledby="s-callback" class="c-card">
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
        <section aria-labelledby="s-api" class="c-card">
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
        <section aria-labelledby="s-owners" class="c-card">
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
        <section aria-labelledby="s-votes" class="c-card">
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

    <x-slot:scripts>
        <script>
            // Просування: перемикач терміну міняє ціну й тариф, який купує кнопка картки
            document.querySelectorAll('[data-offer]').forEach((card) => {
                card.querySelectorAll('[data-tariff]').forEach((chip) => chip.addEventListener('click', () => {
                    card.querySelectorAll('[data-tariff]').forEach((c) => { c.setAttribute('aria-checked', String(c === chip)); c.setAttribute('aria-pressed', String(c === chip)); });
                    card.querySelector('[data-buy]').value = chip.dataset.tariff;
                    card.querySelector('[data-price-out]').textContent = chip.dataset.price;
                }));
            });
        </script>
    </x-slot:scripts>
</x-layouts.cabinet>
