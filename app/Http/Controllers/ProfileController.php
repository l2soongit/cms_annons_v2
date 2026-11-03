<?php

namespace App\Http\Controllers;

use App\Community\Perks;
use App\Notifications\Channels\DiscordChannel;
use App\Notifications\Channels\TelegramChannel;
use App\Support\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('cabinet.profile', ['user' => $request->user()]);
    }

    /**
     * Профіль на форумі: аватар (анімований GIF — з «плюшкою» магазину балів), підпис під повідомленнями
     * (текст — усім, картинка й посилання — з «плюшкою» «Підпис з картинкою»).
     */
    public function updateForum(Request $request): RedirectResponse
    {
        $user = $request->user();
        $plus = Perks::has($user, 'signature_plus');
        $data = $request->validate([
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'signature' => ['nullable', 'string', 'max:'.($plus ? 500 : 200)],
            'signature_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:512'],
            'remove_signature_image' => ['nullable', 'boolean'],
            'card_bg_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'remove_card_bg_image' => ['nullable', 'boolean'],
        ], [], [
            'avatar' => __('profile.forum.avatar'),
            'signature' => __('profile.forum.signature'),
            'signature_image' => __('profile.forum.signature_image'),
            'card_bg_image' => __('profile.forum.card_bg'),
        ]);

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            if (ImageOptimizer::isAnimatedGif($file) && ! Perks::has($user, 'gif_avatar')) {
                throw ValidationException::withMessages(['avatar' => __('profile.forum.gif_needs_perk')]);
            }
            $this->forget($user->avatar);
            $user->avatar = ImageOptimizer::store($file, 'avatars', 'avatar');
        } elseif ($request->boolean('remove_avatar')) {
            $this->forget($user->avatar);
            $user->avatar = null;
        }

        if ($plus && $request->hasFile('signature_image')) {
            $this->forget($user->signature_image);
            $user->signature_image = ImageOptimizer::store($request->file('signature_image'), 'signatures', 'signature');
        } elseif ($request->boolean('remove_signature_image')) {
            $this->forget($user->signature_image);
            $user->signature_image = null;
        }

        // Власний фон картки автора — лише з «плюшкою» «Фон картки автора»
        if (Perks::has($user, 'card_bg') && $request->hasFile('card_bg_image')) {
            $this->forget($user->card_bg_image);
            $user->card_bg_image = ImageOptimizer::store($request->file('card_bg_image'), 'card-backgrounds', 'card_bg');
        } elseif ($request->boolean('remove_card_bg_image')) {
            $this->forget($user->card_bg_image);
            $user->card_bg_image = null;
        }

        $user->signature = trim((string) ($data['signature'] ?? '')) ?: null;
        $user->save();

        return back()->with('status', __('profile.forum.saved'));
    }

    /** Видалити власний файл (не зовнішнє посилання, напр. аватар із соцмережі) */
    private function forget(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'http')) {
            Storage::disk('public')->delete($path);
        }
    }

    public function security(Request $request): View
    {
        return view('cabinet.security', ['user' => $request->user()]);
    }

    public function notifications(Request $request): View
    {
        $user = $request->user();

        if (! $user->telegram_chat_id && ! $user->telegram_link_token) {
            $user->forceFill(['telegram_link_token' => Str::random(24)])->save();
        }

        return view('cabinet.notifications', [
            'user' => $user,
            'botLink' => TelegramChannel::linkUrl($user),
        ]);
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $events = array_keys(config('graj.notifications.events'));

        $data = $request->validate([
            'prefs' => ['array'],
            'prefs.*' => ['array'],
            'prefs.*.*' => [Rule::in(['mail', 'telegram', 'discord'])],
        ]);

        $prefs = collect($events)->mapWithKeys(fn ($event) => [$event => array_values($data['prefs'][$event] ?? [])])->all();

        $request->user()->forceFill(['notification_prefs' => $prefs])->save();

        return back()->with('status', __('profile.notifications.saved'));
    }

    /** Вебхук Discord-каналу: зберегти (з тестовим повідомленням) або прибрати */
    public function updateDiscord(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'discord_webhook' => ['nullable', 'string', 'max:255', 'regex:'.DiscordChannel::URL_PATTERN],
        ], ['discord_webhook.regex' => __('profile.notifications.discord_invalid')]);

        $webhook = $data['discord_webhook'] ?? null;
        if ($webhook && ! DiscordChannel::sendMessage($webhook, __('profile.notifications.discord_test'))) {
            return back()->withInput()->withErrors(['discord_webhook' => __('profile.notifications.discord_failed')]);
        }

        $request->user()->forceFill(['discord_webhook' => $webhook])->save();

        return back()->with('status', __($webhook ? 'profile.notifications.discord_linked' : 'profile.notifications.discord_unlinked'));
    }

    public function unlinkTelegram(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['telegram_chat_id' => null, 'telegram_link_token' => Str::random(24)])->save();

        return back()->with('status', __('profile.notifications.telegram_unlinked'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('deleteAccount', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Сервери видаляються каскадно; голоси знеособлюються (user_id = null)
        $user->delete();

        return to_route('home')->with('status', __('profile.deleted'));
    }
}
