<x-layouts.app :title="__('profile.nav.notifications').' | graj.club'">
    <x-cabinet-nav />
    <x-flash class="mb-6" />

    <div class="grid max-w-4xl gap-x-12 gap-y-12 lg:grid-cols-[1fr_18rem]">
        <section aria-labelledby="n-prefs">
            <h2 id="n-prefs" class="font-display text-lg font-medium">{{ __('profile.notifications.title') }}</h2>
            <p class="mt-3 text-moss">{{ __('profile.notifications.lead') }}</p>

            <form method="POST" action="{{ route('cabinet.notifications.update') }}" class="mt-6">
                @csrf @method('PUT')
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-bone/80 text-left text-moss">
                            <th class="py-2 font-normal">{{ __('profile.notifications.event') }}</th>
                            <th class="w-24 py-2 text-center font-normal">Email</th>
                            <th class="w-24 py-2 text-center font-normal">Telegram</th>
                            <th class="w-24 py-2 text-center font-normal">Discord</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_keys(config('graj.notifications.events')) as $event)
                            @php($current = $user->notification_prefs[$event] ?? config("graj.notifications.events.{$event}"))
                            <tr class="border-b border-seam">
                                <th scope="row" class="py-3 text-left font-normal">{{ __("profile.notifications.events.{$event}") }}</th>
                                <td class="text-center">
                                    <input type="checkbox" name="prefs[{{ $event }}][]" value="mail" @checked(in_array('mail', $current)) class="size-4 accent-amber" aria-label="Email: {{ __("profile.notifications.events.{$event}") }}">
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" name="prefs[{{ $event }}][]" value="telegram" @checked(in_array('telegram', $current)) @disabled(! $user->telegram_chat_id) class="size-4 accent-amber disabled:opacity-40" aria-label="Telegram: {{ __("profile.notifications.events.{$event}") }}">
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" name="prefs[{{ $event }}][]" value="discord" @checked(in_array('discord', $current)) @disabled(! $user->discord_webhook) class="size-4 accent-amber disabled:opacity-40" aria-label="Discord: {{ __("profile.notifications.events.{$event}") }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="mt-3 text-xs text-moss">{{ __('profile.notifications.site_always') }}</p>
                <x-button class="mt-5">{{ __('profile.save') }}</x-button>
            </form>
        </section>

        <section aria-labelledby="n-tg">
            <h2 id="n-tg" class="font-display text-lg font-medium">Telegram</h2>
            @if ($user->telegram_chat_id)
                <p class="mt-3 text-signal">{{ __('profile.notifications.telegram_linked') }}</p>
                <form method="POST" action="{{ route('cabinet.notifications.telegram.unlink') }}" class="mt-4">
                    @csrf @method('DELETE')
                    <x-button variant="secondary">{{ __('profile.notifications.telegram_unlink') }}</x-button>
                </form>
            @elseif ($botLink)
                <p class="mt-3 text-moss">{{ __('profile.notifications.telegram_lead') }}</p>
                <x-button class="mt-4" :href="$botLink" target="_blank" rel="noopener">{{ __('profile.notifications.telegram_link') }}</x-button>
            @else
                <p class="mt-3 text-moss">{{ __('profile.notifications.telegram_unavailable') }}</p>
            @endif

            <h2 id="n-discord" class="mt-10 font-display text-lg font-medium">Discord</h2>
            <p class="mt-3 text-sm text-moss">{{ __('profile.notifications.discord_lead') }}</p>
            <form method="POST" action="{{ route('cabinet.notifications.discord') }}" class="mt-4 space-y-3">
                @csrf @method('PUT')
                <x-field name="discord_webhook" :label="__('profile.notifications.discord_label')" type="url" :value="$user->discord_webhook" placeholder="https://discord.com/api/webhooks/…" />
                <x-button variant="secondary">{{ __('profile.save') }}</x-button>
            </form>
        </section>
    </div>
</x-layouts.app>
