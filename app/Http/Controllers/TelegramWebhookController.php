<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Вебхук бота: `/start <токен>` прив'язує чат Telegram до акаунта.
 * Реєстрація вебхука: https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://graj.club/telegram/webhook/<SECRET>
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret): JsonResponse
    {
        $expected = (string) config('graj.telegram.webhook_secret');
        abort_unless($expected !== '' && hash_equals($expected, $secret), 404);

        $chatId = (string) $request->input('message.chat.id');
        $text = trim((string) $request->input('message.text'));

        if ($chatId === '' || ! str_starts_with($text, '/start')) {
            return response()->json(['ok' => true]);
        }

        $token = trim(substr($text, 6));
        $user = $token !== '' ? User::where('telegram_link_token', $token)->first() : null;

        if (! $user) {
            TelegramChannel::sendMessage($chatId, __('profile.notifications.telegram_bad_link'));

            return response()->json(['ok' => true]);
        }

        // Один чат — один акаунт
        User::where('telegram_chat_id', $chatId)->whereKeyNot($user->id)->update(['telegram_chat_id' => null]);

        $user->forceFill(['telegram_chat_id' => $chatId, 'telegram_link_token' => null])->save();

        TelegramChannel::sendMessage($chatId, __('profile.notifications.telegram_linked_message', ['name' => $user->name], $user->locale ?? 'uk'));

        return response()->json(['ok' => true]);
    }
}
