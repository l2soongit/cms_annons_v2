<x-layouts.cabinet :title="__('profile.nav.notifications').' | graj.club'" :heading="__('profile.notifications.title')" :subtitle="__('profile.notifications.lead')">
    <x-flash class="mb-6" />

    <div class="c-cols-side">
        <section class="c-card" aria-labelledby="n-prefs">
            <h2 id="n-prefs">{{ __('profile.notifications.channels') }}</h2>
            <form method="POST" action="{{ route('cabinet.notifications.update') }}">
                @csrf @method('PUT')
                <div style="overflow-x:auto">
                    <table class="c-table" style="min-width:30rem">
                        <thead>
                            <tr><th>{{ __('profile.notifications.event') }}</th><th style="text-align:center">Email</th><th style="text-align:center">Telegram</th><th style="text-align:center">Discord</th></tr>
                        </thead>
                        <tbody>
                            @foreach (array_keys(config('graj.notifications.events')) as $event)
                                @php $current = $user->notification_prefs[$event] ?? config("graj.notifications.events.{$event}"); @endphp
                                <tr>
                                    <th scope="row" style="font-weight:600;color:var(--c-text);font-size:14px;border-bottom:1px solid var(--c-line)">{{ __("profile.notifications.events.{$event}") }}</th>
                                    @foreach (['mail' => 'Email', 'telegram' => 'Telegram', 'discord' => 'Discord'] as $channel => $label)
                                        @php $off = ($channel === 'telegram' && ! $user->telegram_chat_id) || ($channel === 'discord' && ! $user->discord_webhook); @endphp
                                        <td style="text-align:center">
                                            <label class="c-toggle" @if ($off) title="{{ __('profile.notifications.connect_first') }}" @endif>
                                                <input type="checkbox" name="prefs[{{ $event }}][]" value="{{ $channel }}" @checked(in_array($channel, $current)) @disabled($off) aria-label="{{ $label }}: {{ __("profile.notifications.events.{$event}") }}">
                                                <span aria-hidden="true"></span>
                                            </label>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="c-hint" style="margin-top:12px">{{ __('profile.notifications.site_always') }}</p>
                <button class="c-btn is-go" style="margin-top:16px">{{ __('profile.save') }}</button>
            </form>
        </section>

        <div class="c-stack">
            <section class="c-card" aria-labelledby="n-tg">
                <h2 id="n-tg">Telegram
                    @if ($user->telegram_chat_id)<span class="c-status is-ok" style="margin-left:auto">✓ {{ __('profile.notifications.connected') }}</span>@endif
                </h2>
                @if ($user->telegram_chat_id)
                    <p class="c-muted" style="margin-top:10px;font-size:14px">{{ __('profile.notifications.telegram_linked') }}</p>
                    <form method="POST" action="{{ route('cabinet.notifications.telegram.unlink') }}" style="margin-top:14px">
                        @csrf @method('DELETE')
                        <button class="c-btn is-sm">{{ __('profile.notifications.telegram_unlink') }}</button>
                    </form>
                @elseif ($botLink)
                    <p class="c-muted" style="margin-top:10px;font-size:14px">{{ __('profile.notifications.telegram_lead') }}</p>
                    <a class="c-btn is-go" style="margin-top:14px" href="{{ $botLink }}" target="_blank" rel="noopener">{{ __('profile.notifications.telegram_link') }}</a>
                @else
                    <p class="c-muted" style="margin-top:10px;font-size:14px">{{ __('profile.notifications.telegram_unavailable') }}</p>
                @endif
            </section>

            <section class="c-card" aria-labelledby="n-discord">
                <h2 id="n-discord">Discord
                    @if ($user->discord_webhook)<span class="c-status is-ok" style="margin-left:auto">✓ {{ __('profile.notifications.connected') }}</span>@endif
                </h2>
                <p class="c-muted" style="margin-top:10px;font-size:14px">{{ __('profile.notifications.discord_lead') }}</p>
                <form method="POST" action="{{ route('cabinet.notifications.discord') }}">
                    @csrf @method('PUT')
                    <div class="c-field">
                        <label for="discord_webhook">{{ __('profile.notifications.discord_label') }}</label>
                        <div class="c-inp"><input id="discord_webhook" name="discord_webhook" type="url" value="{{ old('discord_webhook', $user->discord_webhook) }}" placeholder="https://discord.com/api/webhooks/…"></div>
                        @error('discord_webhook')<p class="c-err">{{ $message }}</p>@enderror
                    </div>
                    <button class="c-btn is-sm" style="margin-top:14px">{{ __('profile.save') }}</button>
                </form>
            </section>
        </div>
    </div>
</x-layouts.cabinet>
