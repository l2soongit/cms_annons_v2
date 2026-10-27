<?php

namespace App\Http\Controllers;

use App\Notifications\Channels\TelegramChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('cabinet.profile', ['user' => $request->user()]);
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
            'prefs.*.*' => [Rule::in(['mail', 'telegram'])],
        ]);

        $prefs = collect($events)->mapWithKeys(fn ($event) => [$event => array_values($data['prefs'][$event] ?? [])])->all();

        $request->user()->forceFill(['notification_prefs' => $prefs])->save();

        return back()->with('status', __('profile.notifications.saved'));
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
